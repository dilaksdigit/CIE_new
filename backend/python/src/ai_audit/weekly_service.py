"""
CIE v2.3.1 / v2.3.2 — Weekly AI citation audit service.

Implements Document 10 (AI Citation Audits) and Patch 2 (AI Audit Degradation Rules):

- Loads golden queries for a category (20 questions each) from ai_golden_queries.
- For each question, queries 4 engines: ChatGPT, Gemini, Perplexity, Google SGE.
- Scores each response with citation score 0–3:
  0 = not mentioned, 1 = cited, 2 = summarised, 3 = selected.
- Stores per-engine, per-question rows in ai_audit_results.
- Writes a run row in ai_audit_runs with aggregate citation rate and quorum metadata.
- Compares to previous 2 weeks to support decay detection.
- Handles engine failures and rate limits gracefully; engines can be 'unavailable' instead of score=0.

NOTE: This module assumes a PEP-249 DB-API 2.0 connection is provided by the caller.
It does not manage the connection lifecycle itself.
"""

from __future__ import annotations

import datetime as dt
import logging
import uuid
from dataclasses import dataclass
from difflib import SequenceMatcher
from typing import Any, Dict, List, Optional, Sequence, Tuple

logger = logging.getLogger(__name__)

ENGINES: Sequence[str] = ("chatgpt", "gemini", "perplexity", "google_sge")


@dataclass
class EngineQuestionResult:
    question_id: str
    engine: str
    score: Optional[int]  # 0–3 or None when unavailable / skipped
    response_snippet: str
    skip_reason: Optional[str]  # None, 'timeout', 'rate_limited', 'api_error', 'engine_down'


@dataclass
class EngineRunSummary:
    engine: str
    status: str  # 'complete', 'rate_limited', 'engine_down'
    results: List[EngineQuestionResult]


def load_golden_queries(db, category: str) -> List[Dict[str, Any]]:
    """
    Load golden queries JSON for a category from ai_golden_queries.
    Returns list of dicts with at least: question_id, question_text, target_skus (list of sku_codes).
    """
    sql = """
        SELECT question_id,
               question_text,
               target_skus
        FROM ai_golden_queries
        WHERE category = %s
          AND is_active = 1
          AND (locked_until IS NULL OR locked_until <= CURRENT_DATE)
        ORDER BY question_id
    """
    cur = db.cursor()
    cur.execute(sql, (category,))
    rows = cur.fetchall()
    cur.close()

    import json

    queries: List[Dict[str, Any]] = []
    for row in rows:
        # Row is either tuple or mapping depending on cursor type
        question_id = row[0] if isinstance(row, tuple) else row["question_id"]
        question_text = row[1] if isinstance(row, tuple) else row["question_text"]
        raw_targets = row[2] if isinstance(row, tuple) else row["target_skus"]
        try:
            target_skus = json.loads(raw_targets) if isinstance(raw_targets, str) else raw_targets
        except Exception:
            target_skus = []
        if not isinstance(target_skus, list):
            target_skus = []
        queries.append(
            {
                "id": question_id,
                "text": question_text,
                "target_skus": target_skus,
            }
        )
    return queries


def _load_sku_metadata(db, sku_codes: Sequence[str]) -> Dict[str, Dict[str, Any]]:
    """
    Load minimal metadata for target SKUs: product_name (title) and answer_block (ai_answer_block).
    Keyed by sku_code.
    """
    if not sku_codes:
        return {}
    sql = """
        SELECT sku_code, title, ai_answer_block
        FROM skus
        WHERE sku_code IN ({placeholders})
    """.format(
        placeholders=",".join(["%s"] * len(sku_codes))
    )
    cur = db.cursor()
    cur.execute(sql, tuple(sku_codes))
    rows = cur.fetchall()
    cur.close()
    meta: Dict[str, Dict[str, Any]] = {}
    for row in rows:
        sku_code = row[0]
        title = row[1]
        answer_block = row[2] if len(row) > 2 else None
        meta[sku_code] = {
            "product_name": title,
            "answer_block": answer_block or "",
        }
    return meta


def query_engine(engine: str, question_text: str, brand_name: str) -> str:
    """
    Placeholder query implementation.

    In production, this should:
      - Use ChatGPT (OpenAI), Gemini, Perplexity APIs, or a Google SGE scraper.
      - Return the full text answer from each engine.

    For now, returns a deterministic stub that contains the brand name so the
    evaluation logic can be exercised without external calls.
    """
    return f"{brand_name} answer for '{question_text}'"


