import os


class Config:
    REDIS_URL = os.getenv('REDIS_URL')

    # GSC / GA4 integration (env: GSC_PROPERTY, GA4_PROPERTY_ID, GOOGLE_SERVICE_ACCOUNT_JSON)
    GSC_PROPERTY = os.getenv('GSC_PROPERTY', '')
    GA4_PROPERTY_ID = os.getenv('GA4_PROPERTY_ID', '')
    GOOGLE_SERVICE_ACCOUNT_JSON = os.getenv('GOOGLE_SERVICE_ACCOUNT_JSON', '')
    # Base URL to build landing page URL from sku_code (e.g. https://www.example.com/products)
    CIE_LANDING_BASE_URL = os.getenv('CIE_LANDING_BASE_URL', '').rstrip('/')

    # Cron expressions (UTC) for weekly jobs — read by scheduler infrastructure.
    # Defaults follow CIE_Master_Developer_Build_Spec.docx §9.1 (GSC Monday 02:00), §10.2 (GA4 Monday 03:00).
    #
    # Monday 02:00 UTC — GSC weekly pull (gsc_weekly_performance).
    GSC_CRON_SCHEDULE = os.getenv('SYNC_GSC_CRON_SCHEDULE', '0 2 * * 1')
    # Monday 03:00 UTC — GA4 weekly pull (ga4_landing_performance), 24h after GSC.
    GA4_CRON_SCHEDULE = os.getenv('SYNC_GA4_CRON_SCHEDULE', '0 3 * * 1')

