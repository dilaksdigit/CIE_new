import os
from typing import List, Dict

class ODBCConnector:
    def __init__(self):
        self.connection_string = os.getenv('ERP_CONNECTION_STRING')
    
    def fetch_sku_data(self) -> List[Dict]:
        # Connect and query logic
        return []
