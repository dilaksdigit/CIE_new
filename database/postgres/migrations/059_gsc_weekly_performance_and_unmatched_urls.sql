-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Check 9.1
-- gsc_weekly_performance: store GSC weekly pull results (impressions, clicks, CTR, position per URL).
-- gsc_unmatched_urls: log unmatched URLs (trailing slashes, UTM params) without erroring.

CREATE TABLE IF NOT EXISTS gsc_weekly_performance (
  id                INT           NOT NULL ,
  url               VARCHAR(1000) NOT NULL,
  window_end        DATE          NOT NULL,
  impressions       DECIMAL(10,2) NULL,
  clicks            DECIMAL(10,2) NULL,
  ctr               DECIMAL(6,4)  NULL,
  avg_position      DECIMAL(6,2)  NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),

  INDEX idx_gsc_weekly_url (url(100))
);

CREATE TABLE IF NOT EXISTS gsc_unmatched_urls (
  id         INT           NOT NULL ,
  url        VARCHAR(1000) NOT NULL,
  window_end DATE          NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
