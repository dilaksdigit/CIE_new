import os
# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1
class GeminiEngine:
    def __init__(self):
        self.api_key = os.getenv('GEMINI_API_KEY')
        self.model = os.environ.get('GEMINI_MODEL')
    def query(self, prompt):
        return {'score': 50, 'status': 'SUCCESS'}
