import os


class Config:
    REDIS_URL = os.getenv('REDIS_URL')

    # Cron expressions (UTC) for weekly jobs — read by scheduler infrastructure.
    # Defaults follow CIE_Master_Developer_Build_Spec.docx §9.2 and §10.2.
    #
    # Sunday 03:00 UTC — GSC weekly pull (url_performance).
    GSC_CRON_SCHEDULE = os.getenv('SYNC_GSC_CRON_SCHEDULE', '0 3 * * 0')
    # Monday 03:00 UTC — GA4 weekly pull (ga4_landing_performance), 24h after GSC.
    GA4_CRON_SCHEDULE = os.getenv('SYNC_GA4_CRON_SCHEDULE', '0 3 * * 1')

