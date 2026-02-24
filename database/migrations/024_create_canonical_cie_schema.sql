-- ===================================================================
-- CIE v2.3.1 – Canonical Schema (MySQL implementation)
-- ===================================================================
-- This migration adds the v2 canonical tables alongside the existing
-- v1 tables. It is additive-only and does NOT drop or alter the
-- current production tables.
--
-- Key rules:
-- - UUIDs (as CHAR(36) via UUID()) for all primary keys
-- - created_at/updated_at on every table
-- - New canonical tables use the names from the spec
--   (cluster_master, sku_master, intent_taxonomy, etc.)
-- ===================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. cluster_master
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cluster_master (
    id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    cluster_id       VARCHAR(50) NOT NULL UNIQUE,
    category         ENUM('cables','lampshades','bulbs','pendants',
                          'floor_lamps','ceiling_lights','accessories') NOT NULL,
    intent_statement VARCHAR(500) NOT NULL,
    intent_vector    JSON NOT NULL,
    is_active        BOOLEAN NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cluster_category (category)
);

-- -------------------------------------------------------------------
-- 2. intent_taxonomy (exactly 9 locked rows; see seeds)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS intent_taxonomy (
    id           CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    intent_id    SMALLINT NOT NULL UNIQUE,
    intent_key   VARCHAR(30) NOT NULL UNIQUE,
    label        VARCHAR(50) NOT NULL,
    definition   VARCHAR(200) NOT NULL,
    tier_access  JSON NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------------
-- 3. sku_master
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sku_master (
    id                      CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id                  VARCHAR(50) NOT NULL UNIQUE,
    cluster_id              VARCHAR(50) NOT NULL,
    tier                    ENUM('hero','support','harvest','kill') NOT NULL,
    primary_intent_id       SMALLINT NOT NULL,
    status                  ENUM('draft','ready','published','archived')
                            NOT NULL DEFAULT 'draft',
    erp_margin_pct          DECIMAL(5,2) NULL,
    erp_cppc                DECIMAL(8,4) NULL,
    erp_velocity_90d        INT NULL,
    erp_return_rate_pct     DECIMAL(5,2) NULL,
    commercial_score        DECIMAL(8,4) NULL,
    decay_status            ENUM('none','yellow_flag','alert','auto_brief','escalated')
                            NOT NULL DEFAULT 'none',
    decay_consecutive_zeros SMALLINT NOT NULL DEFAULT 0,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sku_master_cluster
        FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id),
    CONSTRAINT fk_sku_master_primary_intent
        FOREIGN KEY (primary_intent_id) REFERENCES intent_taxonomy(intent_id),
    INDEX idx_sku_master_cluster (cluster_id),
    INDEX idx_sku_master_tier (tier),
    INDEX idx_sku_master_status (status),
    INDEX idx_sku_master_decay_status (decay_status),
    INDEX idx_sku_master_commercial_score (commercial_score)
);

-- -------------------------------------------------------------------
-- 4. sku_secondary_intents
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sku_secondary_intents (
    id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id      VARCHAR(50) NOT NULL,
    intent_id   SMALLINT NOT NULL,
    ordinal     SMALLINT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ssi_sku_master
        FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ssi_intent_taxonomy
        FOREIGN KEY (intent_id) REFERENCES intent_taxonomy(intent_id),
    UNIQUE KEY uq_ssi_sku_intent (sku_id, intent_id),
    INDEX idx_ssi_sku (sku_id),
    INDEX idx_ssi_intent (intent_id)
);

-- -------------------------------------------------------------------
-- 5. material_wikidata
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS material_wikidata (
    id            CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    material_id   VARCHAR(20) NOT NULL UNIQUE,
    material_name VARCHAR(100) NOT NULL,
    wikidata_qid  VARCHAR(20) NOT NULL,
    wikidata_uri  VARCHAR(100) NOT NULL,
    ai_signal     VARCHAR(300) NOT NULL,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------------
-- 6. sku_content
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sku_content (
    id                CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id            VARCHAR(50) NOT NULL UNIQUE,
    title             VARCHAR(250) NOT NULL,
    description       TEXT NOT NULL,
    answer_block      VARCHAR(300) NULL,
    best_for          JSON NULL,
    not_for           JSON NULL,
    expert_authority  TEXT NULL,
    wikidata_uri      VARCHAR(100) NULL,
    material_id       VARCHAR(20) NULL,
    vector_similarity DECIMAL(6,4) NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sku_content_sku_master
        FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_sku_content_material
        FOREIGN KEY (material_id) REFERENCES material_wikidata(material_id),
    INDEX idx_sku_content_sku (sku_id)
);

-- -------------------------------------------------------------------
-- 7. sku_gate_status
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sku_gate_status (
    id            CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id        VARCHAR(50) NOT NULL,
    gate_code     ENUM('G1','G2','G3','G4','G5','G6','G6_1','G7','VECTOR') NOT NULL,
    status        ENUM('pass','fail','pending','not_applicable') NOT NULL,
    error_code    VARCHAR(40) NULL,
    error_message VARCHAR(500) NULL,
    checked_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gate_status_sku_master
        FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_gate_status_sku_code (sku_id, gate_code),
    INDEX idx_gate_status_status (status),
    INDEX idx_gate_status_checked_at (checked_at)
);

-- -------------------------------------------------------------------
-- 8. channel_readiness
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS channel_readiness (
    id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id           VARCHAR(50) NOT NULL,
    channel          ENUM('google_sge','amazon','ai_assistants','own_website') NOT NULL,
    score            SMALLINT NOT NULL,
    component_scores JSON NOT NULL,
    computed_at      TIMESTAMP NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_channel_readiness_sku_master
        FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_channel_readiness_sku_channel (sku_id, channel),
    INDEX idx_channel_readiness_score (score),
    INDEX idx_channel_readiness_computed_at (computed_at)
);

-- -------------------------------------------------------------------
-- 9. sku_tier_history (immutable audit trail for canonical tiers)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sku_tier_history (
    id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id          VARCHAR(50) NOT NULL,
    old_tier        ENUM('hero','support','harvest','kill') NOT NULL,
    new_tier        ENUM('hero','support','harvest','kill') NOT NULL,
    reason          ENUM('erp_sync','manual_override','auto_promote','quarterly_review') NOT NULL,
    approved_by     VARCHAR(100) NULL,
    second_approver VARCHAR(100) NULL,
    changed_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sku_tier_history_sku_master
        FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id),
    CONSTRAINT chk_sku_tier_history_change CHECK (old_tier <> new_tier),
    INDEX idx_sku_tier_history_sku (sku_id),
    INDEX idx_sku_tier_history_changed_at (changed_at)
);

-- -------------------------------------------------------------------
-- 10. tier_types (4 canonical tiers)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tier_types (
    id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    tier_key    ENUM('hero','support','harvest','kill') NOT NULL UNIQUE,
    label       VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------------
-- 11. tier_intent_rules (normalized mapping tier → intent)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tier_intent_rules (
    id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    tier       ENUM('hero','support','harvest','kill') NOT NULL,
    intent_id  SMALLINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
               ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tier_intent_rules_intent
        FOREIGN KEY (intent_id) REFERENCES intent_taxonomy(intent_id),
    UNIQUE KEY uq_tier_intent (tier, intent_id),
    INDEX idx_tier_intent_tier (tier),
    INDEX idx_tier_intent_intent (intent_id)
);

SET FOREIGN_KEY_CHECKS = 1;

