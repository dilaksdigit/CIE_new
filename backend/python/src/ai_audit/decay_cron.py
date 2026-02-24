"""
Citation decay escalation cron (Python equivalent of N8N workflow).

Implements CIE v2.3.1 §5.3 / 9.3:
- After a weekly AI citation audit has run, inspect latest run per category.
- For Hero SKUs, compute whether this week is a "zero citation" week.
- Maintain per-SKU consecutive_zero_weeks via `skus.decay_consecutive_zeros`.
- Escalate decay_status:
  - 1 week  => 'yellow_flag'
  - 2 weeks => 'alert'
  - 3 weeks => 'auto_brief' (auto brief generated)
  - 4+ weeks=> 'escalated'

NOTE:
- This module assumes a PEP-249 DB-API 2.0 connection `db` is provided.
- HTTP calls (e.g. to POST /api/v1/brief/generate or Slack/email) are stubbed via
  simple hooks so that callers or N8N can wire real integrations.
"""

from __future__ import annotations

import datetime as dt
import logging
import os
from typing import Any, Callable, Dict, Iterable, List, Optional, Sequence, Tuple

logger = logging.getLogger(__name__)


def _get_latest_completed_runs(db) -> List[Tuple[str, str]]:
    """
    Return list of (category, run_id) for the most recent completed & quorum_met run
    per category from ai_audit_runs.
    """
    cur = db.cursor()
    # Latest run per category where status=completed and quorum_met=1
    cur.execute(
        """
        SELECT r.category, r.run_id
        FROM ai_audit_runs r
        JOIN (
            SELECT category, MAX(run_date) AS max_date
            FROM ai_audit_runs
            WHERE status = 'completed' AND (quorum_met = 1 OR quorum_met IS TRUE)
            GROUP BY category
        ) latest
          ON latest.category = r.category AND latest.max_date = r.run_date
        WHERE r.status = 'completed' AND (r.quorum_met = 1 OR r.quorum_met IS TRUE)
        """
    )
    rows = cur.fetchall()
    cur.close()
    return [(row[0], row[1]) for row in rows]


def _load_golden_queries_for_run(db, category: str) -> List[Dict[str, Any]]:
    """Load active golden queries + their target_skus for a category."""
    import json

    cur = db.cursor()
    cur.execute(
        """
        SELECT question_id, question_text, target_skus
        FROM ai_golden_queries
        WHERE category = %s AND is_active = 1
        ORDER BY question_id
        """,
        (category,),
    )
    rows = cur.fetchall()
    cur.close()

    questions: List[Dict[str, Any]] = []
    for row in rows:
        qid, text, raw_targets = row[0], row[1], row[2]
        try:
            targets = json.loads(raw_targets) if isinstance(raw_targets, str) else raw_targets
        except Exception:
            targets = []
        if not isinstance(targets, list):
            targets = []
        questions.append(
            {
                "id": qid,
                "text": text,
                "target_skus": targets,
            }
        )
    return questions


def _load_hero_skus_by_code(db, sku_codes: Sequence[str]) -> Dict[str, Dict[str, Any]]:
    """Return mapping sku_code -> {id, sku_code, title, tier, margin_percent, ai_answer_block} for HERO SKUs in given codes."""
    if not sku_codes:
        return {}
    placeholders = ",".join(["%s"] * len(sku_codes))
    sql = f"""
        SELECT id, sku_code, title, tier, margin_percent, ai_answer_block
        FROM skus
        WHERE tier = 'HERO'
          AND sku_code IN ({placeholders})
    """
    cur = db.cursor()
    cur.execute(sql, tuple(sku_codes))
    rows = cur.fetchall()
    cur.close()

    out: Dict[str, Dict[str, Any]] = {}
    for row in rows:
        out[row[1]] = {
            "id": row[0],
            "sku_code": row[1],
            "title": row[2],
            "tier": row[3],
            "margin_percent": float(row[4]) if row[4] is not None else None,
            "ai_answer_block": row[5] or "",
        }
    return out