def evaluate_citation(
    response: str,
    brand_name: str,
    product_name: Optional[str],
    answer_block: Optional[str],
) -> int:
    """
    Compute citation score 0–3 based on whether the AI response:
    - Mentions our brand name
    - Mentions our product name
    - Fuzzily matches our answer_block (>60%)

    0 = not mentioned
    1 = cited (brand mentioned)
    2 = summarised (product mentioned or fuzzy match >= 0.6)
    3 = selected (fuzzy match >= 0.8)
    """
    if not response:
        return 0

    text = response.lower()
    score = 0

    if brand_name and brand_name.lower() in text:
        score = max(score, 1)

    if product_name:
        pn = product_name.lower()
        if pn in text:
            score = max(score, 2)

    if answer_block:
        ab = answer_block.lower()
        ratio = SequenceMatcher(None, ab, text).ratio()
        if ratio >= 0.8:
            score = 3
        elif ratio >= 0.6:
            score = max(score, 2)

    return score


def run_audit_for_engine(
    engine: str,
    questions: Sequence[Dict[str, Any]],
    sku_meta: Dict[str, Dict[str, Any]],
    brand_name: str,
) -> EngineRunSummary:
    """
    Run audit for a single engine across all questions.
    Implements Patch 2 §2.2 per-engine failure handling (in a simplified form).
    """
    results: List[EngineQuestionResult] = []
    consecutive_failures = 0

    for q in questions:
        qid = q["id"]
        text = q["text"]
        target_skus: List[str] = q.get("target_skus") or []
        primary_sku_code = target_skus[0] if target_skus else None
        meta = sku_meta.get(primary_sku_code or "", {})
        product_name = meta.get("product_name")
        answer_block = meta.get("answer_block")

        try:
            response = query_engine(engine, text, brand_name)
            score = evaluate_citation(response, brand_name, product_name, answer_block)
            snippet = (response or "")[:500]
            results.append(
                EngineQuestionResult(
                    question_id=qid,
                    engine=engine,
                    score=score,
                    response_snippet=snippet,
                    skip_reason=None,
                )
            )
            consecutive_failures = 0
        except Exception as exc:  # In real code, distinguish RateLimitError, TimeoutError, APIError
            logger.warning("Engine %s error on question %s: %s", engine, qid, exc)
            consecutive_failures += 1
            # Treat repeated failures as engine down; null score with skip_reason
            if consecutive_failures >= 5:
                results.append(
                    EngineQuestionResult(
                        question_id=qid,
                        engine=engine,
                        score=None,
                        response_snippet=str(exc),
                        skip_reason="engine_down",
                    )
                )
                return EngineRunSummary(engine=engine, status="engine_down", results=results)
            else:
                results.append(
                    EngineQuestionResult(
                        question_id=qid,
                        engine=engine,
                        score=None,
                        response_snippet=str(exc),
                        skip_reason="api_error",
                    )
                )
                continue

    return EngineRunSummary(engine=engine, status="complete", results=results)


def compute_aggregate(results: Sequence[EngineRunSummary]) -> Tuple[float, int]:
    """
    Aggregate citation rate across engines, using only questions that have
    non-null scores from all quorum engines (Patch 2 §2.3).
    Returns (aggregate_rate_0_to_1, questions_scored).
    """
    # Collect per-engine per-question scores (only where score is not None)
    per_engine_scores: Dict[str, Dict[str, int]] = {}
    for summary in results:
        if summary.status not in ("complete", "rate_limited"):
            continue
        engine_scores: Dict[str, int] = {}
        for r in summary.results:
            if r.score is not None:
                engine_scores[r.question_id] = r.score
        per_engine_scores[summary.engine] = engine_scores

    if not per_engine_scores:
        return 0.0, 0

    # Engines that met minimum coverage: at least 15 of 20 questions scored (Patch 2 §2.3)
    engines_with_coverage = {
        e: s for e, s in per_engine_scores.items() if len(s) >= 15
    }
    engine_count = len(engines_with_coverage)
    if engine_count == 0:
        return 0.0, 0

    # Only questions that all quorum engines scored
    common_qids: Optional[set[str]] = None
    for scores in engines_with_coverage.values():
        qids = set(scores.keys())
        if common_qids is None:
            common_qids = qids
        else:
            common_qids &= qids

    if not common_qids:
        return 0.0, 0

    total_scores = 0
    total_max = 0
    for qid in common_qids:
        for scores in engines_with_coverage.values():
            total_scores += scores[qid]
            total_max += 3

    if total_max == 0:
        return 0.0, 0
    rate = total_scores / float(total_max)
    return rate, len(common_qids)


