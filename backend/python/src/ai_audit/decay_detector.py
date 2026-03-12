
# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.2;
# CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 5.3
# SOURCE: CLAUDE.md Section 18 — Degradation quorum (3 of 4 engines) — locked
# SOURCE: CIE_v232_Hardening_Addendum.pdf — "3 of 4 engines must agree on degradation"
from api.gates_validate import BusinessRules
from collections import defaultdict


class DecayDetector:
    def __init__(self, db):
        self.db = db

    def detect(self) -> list:
        """
        Queries ai_audit_results for Hero SKUs with consecutive zero-score weeks.
        Returns list of dicts: {sku_id, consecutive_zero_weeks, decay_status, failing_questions}
        Only Hero SKUs are subject to decay detection.
        A week counts as 'zero' if the aggregate citation rate for that SKU is 0
        across all available engines for that run week.
        Engine-unavailable results (is_available=False) are excluded from the check.
        """
        yellow_flag_weeks = int(BusinessRules.get('decay.yellow_flag_weeks'))
        alert_weeks = int(BusinessRules.get('decay.alert_weeks'))
        auto_brief_weeks = int(BusinessRules.get('decay.auto_brief_weeks'))
        escalate_weeks = int(BusinessRules.get('decay.escalate_weeks'))

        results = []
        hero_skus = self._load_hero_skus()

        for sku in hero_skus:
            consecutive_zeros, failing_questions = self._count_consecutive_zeros(sku['sku_id'])

            if consecutive_zeros < yellow_flag_weeks:
                continue

            if consecutive_zeros >= escalate_weeks:
                status = 'escalated'
            elif consecutive_zeros >= auto_brief_weeks:
                status = 'auto_brief'
            elif consecutive_zeros >= alert_weeks:
                status = 'alert'
            else:
                status = 'yellow_flag'

            results.append({
                'sku_id': sku['sku_id'],
                'consecutive_zero_weeks': consecutive_zeros,
                'decay_status': status,
                'failing_questions': failing_questions,
            })

        return results

    def _load_hero_skus(self):
        """Query sku_master where tier = 'HERO' and return list of dicts with sku_id."""
        cur = self.db.cursor()
        cur.execute("SELECT sku_id FROM sku_master WHERE tier = 'HERO'")
        rows = cur.fetchall()
        cur.close()
        return [{'sku_id': row[0]} for row in rows]

    def _count_consecutive_zeros(self, sku_id):
        """
        Query ai_audit_results for the given SKU, ordered by week_ending DESC.
        Count consecutive weeks where degradation quorum is met: >= 3 of 4 engines
        must agree on degradation (score 0). Engine-unavailable results excluded.
        Return (count, failing_question_ids for most recent run).
        SOURCE: CLAUDE.md Section 18 — Degradation quorum (3 of 4 engines) — locked
        """
        cur = self.db.cursor()
        # Include engine so we can apply 3-of-4 quorum per week
        cur.execute("""
            SELECT week_ending, question_id, engine, score, is_available
            FROM ai_audit_results
            WHERE sku_id = %s
            ORDER BY week_ending DESC, question_id, engine
        """, (sku_id,))
        rows = cur.fetchall()
        cur.close()
        # Handle both tuple and dict row formats; schema may not have week_ending/sku_id in all envs
        try:
            week_idx = 0
            qid_idx = 1
            engine_idx = 2
            score_idx = 3
            avail_idx = 4
            if rows and len(rows[0]) < 5:
                engine_idx = None
                score_idx = 2
                avail_idx = 3
        except Exception:
            week_idx, qid_idx, engine_idx, score_idx, avail_idx = 0, 1, 2, 3, 4

        weeks = defaultdict(lambda: defaultdict(list))  # week_ending -> engine -> [scores]
        for row in rows:
            week_ending = row[week_idx] if isinstance(row, (list, tuple)) else row.get('week_ending')
            question_id = row[qid_idx] if isinstance(row, (list, tuple)) else row.get('question_id')
            engine = row[engine_idx] if engine_idx is not None and isinstance(row, (list, tuple)) else (row.get('engine') if isinstance(row, dict) else None)
            score = row[score_idx] if isinstance(row, (list, tuple)) else row.get('score')
            is_available = row[avail_idx] if avail_idx is not None and isinstance(row, (list, tuple)) else row.get('is_available', True)
            if not is_available:
                continue
            if engine is not None:
                weeks[week_ending][engine].append(score)
            else:
                weeks[week_ending]['_'].append(score)

        consecutive_zeros = 0
        failing_questions = []
        for week, engine_scores in sorted(weeks.items(), reverse=True):
            if engine_scores:
                # Per-engine aggregate: min score (0 if any question scored 0 for that engine)
                if next(iter(engine_scores)) == '_':
                    available_scores = engine_scores['_']
                else:
                    available_scores = [min(scores) for scores in engine_scores.values() if scores]
            else:
                available_scores = []
            if len(available_scores) < 3:
                break  # Need at least 3 engine results to apply quorum
            # SOURCE: CLAUDE.md Section 18 — 3 of 4 engines must agree on degradation
            week_is_degraded = sum(1 for s in available_scores if s is not None and s == 0) >= 3
            if not week_is_degraded:
                break
            consecutive_zeros += 1
            if consecutive_zeros == 1:
                failing_questions = list(set(
                    r.get('question_id') if isinstance(r, dict) else r[qid_idx]
                    for r in rows
                    if (r[week_idx] if isinstance(r, (list, tuple)) else r.get('week_ending')) == week
                )) if rows else []
        return consecutive_zeros, failing_questions

