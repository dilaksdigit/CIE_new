-- CIE v2.3.2 — Business Rules seed (Section 5.3 of CIE_Master_Developer_Build_Spec.docx).
-- Exactly 52 rules. Phase 0 checklist: SELECT COUNT(*) = 52 FROM business_rules.
-- All former "extra" keys are now hard-coded or aliased in application code.
-- SOURCE: MASTER§5.3 C2 — spec-defined gate rules: gates.answer_block_min_chars, gates.answer_block_max_chars,
--   gates.best_for_min_entries, gates.not_for_min_entries, gates.vector_similarity_min.
-- Implementation-specific additions (not in C2, used by G6/VEC): gates.description_word_count_min, gates.description_min_chars.

INSERT INTO business_rules (id, rule_key, value, value_type, description) VALUES
-- tier — Tier Assignment Engine (8)
(UUID(), 'tier.hero_percentile_threshold', '0.80', 'float', 'Top 20% of Commercial Priority Scores = Hero'),
(UUID(), 'tier.support_percentile_threshold', '0.30', 'float', 'Above this & below Hero = Support'),
(UUID(), 'tier.harvest_percentile_threshold', '0.10', 'float', 'Above this & below Support = Harvest; below = Kill'),
(UUID(), 'tier.margin_weight', '0.40', 'float', 'Contribution Margin % weight in Priority Score'),
(UUID(), 'tier.cppc_weight', '0.25', 'float', 'CPPC weighting in Priority Score'),
(UUID(), 'tier.velocity_weight', '0.20', 'float', '90-day velocity weighting'),
(UUID(), 'tier.returns_weight', '0.15', 'float', 'Return rate weighting'),
(UUID(), 'tier.manual_override_expiry_days', '90', 'integer', 'Days before manual tier override auto-expires'),
-- chs — Content Health Score (7)
(UUID(), 'chs.intent_alignment_weight', '0.25', 'float', 'CHS weight: conversion / intent match signal'),
(UUID(), 'chs.semantic_coverage_weight', '0.20', 'float', 'CHS weight: topic cluster coverage'),
(UUID(), 'chs.technical_hygiene_weight', '0.20', 'float', 'CHS weight: G1–G7 gate pass rate'),
(UUID(), 'chs.competitive_gap_weight', '0.20', 'float', 'CHS weight: GSC avg_position signal'),
(UUID(), 'chs.ai_readiness_weight', '0.15', 'float', 'CHS weight: per-channel readiness score'),
(UUID(), 'chs.green_threshold', '70', 'integer', 'CHS ≥ this = Green on dashboard'),
(UUID(), 'chs.amber_threshold', '40', 'integer', 'CHS ≥ this but below green = Amber; below = Red'),
-- cis — Change Impact Score (9)
(UUID(), 'cis.position_improvement_small_pts', '20', 'integer', 'CIS points for 1–4 spot position improvement'),
(UUID(), 'cis.position_improvement_large_pts', '40', 'integer', 'CIS points for 5+ spot improvement'),
(UUID(), 'cis.position_improvement_large_min', '5', 'integer', 'Minimum spots to qualify for large band'),
(UUID(), 'cis.ctr_improvement_pts', '20', 'integer', 'CIS points if CTR improves at D+30'),
(UUID(), 'cis.impressions_improvement_pts', '15', 'integer', 'CIS points if impressions increase at D+30'),
(UUID(), 'cis.conversion_rate_improvement_pts', '25', 'integer', 'CIS points if organic conversion rate improves'),
(UUID(), 'cis.success_threshold', '50', 'integer', 'CIS ≥ this = Successful change'),
(UUID(), 'cis.measurement_window_d15', '15', 'integer', 'Day-15 measurement trigger after publish'),
(UUID(), 'cis.measurement_window_d30', '30', 'integer', 'Day-30 final CIS computation trigger'),
-- decay — Citation Decay & Refresh Briefs (8)
(UUID(), 'decay.yellow_flag_weeks', '1', 'integer', 'Consecutive zero-score weeks before yellow_flag status'),
(UUID(), 'decay.alert_weeks', '2', 'integer', 'Consecutive zeros before Content Writer alert'),
(UUID(), 'decay.auto_brief_weeks', '3', 'integer', 'Consecutive zeros before AI auto-generates refresh brief'),
(UUID(), 'decay.escalate_weeks', '4', 'integer', 'Weeks after brief with no recovery before escalation'),
(UUID(), 'decay.auto_brief_deadline_days', '7', 'integer', 'Days Content Writer has to complete a refresh brief'),
(UUID(), 'decay.hero_citation_target', '0.70', 'float', 'Target AI citation rate for Hero SKUs (70% ≥ score 1)'),
(UUID(), 'decay.hero_citation_danger', '0.50', 'float', 'Citation rate below this triggers immediate escalation'),
(UUID(), 'decay.audit_question_count', '20', 'integer', 'Golden queries per category per weekly audit'),
(UUID(), 'decay.quorum_minimum', '3', 'integer', 'Min AI engines that must respond for audit result to count'),
-- effort — Content Effort Allocation (3)
(UUID(), 'effort.hero_allocation_target', '0.60', 'float', 'Target % of content hours on Hero SKUs'),
(UUID(), 'effort.hero_allocation_danger', '0.55', 'float', 'Below this auto-flags to KPI Conductor dashboard'),
(UUID(), 'effort.support_max_hours_per_quarter', '2', 'integer', 'Max effort per Support SKU per quarter'),
-- readiness — Channel Readiness Scores (6)
(UUID(), 'readiness.hero_primary_channel_min', '85', 'integer', 'Hero must reach this on primary channel within 30 days'),
(UUID(), 'readiness.hero_all_channels_min', '70', 'integer', 'Hero must reach this on all active channels'),
(UUID(), 'readiness.support_primary_channel_min', '60', 'integer', 'Support minimum readiness on primary channel'),
(UUID(), 'readiness.gold_threshold', '90', 'integer', 'Readiness avg ≥ this = Gold maturity'),
(UUID(), 'readiness.silver_threshold', '65', 'integer', 'Readiness avg ≥ this = Silver maturity'),
(UUID(), 'readiness.deadline_days_after_completion', '30', 'integer', 'Days after content completion before readiness minimum enforced'),
-- gates — Publish Gate Validation (9)
(UUID(), 'gates.answer_block_min_chars', '250', 'integer', 'Minimum AI Answer Block character count (G4)'),
(UUID(), 'gates.answer_block_max_chars', '300', 'integer', 'Maximum AI Answer Block character count (G4)'),
(UUID(), 'gates.best_for_min_entries', '2', 'integer', 'Minimum Best-For entries for Hero/Support (G5)'),
(UUID(), 'gates.not_for_min_entries', '1', 'integer', 'Minimum Not-For entries (G5)'),
(UUID(), 'gates.secondary_intent_max', '3', 'integer', 'Maximum secondary intents per SKU (G3)'),
(UUID(), 'gates.vector_similarity_min', '0.72', 'float', 'Minimum cosine similarity for vector validation to pass'),
(UUID(), 'gates.meta_title_max_chars', '65', 'integer', 'Maximum meta title length'),
(UUID(), 'gates.meta_description_max_chars', '160', 'integer', 'Maximum meta description length'),
(UUID(), 'gates.meta_description_min_chars', '120', 'integer', 'Minimum meta description length'),
-- SOURCE: MASTER§5.3 — add description word count min for G6
(UUID(), 'gates.description_word_count_min', '50', 'integer', 'Minimum word count for product description (G6)'),
(UUID(), 'gates.description_min_chars', '100', 'integer', 'Minimum character count for description before vector check'),
-- sync — Cron & Data Sync Schedules (5)
(UUID(), 'sync.gsc_cron_schedule', '0 3 * * 0', 'string', 'GSC weekly pull — Sunday 03:00 UTC'),
(UUID(), 'sync.ga4_cron_schedule', '0 3 * * 1', 'string', 'GA4 weekly pull — Monday 03:00 UTC'),
(UUID(), 'sync.erp_cron_schedule', '0 2 1 * *', 'string', 'ERP monthly sync — 1st of month 02:00 UTC'),
(UUID(), 'sync.ai_audit_cron_schedule', '0 9 * * 1', 'string', 'AI audit run — Monday 09:00 UTC'),
(UUID(), 'sync.baseline_lookback_weeks', '2', 'integer', 'Weeks to average for 14-day baseline calculations')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
