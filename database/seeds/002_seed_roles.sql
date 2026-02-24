INSERT INTO roles (id, name, display_name, description) VALUES
  (UUID(), 'CONTENT_EDITOR', 'Content Editor', 'Creates/edits titles, descriptions, answer blocks, best-for/not-for within assigned SKUs'),
  (UUID(), 'PRODUCT_SPECIALIST', 'Product Specialist', 'Adds expert authority blocks, safety certs, compliance data'),
  (UUID(), 'SEO_GOVERNOR', 'SEO Governor', 'Manages cluster master list and intent taxonomy'),
  (UUID(), 'CHANNEL_MANAGER', 'Channel Manager', 'Manages channel-specific content, feed optimisation, readiness review'),
  (UUID(), 'AI_OPS', 'AI Operations', 'Runs AI audits, manages golden queries, reviews decay alerts'),
  (UUID(), 'PORTFOLIO_HOLDER', 'Portfolio Holder', 'Reviews tier assignments, owns category P&L'),
  (UUID(), 'FINANCE', 'Finance', 'Provides ERP data, validates tier calculations, co-approves overrides'),
  (UUID(), 'ADMIN', 'Administrator', 'System configuration, user management, audit review (no gate bypass)'),
  (UUID(), 'SYSTEM', 'System', 'Automated processes (ERP sync, audits, decay) with no direct content edit rights');
