import os


class Config:
    REDIS_URL = os.getenv('REDIS_URL')

    # GSC / GA4 integration (env: GSC_PROPERTY, GA4_PROPERTY_ID, GOOGLE_SERVICE_ACCOUNT_JSON)
    GSC_PROPERTY = os.getenv('GSC_PROPERTY', '')
    GA4_PROPERTY_ID = os.getenv('GA4_PROPERTY_ID', '')
    GOOGLE_SERVICE_ACCOUNT_JSON = os.getenv('GOOGLE_SERVICE_ACCOUNT_JSON', '')
    # Base URL to build landing page URL from sku_code (e.g. https://www.example.com/products)
    CIE_LANDING_BASE_URL = os.getenv('CIE_LANDING_BASE_URL', '').rstrip('/')

    # Cron expressions (UTC) for weekly jobs — read by scheduler / host (env mirrors business_rules seeds).
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — sync.gsc_cron_schedule, sync.ga4_cron_schedule
    # FIX: GSC-02a — GSC default Sunday 03:00 UTC (was Monday 02:00).
    GSC_CRON_SCHEDULE = os.getenv('SYNC_GSC_CRON_SCHEDULE', '0 3 * * 0')
    # Monday 03:00 UTC — GA4 weekly pull (ga4_landing_performance).
    GA4_CRON_SCHEDULE = os.getenv('SYNC_GA4_CRON_SCHEDULE', '0 3 * * 1')

