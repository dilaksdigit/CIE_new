import os
import re
import logging

import httpx

# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1


class PerplexityEngine:
    def __init__(self):
        local_llm_mode = os.getenv("LOCAL_LLM_MODE", "false").strip().lower() == "true"
        if local_llm_mode:
            logging.getLogger(__name__).warning(
                "LOCAL_LLM_MODE=true detected but AI audit Perplexity engine is NOT rerouted "
                "to localhost. Citation audit always uses real external APIs. SOURCE: CLAUDE.md"
            )

    async def query(self, prompt: str) -> dict:
        api_key = (os.environ.get("PERPLEXITY_API_KEY") or "").strip()
        model = (os.environ.get("PERPLEXITY_MODEL") or "").strip()
        if not api_key or not model:
            return {"score": None, "status": "engine_down", "skip_reason": "engine_down"}

        url = "https://api.perplexity.ai/chat/completions"
        payload = {
            "model": model,
            "messages": [{"role": "user", "content": prompt}],
            "max_tokens": 400,
        }
        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        }

        try:
            async with httpx.AsyncClient(timeout=45.0) as client:
                resp = await client.post(url, json=payload, headers=headers)
                resp.raise_for_status()
                data = resp.json()
            choices = data.get("choices") or []
            text = ""
            if choices:
                text = (choices[0].get("message") or {}).get("content") or ""

            match = re.search(r"\d+", text or "")
            score = int(match.group()) if match else 0
            return {"score": score, "status": "SUCCESS"}
        except Exception:
            return {"score": None, "status": "engine_down", "skip_reason": "engine_down"}
