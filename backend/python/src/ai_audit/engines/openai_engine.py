# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1
import os
import re
from openai import AsyncOpenAI


class OpenAIEngine:
    def __init__(self):
        local_mode = os.environ.get("LOCAL_LLM_MODE", "").strip().lower() == "true"
        base_url = os.environ.get("LOCAL_LLM_BASE_URL", None) if local_mode else None
        api_key = os.getenv("OPENAI_API_KEY") or (
            "local-dummy-key" if local_mode else None
        )
        kwargs = {"api_key": api_key}
        if base_url:
            kwargs["base_url"] = base_url
        self.client = AsyncOpenAI(**kwargs)
    
    async def query(self, prompt: str) -> dict:
        response = await self.client.chat.completions.create(
            model=os.environ.get("OPENAI_CHAT_MODEL"),
            messages=[{"role": "user", "content": prompt}],
            max_tokens=10
        )
        text = response.choices[0].message.content
        match = re.search(r'\d+', text)
        score = int(match.group()) if match else 0
        return {'score': score, 'status': 'SUCCESS'}
