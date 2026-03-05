import asyncio
import logging
from typing import Dict, List

from .engines.openai_engine import OpenAIEngine
from .engines.gemini_engine import GeminiEngine
from .engines.perplexity_engine import PerplexityEngine

# SOURCE: CIE_Master_Developer_Build_Spec.docx Layer L7 / CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 10.2 / CIE_v232_Hardening_Addendum.pdf Patch 2

logger = logging.getLogger(__name__)


class GoogleSGEEngine:
    """
    Google SGE: no API available — stub per CIE_v232_FINAL_Developer_Instruction.docx Stage 0.
    Always returns engine_down so quorum logic can treat this engine as unavailable.
    """

    async def query(self, prompt: str) -> Dict:
        return {"score": None, "status": "engine_down", "skip_reason": "engine_down"}


# Engine instances and identifiers in canonical order
ENGINES = [OpenAIEngine(), GeminiEngine(), PerplexityEngine(), GoogleSGEEngine()]
ENGINE_IDS = ["chatgpt", "gemini", "perplexity", "google_sge"]


class AuditEngine:
    def __init__(self):
        self.engines = ENGINES
        self.engine_ids = ENGINE_IDS

    async def audit_sku(self, sku_title: str, description: str) -> Dict:
        """
        Run AI citation audit across all engines for a single SKU.

        Applies quorum logic from Hardening Addendum Patch 2 §2.1:
          - 4/4 or 3/4 engines respond → run_status = 'complete'
          - 2/4 engines respond       → run_status = 'partial'
          - ≤1/4 engines respond      → run_status = 'failed'
        """
        prompt = f"Analyze citation for: {sku_title}\nDescription: {description}"

        tasks = [
            self._query_engine(engine, engine_id, prompt)
            for engine, engine_id in zip(self.engines, self.engine_ids)
        ]
        results = await asyncio.gather(*tasks, return_exceptions=False)

        scores: Dict[str, Dict] = {}
        successful_scores: List[int] = []
        responders = 0


        for engine_id, result in zip(self.engine_ids, results):
            # SOURCE: CLAUDE.md §18 (AI audit scoring scale 0-3 locked); CIE_v231_Developer_Build_Pack.pdf (ai_audit_results schema)
            score = result.get("score")
            # Bounds enforcement for 0–3 scale
            if score is None:
                # Engine unavailable — store with is_available=False, do not count in average
                scores[engine_id] = {**result, "score": None, "is_available": False}
                continue
            if not isinstance(score, (int, float)) or not (0 <= score <= 3):
                logger.error(f"Engine {engine_id} returned invalid score: {score}")
                scores[engine_id] = {**result, "score": None, "is_available": False}
                continue
            scores[engine_id] = {**result, "score": int(score), "is_available": True}
            responders += 1
            successful_scores.append(int(score))

        avg_score = (
            sum(successful_scores) / len(successful_scores) if successful_scores else None
        )

        # Quorum-based run_status
        if responders >= 3:
            run_status = "complete"
        elif responders == 2:
            run_status = "partial"
        else:
            run_status = "failed"

        # Backwards-compatible overall status
        overall_status = "SUCCESS" if responders > 0 else "FAILED"

        return {
            "scores": scores,
            "avg_score": avg_score,
            "engines_responded": responders,
            "total_engines": len(self.engines),
            "run_status": run_status,
            "status": overall_status,
        }

    async def _query_engine(self, engine, engine_id: str, prompt: str) -> Dict:
        """
        Per-engine failure handling (Patch 2 §2.2):
          - RateLimitError → exponential backoff, max 3 retries
          - TimeoutError / APIError → up to 5 consecutive failures before engine_down
        """
        max_rate_limit_retries = 3
        max_failures = 5
        delay = 1.0

        rate_limit_retries = 0
        failures = 0

        while failures < max_failures:
            try:
                # 10s upper bound; engines should respect their own internal timeouts as well
                raw = await asyncio.wait_for(engine.query(prompt), timeout=10.0)
                score = raw.get("score")
                status = raw.get("status", "SUCCESS")
                skip_reason = raw.get("skip_reason")

                return {
                    "score": score,
                    "status": status,
                    "skip_reason": skip_reason,
                }
            except Exception as exc:  # In real code, distinguish specific API exceptions
                name = type(exc).__name__
                logger.warning("Engine %s error: %s", engine_id, exc)

                # Simulated RateLimitError handling
                if name == "RateLimitError" and rate_limit_retries < max_rate_limit_retries:
                    rate_limit_retries += 1
                    await asyncio.sleep(delay)
                    delay *= 2
                    continue

                failures += 1

                # Simulated Timeout/API error handling
                if name in ("TimeoutError", "APIError", "ReadTimeout") and failures < max_failures:
                    await asyncio.sleep(delay)
                    continue

                # Mark engine as down after exceeding failure limits
                logger.warning(
                    "Engine %s marked engine_down after %d failures", engine_id, failures
                )
                return {
                    "score": None,
                    "status": "engine_down",
                    "skip_reason": "engine_down",
                }

        return {
            "score": None,
            "status": "engine_down",
            "skip_reason": "engine_down",
        }
