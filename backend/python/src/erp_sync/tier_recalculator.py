"""
CIE v2.3.1 — Tier recalculator: ERP sync payload → score → percentile tier assignment.

SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2
        CIE_Master_Developer_Build_Spec.docx §8.1 §8.2
        CIE_v231_Developer_Build_Pack.pdf (sync sequence diagram)
        CIE_Integration_Specification.pdf §1.2, §1.3
        CIE_Master_Developer_Build_Spec.docx §5; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.2; CIE_Integration_Specification.pdf §1.3
"""
from __future__ import annotations

import logging
import os
from datetime import datetime
from typing import Any
import numpy as np

logger = logging.getLogger(__name__)


def _get_db():
    from utils.mysql_connect import pymysql_connect_dict_cursor

    return pymysql_connect_dict_cursor()


def _get_business_rule(cursor, key: str, default=None):
    """Read a single business rule, with type coercion."""
    cursor.execute(
        "SELECT value, value_type FROM business_rules WHERE rule_key = %s", (key,)
    )
    row = cursor.fetchone()
    if row is None:
        if default is not None:
            return default
        raise RuntimeError(f"Business rule key not found: {key}")
    raw = row["value"]
    vtype = (row.get("value_type") or "string").lower()
    if vtype == "integer":
        return int(raw)
    if vtype == "float":
        return float(raw)
    if vtype == "boolean":
        return raw.lower() in ("true", "1", "yes")
    return raw


