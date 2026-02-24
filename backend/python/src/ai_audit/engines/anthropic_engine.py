import os
import re
from anthropic import AsyncAnthropic

class AnthropicEngine:
    def __init__(self):
        self.client = AsyncAnthropic(api_key=os.getenv('ANTHROPIC_API_KEY'))
    
    async def query(self, prompt: str) -> dict:
        message = await self.client.messages.create(
            model="claude-3-5-sonnet-20241022",
            max_tokens=10,
            messages=[{"role": "user", "content": prompt}]
        )
        text = message.content[0].text
        match = re.search(r'\d+', text)
        score = int(match.group()) if match else 0
        return {'score': score, 'status': 'SUCCESS'}
