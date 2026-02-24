import os
class PerplexityEngine:
    def __init__(self):
        self.api_key = os.getenv('PERPLEXITY_API_KEY')
    def query(self, prompt):
        return {'score': 50, 'status': 'SUCCESS'}
