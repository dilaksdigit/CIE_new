# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1
import os
import re
from openai import AsyncOpenAI

class OpenAIEngine:
    def __init__(self):
        self.client = AsyncOpenAI(api_key=os.getenv('OPENAI_API_KEY'))
    
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