def _load_question_scores_for_run(db, run_id: str) -> Dict[str, int]:
    """
    For a run, return per-question max score across engines (0–3) where score is not NULL.
    If all engine rows for a question are NULL, the question is treated as score 0.
    """
    cur = db.cursor()
    cur.execute(
        """
        SELECT question_id, score
        FROM ai_audit_results
        WHERE run_id = %s
        """,
        (run_id,),
    )
    rows = cur.fetchall()
    cur.close()

    per_question: Dict[str, List[Optional[int]]] = {}
    for qid, score in rows:
        per_question.setdefault(qid, []).append(score)

    agg: Dict[str, int] = {}
    for qid, scores in per_question.items():
        numeric = [s for s in scores if s is not None]
        agg[qid] = max(numeric) if numeric else 0
    return agg


def _compute_zero_flag_for_sku(
    sku_code: str,
    questions: Sequence[Dict[str, Any]],
    question_scores: Dict[str, int],
) -> bool:
    """
    Determine whether this Hero SKU had a "zero citation" week:
    - Look at all questions where this SKU is in target_skus.
    - If ALL of those questions have score == 0 in the latest run, return True.
    - If any question has score > 0, treat as non-zero week (False).
    """
    relevant_qids = [
        q["id"]
        for q in questions
        if sku_code in (q.get("target_skus") or [])
    ]
    if not relevant_qids:
        return False
    for qid in relevant_qids:
        if question_scores.get(qid, 0) > 0:
            return False
    return True


def _update_sku_decay(
    db,
    sku: Dict[str, Any],
    is_zero_week: bool,
    now: Optional[dt.date] = None,
) -> Tuple[int, str]:
    """
    Apply decay escalation rules to a single SKU using skus.decay_consecutive_zeros / decay_status.
    Returns (new_consecutive_zeros, new_status).
    """
    cur = db.cursor()
    cur.execute(
        """
        SELECT decay_consecutive_zeros, decay_status
        FROM skus
        WHERE id = %s
        """,
        (sku["id"],),
    )
    row = cur.fetchone()
    current_zeros = int(row[0]) if row and row[0] is not None else 0
    current_status = row[1] if row else "none"

    if not is_zero_week:
        # Any non-zero score self-heals decay counter
        cur.execute(
            """
            UPDATE skus
            SET decay_weeks = 0,
                decay_consecutive_zeros = 0,
                decay_status = 'none'
            WHERE id = %s
            """,
            (sku["id"],),
        )
        db.commit()
        cur.close()
        return 0, "none"

    # Zero week: increment counter
    new_zeros = current_zeros + 1
    if new_zeros == 1:
        status = "yellow_flag"
    elif new_zeros == 2:
        status = "alert"
    elif new_zeros == 3:
        status = "auto_brief"
    else:
        status = "escalated"

    cur.execute(
        """
        UPDATE skus
        SET decay_weeks = %s,
            decay_consecutive_zeros = %s,
            decay_status = %s
        WHERE id = %s
        """,
        (new_zeros, new_zeros, status, sku["id"]),
    )
    db.commit()
    cur.close()
    return new_zeros, status


def default_brief_generate_hook(payload: Dict[str, Any]) -> None:
    """
    Default hook: POST to Python worker /queue/brief-generation so brief content is actually generated.
    Set CIE_PYTHON_WORKER_URL (e.g. http://python-worker:5000) if worker is on another host.
    """
    try:
        import requests
        base_url = os.environ.get("CIE_PYTHON_WORKER_URL", "http://localhost:5000").rstrip("/")
        url = f"{base_url}/queue/brief-generation"
        sku_id = payload.get("sku_id")
        title = payload.get("sku_title") or payload.get("sku_code") or ""
        if not sku_id or not title:
            logger.warning("Auto-brief payload missing sku_id or title: %s", list(payload.keys()))
            return
        resp = requests.post(
            url,
            json={"sku_id": sku_id, "title": title},
            timeout=5,
        )
        if resp.status_code in (200, 202):
            data = resp.json() or {}
            logger.info("Auto-brief queued for sku_id=%s: %s", sku_id, data.get("brief_id"))
        else:
            logger.error("Auto-brief queue failed for sku_id=%s: %s %s", sku_id, resp.status_code, resp.text[:200])
    except Exception as e:
        logger.exception("Failed to queue auto-brief for %s: %s", payload.get("sku_code"), e)


