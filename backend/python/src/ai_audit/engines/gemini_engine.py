import asyncio
import os
import re
from typing import Optional

import google.generativeai as genai
from openai import AsyncOpenAI

# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1


class GeminiEngine:
    def __init__(self):
        self.local_mode = (
            os.environ.get("LOCAL_LLM_MODE", "").strip().lower() == "true"
        )
        self._configured = False
        self._model = None
        self._local_client: Optional[AsyncOpenAI] = None
        self._local_model_name: Optional[str] = None

        if self.local_mode:
            base_url = os.environ.get("LOCAL_LLM_BASE_URL", "http://localhost:1234/v1")
            api_key = (os.environ.get("GEMINI_API_KEY") or "local-dummy-key").strip()
            self._local_model_name = (os.environ.get("GEMINI_MODEL") or "").strip() or (
                "Qwen3-Next-dummy-Instruct-dummy"
            )
            self._local_client = AsyncOpenAI(base_url=base_url, api_key=api_key)
            self._configured = True
        else:
            api_key = (os.environ.get("GEMINI_API_KEY") or "").strip()
            model = (os.environ.get("GEMINI_MODEL") or "").strip()
            if api_key and model:
                try:
                    genai.configure(api_key=api_key)
                    self._model = genai.GenerativeModel(model)
                    self._configured = True
                except Exception:
                    self._configured = False
                    self._model = None

    async def query(self, prompt: str) -> dict:
        if not self._configured:
            return {"score": None, "status": "engine_down", "skip_reason": "engine_down"}

        if self.local_mode:
            assert self._local_client is not None
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

        def _run() -> str:
            response = self._model.generate_content(prompt)
            return (response.text or "")[:2000]

        try:
            text = await asyncio.to_thread(_run)
            match = re.search(r"\d+", text or "")
            score = int(match.group()) if match else 0
            return {"score": score, "status": "SUCCESS"}
        except Exception:
            return {"score": None, "status": "engine_down", "skip_reason": "engine_down"}