def compare_decay_last_weeks(db, category: str, current_run_date: dt.date) -> Dict[str, Any]:
    """
    Compare current aggregate citation rate to previous two weeks for decay detection.
    Returns summary stats; actual decay triggers are handled elsewhere.
    """
    cur = db.cursor()
    sql = """
        SELECT run_date, aggregate_citation_rate
        FROM ai_audit_runs
        WHERE category = %s
          AND run_date < %s
          AND status = 'completed'
        ORDER BY run_date DESC
        LIMIT 2
    """
    cur.execute(sql, (category, current_run_date))
    rows = cur.fetchall()
    cur.close()

    previous = [
        {
            "run_date": row[0],
            "aggregate_citation_rate": float(row[1]) if row[1] is not None else None,
        }
        for row in rows
    ]
    return {"previous_runs": previous}


def run_weekly_audit(db, category: str, brand_name: str) -> Dict[str, Any]:
    """
    Entry point: run weekly AI citation audit for a category.

    - Loads golden queries (ai_golden_queries).
    - Queries each engine per question.
    - Evaluates citation scores 0–3.
    - Writes ai_audit_runs and ai_audit_results rows.
    - Applies quorum and degradation rules (Patch 2 Engine Quorum Rules).
    - Returns a summary dict suitable for logging or monitoring.
    """
    today = dt.date.today()

    questions = load_golden_queries(db, category)
    if not questions:
        logger.warning("No golden queries found for category=%s", category)
        return {"status": "no_queries", "category": category, "run_date": str(today)}

    # Preload SKU metadata for all target SKUs across questions
    all_skus: List[str] = []
    for q in questions:
        for code in q.get("target_skus") or []:
            if code not in all_skus:
                all_skus.append(code)
    sku_meta = _load_sku_metadata(db, all_skus)

    run_id = str(uuid.uuid4())
    total_questions = len(questions)

    # Insert initial run row with status='running'
    cur = db.cursor()
    cur.execute(
        """
        INSERT INTO ai_audit_runs (run_id, category, run_date, status, total_questions, engines_available, quorum_met)
        VALUES (%s, %s, %s, 'running', %s, 0, 0)
        """,
        (run_id, category, today, total_questions),
    )
    db.commit()
    cur.close()

    engine_summaries: List[EngineRunSummary] = []
    for engine in ENGINES:
        summary = run_audit_for_engine(engine, questions, sku_meta, brand_name)
        engine_summaries.append(summary)

    # Quorum logic (Patch 2 §2.1)
    engines_ok = [
        s for s in engine_summaries if s.status in ("complete", "rate_limited")
    ]
    engines_responded = len(engines_ok)

    if engines_responded == 4 or engines_responded == 3:
        quorum_status = "COMPLETE"
        decay_action = "advanced"
        quorum_met = True
    elif engines_responded == 2:
        quorum_status = "PARTIAL"
        decay_action = "paused"
        quorum_met = False
    else:
        quorum_status = "FAILED"
        decay_action = "frozen"
        quorum_met = False

    agg_rate, questions_scored = compute_aggregate(engine_summaries)

    # Map rate to pass/fail using minimum_score_for_pass (>=1) and aggregate threshold 0.70
    pass_fail = "pass" if agg_rate >= 0.70 else "fail"

    # Persist per-question results
    cur = db.cursor()
    insert_sql = """
        INSERT INTO ai_audit_results
            (run_id, question_id, engine, score, response_snippet, skip_reason)
        VALUES (%s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            score = VALUES(score),
            response_snippet = VALUES(response_snippet),
            skip_reason = VALUES(skip_reason)
    """
    for summary in engine_summaries:
        for r in summary.results:
            cur.execute(
                insert_sql,
                (
                    run_id,
                    r.question_id,
                    r.engine,
                    r.score,
                    r.response_snippet,
                    r.skip_reason,
                ),
            )

    # Update run row with final status and aggregate stats
    cur.execute(
        """
        UPDATE ai_audit_runs
        SET status = %s,
            aggregate_citation_rate = %s,
            pass_fail = %s,
            engines_available = %s,
            quorum_met = %s
        WHERE run_id = %s
        """,
        (
            "completed" if engines_responded >= 2 else "failed",
            round(agg_rate, 4),
            pass_fail,
            engines_responded,
            quorum_met,
            run_id,
        ),
    )
    db.commit()
    cur.close()

    decay_summary = compare_decay_last_weeks(db, category, today)

    return {
        "status": quorum_status,
        "decay_action": decay_action,
        "category": category,
        "run_id": run_id,
        "run_date": str(today),
        "engines_responded": engines_responded,
        "aggregate_citation_rate": agg_rate,
        "questions_scored": questions_scored,
        "pass_fail": pass_fail,
        "decay_comparison": decay_summary,
    }

