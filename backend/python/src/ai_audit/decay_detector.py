
# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.2;
# CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 5.3
class DecayDetector:
    def __init__(self, db, business_rules=None):
        self.db = db
        self.business_rules = business_rules or self._default_business_rules()

    def detect(self) -> list:
        """
        Queries ai_audit_results for Hero SKUs with consecutive zero-score weeks.
        Returns list of dicts: {sku_id, consecutive_zero_weeks, decay_status, failing_questions}
        Only Hero SKUs are subject to decay detection.
        A week counts as 'zero' if the aggregate citation rate for that SKU is 0
        across all available engines for that run week.
        Engine-unavailable results (is_available=False) are excluded from the check.
        """
        yellow_flag_weeks = int(self.business_rules.get('decay.yellow_flag_weeks', 1))
        alert_weeks = int(self.business_rules.get('decay.alert_weeks', 2))
        auto_brief_weeks = int(self.business_rules.get('decay.auto_brief_weeks', 3))
        escalate_weeks = int(self.business_rules.get('decay.escalate_weeks', 4))

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
        Count consecutive weeks where aggregate score = 0 (is_available=True only).
        Return (count, failing_question_ids for most recent run).
        """
        cur = self.db.cursor()
        cur.execute("""
            SELECT week_ending, question_id, score, is_available
            FROM ai_audit_results
            WHERE sku_id = %s
            ORDER BY week_ending DESC, question_id
        """, (sku_id,))
        rows = cur.fetchall()
        cur.close()
        # Group by week_ending
        from collections import defaultdict
        weeks = defaultdict(list)
        for week_ending, question_id, score, is_available in rows:
            weeks[week_ending].append({'question_id': question_id, 'score': score, 'is_available': is_available})
        consecutive_zeros = 0
        failing_questions = []
        for week, qrows in sorted(weeks.items(), reverse=True):
            # Only consider is_available=True
            available_scores = [r['score'] for r in qrows if r['is_available']]
            if not available_scores:
                break  # No available results, stop counting
            if all((s is not None and s == 0) for s in available_scores):
                consecutive_zeros += 1
                if consecutive_zeros == 1:
                    failing_questions = [r['question_id'] for r in qrows if r['is_available'] and r['score'] == 0]
            else:
                break
        return consecutive_zeros, failing_questions

    def _default_business_rules(self):
        # Fallbacks if not injected
        return {
            'decay.yellow_flag_weeks': 1,
            'decay.alert_weeks': 2,
            'decay.auto_brief_weeks': 3,
            'decay.escalate_weeks': 4,
        }
