import asyncio
import logging
import os
import re

import google.generativeai as genai

# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1


class GeminiEngine:
    def __init__(self):
        self.local_mode = False
        self._configured = False
        self._model = None
        local_llm_mode = os.getenv("LOCAL_LLM_MODE", "false").strip().lower() == "true"
        if local_llm_mode:
            logging.getLogger(__name__).warning(
                "LOCAL_LLM_MODE=true detected but AI audit Gemini engine is NOT rerouted "
                "to localhost. Citation audit always uses real external APIs. SOURCE: CLAUDE.md"
            )

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
