import asyncio
import logging
from typing import Dict, List
from .engines import OpenAIEngine, AnthropicEngine

logger = logging.getLogger(__name__)

class AuditEngine:
    def __init__(self):
        self.engines = [OpenAIEngine(), AnthropicEngine()]
    
    async def audit_sku(self, sku_title: str, description: str) -> Dict:
        prompt = f"Analyze citation for: {sku_title}\nDescription: {description}"
        tasks = [self._query_engine(engine, prompt) for engine in self.engines]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        
        scores = {}
        successful_scores = []
        for engine, result in zip(self.engines, results):
            name = engine.__class__.__name__
            if isinstance(result, Exception):
                scores[name] = {'score': None, 'status': 'ERROR'}
            else:
                scores[name] = result
                successful_scores.append(result['score'])
        
        avg_score = sum(successful_scores) / len(successful_scores) if successful_scores else None
        return {
            'scores': scores,
            'avg_score': avg_score,
            'status': 'SUCCESS' if successful_scores else 'FAILED'
        }

    async def _query_engine(self, engine, prompt: str) -> Dict:
        try:
            return await asyncio.wait_for(engine.query(prompt), timeout=5.0)
        except asyncio.TimeoutError:
            return {'score': None, 'status': 'TIMEOUT'}
