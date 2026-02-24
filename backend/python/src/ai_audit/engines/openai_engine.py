import os
import re
from openai import AsyncOpenAI

class OpenAIEngine:
    def __init__(self):
        self.client = AsyncOpenAI(api_key=os.getenv('OPENAI_API_KEY'))
    
    async def query(self, prompt: str) -> dict:
        response = await self.client.chat.completions.create(
            model="gpt-4-turbo-preview",
            messages=[{"role": "user", "content": prompt}],
            max_tokens=10
        )
        text = response.choices[0].message.content
        match = re.search(r'\d+', text)
        score = int(match.group()) if match else 0
        return {'score': score, 'status': 'SUCCESS'}
