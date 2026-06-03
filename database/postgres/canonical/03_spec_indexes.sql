-- =============================================================================
-- CIE v2.3.2 — Spec-native PostgreSQL indexes (idempotent)
-- =============================================================================
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6
--         CIE_v231_Developer_Build_Pack.pdf (per-table index columns)
--         CIE_v232_Hardening_Addendum.pdf (idx_retry_status)
--         CIE_v232_Semrush_CSV_Import_Spec.docx (semrush_imports indexes)
--
-- Applied on every greenfield init AFTER table migrations:
--   database/postgres/init/03_spec_indexes.sql
--
-- Do NOT rely on convert_mysql_migrations_to_pg.py alone — it strips inline KEY/INDEX.
-- =============================================================================

CREATE INDEX IF NOT EXISTS idx_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_active ON users (is_active);
CREATE INDEX IF NOT EXISTS idx_approval_status ON clusters (approval_status);
CREATE INDEX IF NOT EXISTS idx_sku_code ON skus (sku_code);
CREATE INDEX IF NOT EXISTS idx_tier ON skus (tier);
CREATE INDEX IF NOT EXISTS idx_validation_status ON skus (validation_status);
CREATE INDEX IF NOT EXISTS idx_cluster ON skus (primary_cluster_id);
CREATE INDEX IF NOT EXISTS idx_name ON intents (name);
CREATE UNIQUE INDEX IF NOT EXISTS unique_sku_intent ON sku_intents (sku_id, intent_id);
CREATE INDEX IF NOT EXISTS idx_sku ON sku_intents (sku_id);
CREATE INDEX IF NOT EXISTS idx_intent ON sku_intents (intent_id);
CREATE INDEX IF NOT EXISTS idx_sku_date ON audit_results (sku_id, queried_at);
CREATE INDEX IF NOT EXISTS idx_engine ON audit_results (engine_type);
CREATE INDEX IF NOT EXISTS content_briefs_idx_sku ON content_briefs (sku_id);
CREATE INDEX IF NOT EXISTS idx_status ON content_briefs (status);
CREATE INDEX IF NOT EXISTS idx_assigned ON content_briefs (assigned_to);
CREATE INDEX IF NOT EXISTS idx_deadline ON content_briefs (deadline);
CREATE INDEX IF NOT EXISTS idx_sku_gate ON validation_logs (sku_id, gate_type);
CREATE INDEX IF NOT EXISTS idx_validated_at ON validation_logs (validated_at);
CREATE INDEX IF NOT EXISTS tier_history_idx_sku ON tier_history (sku_id);
CREATE INDEX IF NOT EXISTS idx_changed_at ON tier_history (changed_at);
CREATE INDEX IF NOT EXISTS idx_entity ON audit_log (entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_user ON audit_log (user_id);
CREATE INDEX IF NOT EXISTS idx_created_at ON audit_log (created_at);
CREATE INDEX IF NOT EXISTS idx_user_date ON staff_effort_logs (user_id, logged_at);
CREATE INDEX IF NOT EXISTS idx_tier_date ON staff_effort_logs (tier, logged_at);
CREATE INDEX IF NOT EXISTS staff_effort_logs_idx_sku ON staff_effort_logs (sku_id);
CREATE INDEX IF NOT EXISTS approval_requests_idx_status ON approval_requests (status);
CREATE INDEX IF NOT EXISTS approval_requests_idx_entity ON approval_requests (entity_type, entity_id);
CREATE UNIQUE INDEX IF NOT EXISTS unique_week ON executive_kpis (week_number, year);
CREATE INDEX IF NOT EXISTS idx_cluster_category ON clusters (category);
CREATE UNIQUE INDEX IF NOT EXISTS unique_run_question_engine ON ai_audit_results (run_id, question_id, engine);
CREATE INDEX IF NOT EXISTS cluster_master_idx_cluster_category ON cluster_master (category);
CREATE INDEX IF NOT EXISTS idx_sku_master_cluster ON sku_master (cluster_id);
CREATE INDEX IF NOT EXISTS idx_sku_master_tier ON sku_master (tier);
CREATE INDEX IF NOT EXISTS idx_sku_master_status ON sku_master (status);
CREATE INDEX IF NOT EXISTS idx_sku_master_decay_status ON sku_master (decay_status);
CREATE INDEX IF NOT EXISTS idx_sku_master_commercial_score ON sku_master (commercial_score);
CREATE UNIQUE INDEX IF NOT EXISTS uq_ssi_sku_intent ON sku_secondary_intents (sku_id, intent_id);
CREATE INDEX IF NOT EXISTS idx_ssi_sku ON sku_secondary_intents (sku_id);
CREATE INDEX IF NOT EXISTS idx_ssi_intent ON sku_secondary_intents (intent_id);
CREATE INDEX IF NOT EXISTS idx_sku_content_sku ON sku_content (sku_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_gate_status_sku_code ON sku_gate_status (sku_id, gate_code);
CREATE INDEX IF NOT EXISTS idx_gate_status_status ON sku_gate_status (status);
CREATE INDEX IF NOT EXISTS idx_gate_status_checked_at ON sku_gate_status (checked_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_channel_readiness_sku_channel ON channel_readiness (sku_id, channel);
CREATE INDEX IF NOT EXISTS idx_channel_readiness_score ON channel_readiness (score);
CREATE INDEX IF NOT EXISTS idx_channel_readiness_computed_at ON channel_readiness (computed_at);
CREATE INDEX IF NOT EXISTS idx_sku_tier_history_sku ON sku_tier_history (sku_id);
CREATE INDEX IF NOT EXISTS idx_sku_tier_history_changed_at ON sku_tier_history (changed_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_tier_intent ON tier_intent_rules (tier, intent_id);
CREATE INDEX IF NOT EXISTS idx_tier_intent_tier ON tier_intent_rules (tier);
CREATE INDEX IF NOT EXISTS idx_tier_intent_intent ON tier_intent_rules (intent_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_actor ON audit_log (actor_id, changed_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_entity_canonical ON audit_log (entity_type, entity_id, changed_at);
CREATE INDEX IF NOT EXISTS idx_faq_templates_product_class ON faq_templates (product_class);
CREATE INDEX IF NOT EXISTS idx_faq_templates_display_order ON faq_templates (display_order);
CREATE INDEX IF NOT EXISTS idx_sku_faqs_sku ON sku_faqs (sku_id);
CREATE INDEX IF NOT EXISTS idx_sku_faqs_template ON sku_faqs (template_id);
CREATE INDEX IF NOT EXISTS idx_cluster_review_cluster ON cluster_review_log (cluster_id);
CREATE INDEX IF NOT EXISTS idx_cluster_review_date ON cluster_review_log (review_date);
CREATE INDEX IF NOT EXISTS idx_ai_audit_results_cited_sku_business_id ON ai_audit_results (cited_sku_business_id);
CREATE INDEX IF NOT EXISTS idx_retry_status ON vector_retry_queue (status, next_retry_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_actor_canonical ON audit_log (actor_id, created_at);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_week_start ON weekly_scores (week_start);
CREATE INDEX IF NOT EXISTS idx_bra_rule_key ON business_rules_audit (rule_key);
CREATE INDEX IF NOT EXISTS idx_bra_changed_at ON business_rules_audit (changed_at);
CREATE INDEX IF NOT EXISTS idx_batch ON semrush_imports (import_batch);
CREATE INDEX IF NOT EXISTS idx_keyword ON semrush_imports (keyword(100);
CREATE INDEX IF NOT EXISTS idx_batch_keyword ON semrush_imports (import_batch, keyword(100);
CREATE INDEX IF NOT EXISTS idx_batch_id ON semrush_imports (import_batch_id);
CREATE INDEX IF NOT EXISTS idx_gsc_baselines_sku ON gsc_baselines (sku_id);
CREATE INDEX IF NOT EXISTS idx_url_performance_window ON url_performance (window_end);
CREATE INDEX IF NOT EXISTS idx_url_performance_url ON url_performance (url(100);
CREATE INDEX IF NOT EXISTS idx_ga4_landing_window ON ga4_landing_performance (window_end);
CREATE INDEX IF NOT EXISTS idx_ga4_landing_page ON ga4_landing_performance (landing_page(100);
CREATE INDEX IF NOT EXISTS idx_gsc_weekly_window ON gsc_weekly_performance (window_end);
CREATE INDEX IF NOT EXISTS idx_gsc_weekly_url ON gsc_weekly_performance (url(100);
CREATE INDEX IF NOT EXISTS idx_gsc_unmatched_window ON gsc_unmatched_urls (window_end);
CREATE INDEX IF NOT EXISTS idx_gsc_url ON gsc_data (url(255);
CREATE INDEX IF NOT EXISTS idx_gsc_date ON gsc_data (data_date);
CREATE UNIQUE INDEX IF NOT EXISTS uq_gsc_url_date ON gsc_data (url(255);
CREATE INDEX IF NOT EXISTS idx_gbs_sku ON gsc_baselines (sku_id);
CREATE INDEX IF NOT EXISTS idx_gbs_status ON gsc_baselines (measurement_status);
CREATE INDEX IF NOT EXISTS idx_sth_sku ON sku_tier_history (sku_id);
CREATE INDEX IF NOT EXISTS idx_sth_changed_at ON sku_tier_history (changed_at);
CREATE INDEX IF NOT EXISTS idx_tier_change_sku ON tier_change_requests (sku_id);
CREATE INDEX IF NOT EXISTS idx_tier_change_status ON tier_change_requests (status);
CREATE INDEX IF NOT EXISTS idx_skus_shopify_product_id ON skus (shopify_product_id);
CREATE INDEX IF NOT EXISTS idx_faq_templates_cluster_intent ON faq_templates (cluster_id, intent_key);
CREATE INDEX IF NOT EXISTS idx_semrush_snapshots_sku ON semrush_content_snapshots (sku_id);
CREATE INDEX IF NOT EXISTS idx_semrush_snapshots_date ON semrush_content_snapshots (snapshot_date);
CREATE INDEX IF NOT EXISTS idx_sku_master_shopify_url ON sku_master (shopify_url(255);
CREATE INDEX IF NOT EXISTS idx_sku_function ON ai_agent_logs (sku_id, function_called);
CREATE UNIQUE INDEX IF NOT EXISTS uq_sync_status_service ON sync_status (service);
CREATE UNIQUE INDEX IF NOT EXISTS uq_sku_master_sku_code ON sku_master (sku_code);
CREATE INDEX IF NOT EXISTS idx_notifications_notifiable ON notifications (notifiable_type, notifiable_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read_at ON notifications (read_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cis_measurements_sku_day ON cis_measurements (sku_id, measurement_day);
CREATE INDEX IF NOT EXISTS idx_cis_measurements_measured_at ON cis_measurements (measured_at);
CREATE INDEX IF NOT EXISTS idx_tier_assignments_changed_at ON tier_assignments (changed_at);
CREATE INDEX IF NOT EXISTS idx_tier_assignments_sku ON tier_assignments (sku_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_sku_cluster_map ON sku_cluster_map (sku_id, cluster_id);
CREATE INDEX IF NOT EXISTS idx_sku_cluster_map_cluster ON sku_cluster_map (cluster_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log ("action");
-- Supplemental indexes (spec / FK helpers)
CREATE INDEX IF NOT EXISTS idx_channel_mappings_sku ON channel_mappings (sku_id);
CREATE INDEX IF NOT EXISTS idx_sku_vectors_sku ON sku_vectors (sku_id);
CREATE INDEX IF NOT EXISTS idx_cluster_vectors_cluster ON cluster_vectors (cluster_id);

-- -----------------------------------------------------------------------------
-- Conditional indexes (column may be added in later migrations)
-- -----------------------------------------------------------------------------
DO $cie$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'audit_log' AND column_name = 'timestamp'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_audit_log_timestamp_col ON audit_log ("timestamp");
    CREATE INDEX IF NOT EXISTS idx_audit_time ON audit_log ("timestamp");
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'audit_log' AND column_name = 'actor_id'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_audit_log_actor ON audit_log (actor_id, created_at);
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'audit_log' AND column_name = 'changed_at'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_audit_log_actor_changed ON audit_log (actor_id, changed_at);
    CREATE INDEX IF NOT EXISTS idx_audit_log_entity_changed ON audit_log (entity_type, entity_id, changed_at);
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'skus' AND column_name = 'shopify_product_id'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_skus_shopify_product_id ON skus (shopify_product_id);
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'sku_master' AND column_name = 'shopify_url'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_sku_master_shopify_url ON sku_master (shopify_url);
  END IF;
END
$cie$;