def _build_brief_payload(
    sku: Dict[str, Any],
    failing_questions: Sequence[Dict[str, Any]],
    competitor_answers: Sequence[str],
    deadline_days: int = 7,
) -> Dict[str, Any]:
    """
    Build payload for POST /api/v1/brief/generate.
    Auto-brief contents per spec:
      1. SKU ID + Name + Tier + Current Margin
      2. Failing questions with current score = 0
      3. Current AI Answer Block
      4. Top 3 competitor answers (from AI responses)
      5. Suggested revision direction (left to brief service / LLM)
      6. Deadline: 7 days
      7. Success criteria: Score >=1 on next weekly audit
    """
    deadline = (dt.date.today() + dt.timedelta(days=deadline_days)).isoformat()
    return {
        "sku_id": sku["id"],
        "sku_code": sku["sku_code"],
        "sku_title": sku["title"],
        "tier": sku["tier"],
        "margin_percent": sku["margin_percent"],
        "current_answer_block": sku.get("ai_answer_block") or "",
        "failing_questions": [
            {"id": q["id"], "text": q["text"]} for q in failing_questions
        ],
        "competitor_answers": competitor_answers[:3],
        "suggested_revision_direction": "Refresh Answer Block and authority content to align better with failing queries and out-compete top AI responses.",
        "deadline": deadline,
        "success_criteria": "Citation score >= 1 on next weekly AI audit for all listed questions.",
    }


def run_decay_escalation(
    db,
    brief_generate_hook,
) -> List[Dict[str, Any]]:
    """
    Main entry point for Python cron:
    - Examines latest completed & quorum_met audit run per category.
    - For each Hero SKU targeted by golden queries, determines if this week is zero-citation.
    - Updates skus.decay_consecutive_zeros / decay_status.
    - Invokes `brief_generate_hook(payload)` when reaching week 3 (auto_brief).

    `brief_generate_hook` is a callable that takes the brief payload dict and performs side effects
    (e.g. HTTP POST to /api/v1/brief/generate, N8N node, email, etc.).

    Returns a list of actions taken for logging/testing.
    """
    actions: List[Dict[str, Any]] = []

    latest_runs = _get_latest_completed_runs(db)
    if not latest_runs:
        return actions

    for category, run_id in latest_runs:
        questions = _load_golden_queries_for_run(db, category)
        if not questions:
            continue

        # Collect all target SKU codes for this category
        sku_codes: List[str] = []
        for q in questions:
            for code in q.get("target_skus") or []:
                if code not in sku_codes:
                    sku_codes.append(code)

        hero_skus = _load_hero_skus_by_code(db, sku_codes)
        if not hero_skus:
            continue

        question_scores = _load_question_scores_for_run(db, run_id)

        # Pre-map questions by id for failing list
        q_by_id = {q["id"]: q for q in questions}

        for sku_code, sku in hero_skus.items():
            is_zero_week = _compute_zero_flag_for_sku(sku_code, questions, question_scores)
            new_zeros, status = _update_sku_decay(db, sku, is_zero_week)

            action = {
                "category": category,
                "run_id": run_id,
                "sku_code": sku_code,
                "zeros": new_zeros,
                "decay_status": status,
            }

            # Week 3: auto-brief generation
            if status == "auto_brief" and is_zero_week:
                failing_qs = [
                    q_by_id[qid]
                    for qid, score in question_scores.items()
                    if score == 0
                    and sku_code in (q_by_id.get(qid, {}).get("target_skus") or [])
                ]
                competitor_answers: List[str] = []

                # Fetch top response snippets for failing questions as proxy competitor answers
                if failing_qs:
                    cur = db.cursor()
                    cur.execute(
                        """
                        SELECT question_id, response_snippet, score
                        FROM ai_audit_results
                        WHERE run_id = %s
                          AND question_id IN ({placeholders})
                        """.format(
                            placeholders=",".join(["%s"] * len(failing_qs))
                        ),
                        (run_id, *[fq["id"] for fq in failing_qs]),
                    )
                    rows = cur.fetchall()
                    cur.close()
                    # Sort by score descending to approximate "top competitor answers"
                    rows_sorted = sorted(
                        rows, key=lambda r: (r[2] if r[2] is not None else 0), reverse=True
                    )
                    for _, snippet, _ in rows_sorted:
                        if snippet:
                            competitor_answers.append(snippet[:500])

                payload = _build_brief_payload(
                    sku=sku,
                    failing_questions=failing_qs,
                    competitor_answers=competitor_answers,
                )
                try:
                    brief_generate_hook(payload)
                    action["auto_brief_generated"] = True
                except Exception as e:
                    logger.warning("brief_generate_hook failed for %s: %s", sku_code, e)
                    action["auto_brief_generated"] = False

            actions.append(action)

    return actions

