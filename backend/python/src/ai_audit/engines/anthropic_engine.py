# SOURCE: CIE_Master_Developer_Build_Spec.docx §4.2
# FIX: AI-04 — Use canonical ai_agent_call (sync) from thread pool for async engine API

import asyncio
import re

from src.utils.ai_agent import ai_agent_call
from src.utils.prompts import build_standard_system_prompt


class AnthropicEngine:
    def __init__(self):
        pass

    async def query(self, prompt: str) -> dict:
        def _run() -> str:
            return ai_agent_call(
                build_standard_system_prompt(),
                prompt,
                max_tokens=1000,
                sku_id=None,
                function_name="anthropic_engine_query",
            )

        text = await asyncio.to_thread(_run)
        match = re.search(r"\d+", text)
        score = int(match.group()) if match else 0
        return {"score": score, "status": "SUCCESS"}
