import logging
from datetime import datetime, timedelta

from api.gates_validate import BusinessRules


class BriefGenerator:
    def __init__(self, db_connection):
        self.db = db_connection
    
    def generate_decay_brief(self, sku_id, sku_code, title):
        deadline_days = int(BusinessRules.get("decay.auto_brief_deadline_days"))
        deadline = datetime.now() + timedelta(days=deadline_days)
        brief_data = {
            'sku_id': sku_id,
            'title': f'Content Refresh: {title}',
            'status': 'OPEN',
            'deadline': deadline.strftime('%Y-%m-%d')
        }
        # Insertion logic would go here
        return brief_data
