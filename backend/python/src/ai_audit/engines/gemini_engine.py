import os
class GeminiEngine:
    def __init__(self):
        self.api_key = os.getenv('GEMINI_API_KEY')
    def query(self, prompt):
        return {'score': 50, 'status': 'SUCCESS'}
