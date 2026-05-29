import os
import re
from typing import Optional

import httpx
from openai import AsyncOpenAI

# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1


class PerplexityEngine:
    def __init__(self):
        self.local_mode = (
            os.environ.get("LOCAL_LLM_MODE", "").strip().lower() == "true"
        )
        self._local_client: Optional[AsyncOpenAI] = None
        self._local_model_name: Optional[str] = None
        if self.local_mode:
            base_url = os.environ.get("LOCAL_LLM_BASE_URL", "http://localhost:1234/v1")
            api_key = (os.environ.get("PERPLEXITY_API_KEY") or "local-dummy-key").strip()
            self._local_model_name = (os.environ.get("PERPLEXITY_MODEL") or "").strip() or (
                "Qwen3-Next-dummy-Instruct-dummy"
            )
            self._local_client = AsyncOpenAI(base_url=base_url, api_key=api_key)

    async def query(self, prompt: str) -> dict:
        if self.local_mode:
            if not self._local_client:
                return {
                    "score": None,
                    "status": "engine_down",
                    "skip_reason": "engine_down",
                }
            try:
                response = await self._local_client.chat.completions.create(
                    model=self._local_model_name or "Qwen3-Next-dummy-Instruct-dummy",
                    messages=[{"role": "user", "content": prompt}],
                    max_tokens=1000,
                )
                text = (response.choices[0].message.content or "")[:2000]
                match = re.search(r"\d+", text or "")
                score = int(match.group()) if match else 0
                return {"score": score, "status": "SUCCESS"}
            except Exception:
                return {
                    "score": None,
                    "status": "engine_down",
                    "skip_reason": "engine_down",
                }

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