class TierRecalculator:
    """Processes an ERP sync payload: updates sku_master, recomputes tiers, persists changes."""

    def recalculate(self, payload: dict[str, Any]) -> dict[str, Any]:
        sync_date = payload.get("sync_date", datetime.utcnow().isoformat())
        sku_rows = payload.get("skus", [])
        errors: list[str] = []
        tier_changes = 0
        auto_promotions = 0

        db = _get_db()
        try:
            with db.cursor() as cursor:
                # ── Load weights and percentile thresholds from BusinessRules ──
                w_margin = float(_get_business_rule(cursor, "tier.margin_weight"))
                w_cppc = float(_get_business_rule(cursor, "tier.cppc_weight"))
                w_velocity = float(_get_business_rule(cursor, "tier.velocity_weight"))
                w_returns = float(_get_business_rule(cursor, "tier.returns_weight"))

                hero_pct = float(_get_business_rule(cursor, "tier.hero_percentile_threshold"))
                support_pct = float(_get_business_rule(cursor, "tier.support_percentile_threshold"))
                harvest_pct = float(_get_business_rule(cursor, "tier.harvest_percentile_threshold"))

                # ── STEP 1+2: Parse payload, capture previous velocity, update sku_master ──
                previous_velocities: dict[str, float] = {}

                for row in sku_rows:
                    sku_id = row.get("sku_id")
                    if not sku_id:
                        errors.append("Row missing sku_id")
                        continue

                    margin = row.get("contribution_margin_pct")
                    cppc = row.get("cppc")
                    velocity = row.get("velocity_90d")
                    return_rate = row.get("return_rate_pct")

                    if any(v is None for v in (margin, cppc, velocity, return_rate)):
                        errors.append(f"Incomplete data for SKU {sku_id}")
                        continue

                    try:
                        cursor.execute(
                            "SELECT id, erp_velocity_90d FROM sku_master WHERE sku_id = %s",
                            (sku_id,),
                        )
                        existing = cursor.fetchone()
                        if not existing:
                            errors.append(f"SKU {sku_id} not found in sku_master")
                            continue

                        previous_velocities[sku_id] = float(existing.get("erp_velocity_90d") or 0)

                        cursor.execute(
                            "UPDATE sku_master "
                            "SET erp_margin_pct = %s, erp_cppc = %s, "
                            "    erp_velocity_90d = %s, erp_return_rate_pct = %s "
                            "WHERE sku_id = %s",
                            (margin, cppc, velocity, return_rate, sku_id),
                        )
                    except Exception as exc:
                        errors.append(f"DB error updating SKU {sku_id}: {exc}")
                        logger.exception("Failed to update sku_master for %s", sku_id)

                db.commit()

                # ── STEP 3: Compute Commercial Priority Score for ALL SKUs ──
                cursor.execute(
                    "SELECT sku_id, erp_margin_pct, erp_cppc, erp_velocity_90d, "
                    "       erp_return_rate_pct, tier "
                    "FROM sku_master "
                    "WHERE erp_margin_pct IS NOT NULL "
                    "  AND erp_cppc IS NOT NULL "
                    "  AND erp_return_rate_pct IS NOT NULL"
                )
                all_skus = cursor.fetchall()

                if not all_skus:
                    return self._build_response(sync_date, len(sku_rows), 0, 0, errors)

                max_velocity = max(
                    (float(s.get("erp_velocity_90d") or 0) for s in all_skus),
                    default=1.0,
                )
                if max_velocity <= 0:
                    max_velocity = 1.0

                scores: dict[str, float] = {}
                sku_lookup: dict[str, dict] = {}

                for s in all_skus:
                    sid = s["sku_id"]
                    sku_lookup[sid] = s
                    m = float(s.get("erp_margin_pct") or 0)
                    c = float(s.get("erp_cppc") or 0)
                    v = float(s.get("erp_velocity_90d") or 0)
                    r = float(s.get("erp_return_rate_pct") or 0)

                    score = (
                        (m / 100.0) * w_margin
                        + (1.0 / max(c, 0.01)) * w_cppc
                        + (v / max_velocity) * w_velocity
                        + (1.0 - r / 100.0) * w_returns
                    )
                    scores[sid] = round(score, 4)

                # ── STEP 4: Assign tiers using percentile bands ──
                score_values = np.array(list(scores.values()))
                p80_cut = float(np.percentile(score_values, hero_pct * 100))
                p30_cut = float(np.percentile(score_values, support_pct * 100))
                p10_cut = float(np.percentile(score_values, harvest_pct * 100))

                new_tiers: dict[str, str] = {}
                for sid, score in scores.items():
                    if score >= p80_cut:
                        new_tiers[sid] = "hero"
                    elif score >= p30_cut:
                        new_tiers[sid] = "support"
                    elif score >= p10_cut:
                        new_tiers[sid] = "harvest"
                    else:
                        new_tiers[sid] = "kill"

                # ── STEP 5: Auto-promotion (harvest → support on >30% velocity QoQ) ──
                for sid, tier in list(new_tiers.items()):
                    if tier != "harvest":
                        continue
                    prev_vel = previous_velocities.get(sid)
                    if prev_vel is None:
                        continue
                    curr_vel = float(sku_lookup[sid].get("erp_velocity_90d") or 0)
                    auto_promo_threshold = _get_business_rule(
                        cursor, 'tier.auto_promotion_velocity_growth_pct'
                    )
                    growth_multiplier = 1.0 + float(auto_promo_threshold)
                    if prev_vel > 0 and curr_vel > prev_vel * growth_multiplier:
                        new_tiers[sid] = "support"
                        auto_promotions += 1

                # ── STEP 6: Persist — update sku_master, insert tier_history + audit_log ──
                for sid, new_tier in new_tiers.items():
                    old_tier = (sku_lookup[sid].get("tier") or "").lower()

                    cursor.execute(
                        "UPDATE sku_master SET commercial_score = %s WHERE sku_id = %s",
                        (scores[sid], sid),
                    )

                    if new_tier == old_tier:
                        continue

                    tier_changes += 1

                    cursor.execute(
                        "UPDATE sku_master SET tier = %s WHERE sku_id = %s",
                        (new_tier, sid),
                    )

                    cursor.execute(
                        "INSERT INTO tier_history "
                        "(sku_id, old_tier, new_tier, reason, changed_at) "
                        "VALUES (%s, %s, %s, %s, %s)",
                        (sid, old_tier or None, new_tier, "erp_sync", sync_date),
                    )

                    cursor.execute(
                        "INSERT INTO audit_log "
                        "(entity_type, entity_id, action, old_value, new_value, created_at) "
                        "VALUES ('sku', %s, 'tier_change', %s, %s, %s)",
                        (sid, old_tier, new_tier, sync_date),
                    )

                db.commit()

            # ── STEP 7: Notify CMS of tier changes ──
            if tier_changes > 0:
                self._notify_cms(new_tiers, sku_lookup)

        except Exception as exc:
            logger.exception("Tier recalculation failed: %s", exc)
            errors.append(f"Recalculation error: {exc}")
            db.rollback()
        finally:
            db.close()

        # ── STEP 8: Return sync summary ──
        return self._build_response(sync_date, len(sku_rows), tier_changes, auto_promotions, errors)

    # ------------------------------------------------------------------
    @staticmethod
    def _build_response(
        sync_date: str, skus_processed: int, tier_changes: int,
        auto_promotions: int, errors: list[str],
    ) -> dict[str, Any]:
        return {
            "sync_date": sync_date,
            "skus_processed": skus_processed,
            "tier_changes": tier_changes,
            "auto_promotions": auto_promotions,
            "errors": errors,
        }

    @staticmethod
    def _notify_cms(new_tiers: dict[str, str], sku_lookup: dict[str, dict]) -> None:
        """POST tier changes to PHP CMS so UI tier restrictions update."""
        import requests

        cms_url = os.environ.get(
            "CIE_CMS_URL", "http://localhost:8000"
        ).rstrip("/")
        url = f"{cms_url}/api/tier-changes"

        changes = [
            {"sku_id": sid, "new_tier": tier}
            for sid, tier in new_tiers.items()
            if tier != (sku_lookup.get(sid, {}).get("tier") or "").lower()
        ]
        if not changes:
            return

        try:
            resp = requests.post(url, json={"changes": changes}, timeout=10)
            if resp.status_code in (200, 202):
                logger.info("CMS notified of %d tier changes", len(changes))
            else:
                logger.error(
                    "CMS notification failed: %s %s", resp.status_code, resp.text[:200]
                )
        except Exception as exc:
            logger.exception("Failed to notify CMS: %s", exc)
