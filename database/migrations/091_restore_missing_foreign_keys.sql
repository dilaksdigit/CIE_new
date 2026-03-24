-- SOURCE: CIE_Master_Developer_Build_Spec §6 — Foreign key constraints enforced at the database level
-- SOURCE: CLAUDE.md §9 — Foreign keys enforced on all relationships
-- SOURCE: CIE_v232_FINAL_Developer_Instruction §DB-15 — All foreign keys defined correctly
-- FIX: CHECK 2.8 — Restore/add missing FK constraints (2.8-A, 2.8-B, 2.8-C only)
-- NOTE: MySQL commits an implicit transaction before each DDL statement; DELETE cleanup is transactional.

SET NAMES utf8mb4;

SET @tbl_sgs := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_gate_status'
);
SET @tbl_vrq := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vector_retry_queue'
);

-- 2.8-C prerequisite (only if 072_add_faq_tables.sql applied): INT → VARCHAR(50) to match cluster_master.cluster_id
SET @ft_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'faq_templates'
      AND COLUMN_NAME = 'cluster_id'
);
SET @sql_ft_mod := IF(@ft_col > 0,
    'ALTER TABLE faq_templates MODIFY COLUMN cluster_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
    'SELECT ''faq_templates.cluster_id absent — skip 2.8-C column align (apply 072 first)'' AS msg'
);
PREPARE stmt_ft_mod FROM @sql_ft_mod;
EXECUTE stmt_ft_mod;
DEALLOCATE PREPARE stmt_ft_mod;

START TRANSACTION;

-- 2.8-A: Orphan cleanup — sku_gate_status.sku_id → sku_master.sku_id
SET @sql_del_sgs := IF(@tbl_sgs > 0,
    'DELETE FROM sku_gate_status WHERE sku_id NOT IN (SELECT sku_id FROM sku_master)',
    'SELECT ''skip sku_gate_status orphan cleanup (table missing)'' AS msg'
);
PREPARE stmt_del_sgs FROM @sql_del_sgs;
EXECUTE stmt_del_sgs;
DEALLOCATE PREPARE stmt_del_sgs;

-- 2.8-B: Orphan cleanup — vector_retry_queue → sku_master / cluster_master
SET @sql_del_vrq1 := IF(@tbl_vrq > 0,
    'DELETE FROM vector_retry_queue WHERE sku_id NOT IN (SELECT sku_id FROM sku_master)',
    'SELECT ''skip vector_retry_queue sku orphan cleanup (table missing)'' AS msg'
);
PREPARE stmt_del_vrq1 FROM @sql_del_vrq1;
EXECUTE stmt_del_vrq1;
DEALLOCATE PREPARE stmt_del_vrq1;

SET @sql_del_vrq2 := IF(@tbl_vrq > 0,
    'DELETE FROM vector_retry_queue WHERE cluster_id NOT IN (SELECT cluster_id FROM cluster_master)',
    'SELECT ''skip vector_retry_queue cluster orphan cleanup (table missing)'' AS msg'
);
PREPARE stmt_del_vrq2 FROM @sql_del_vrq2;
EXECUTE stmt_del_vrq2;
DEALLOCATE PREPARE stmt_del_vrq2;

COMMIT;

-- 2.8-C: Clear invalid cluster references (dynamic SQL so parse succeeds when column absent)
SET @sql_ft_orph := IF(@ft_col > 0,
    'UPDATE faq_templates SET cluster_id = NULL WHERE cluster_id IS NOT NULL AND cluster_id NOT IN (SELECT cluster_id FROM cluster_master)',
    'SELECT ''skip faq_templates orphan cleanup (no cluster_id column)'' AS msg'
);
PREPARE stmt_ft_orph FROM @sql_ft_orph;
EXECUTE stmt_ft_orph;
DEALLOCATE PREPARE stmt_ft_orph;

-- 2.8-A: Restore FK removed by 079_drop_sku_gate_status_fk_to_sku_master.sql (do not edit 079)
SET @fk_gs := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sku_gate_status'
      AND CONSTRAINT_NAME = 'fk_gate_status_sku_master'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_gs := IF(@tbl_sgs = 0,
    'SELECT ''skip fk_gate_status_sku_master (sku_gate_status missing)'' AS msg',
    IF(@fk_gs = 0,
        'ALTER TABLE sku_gate_status ADD CONSTRAINT fk_gate_status_sku_master FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE',
        'SELECT ''fk_gate_status_sku_master already present'' AS msg'
    )
);
PREPARE stmt_gs FROM @sql_gs;
EXECUTE stmt_gs;
DEALLOCATE PREPARE stmt_gs;

-- 2.8-B: vector_retry_queue FKs
SET @fk_rq_sku := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vector_retry_queue'
      AND CONSTRAINT_NAME = 'fk_retry_queue_sku'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_rq_sku := IF(@tbl_vrq = 0,
    'SELECT ''skip fk_retry_queue_sku (vector_retry_queue missing)'' AS msg',
    IF(@fk_rq_sku = 0,
        'ALTER TABLE vector_retry_queue ADD CONSTRAINT fk_retry_queue_sku FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE',
        'SELECT ''fk_retry_queue_sku already present'' AS msg'
    )
);
PREPARE stmt_rq_sku FROM @sql_rq_sku;
EXECUTE stmt_rq_sku;
DEALLOCATE PREPARE stmt_rq_sku;

SET @fk_rq_cl := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vector_retry_queue'
      AND CONSTRAINT_NAME = 'fk_retry_queue_cluster'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_rq_cl := IF(@tbl_vrq = 0,
    'SELECT ''skip fk_retry_queue_cluster (vector_retry_queue missing)'' AS msg',
    IF(@fk_rq_cl = 0,
        'ALTER TABLE vector_retry_queue ADD CONSTRAINT fk_retry_queue_cluster FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE CASCADE',
        'SELECT ''fk_retry_queue_cluster already present'' AS msg'
    )
);
PREPARE stmt_rq_cl FROM @sql_rq_cl;
EXECUTE stmt_rq_cl;
DEALLOCATE PREPARE stmt_rq_cl;

-- 2.8-C: faq_templates.cluster_id → cluster_master (skip when 072 not applied)
SET @fk_ft := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'faq_templates'
      AND CONSTRAINT_NAME = 'fk_faq_templates_cluster'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_ft := IF(@ft_col = 0,
    'SELECT ''skip fk_faq_templates_cluster (faq_templates.cluster_id missing)'' AS msg',
    IF(@fk_ft = 0,
        'ALTER TABLE faq_templates ADD CONSTRAINT fk_faq_templates_cluster FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE SET NULL',
        'SELECT ''fk_faq_templates_cluster already present'' AS msg'
    )
);
PREPARE stmt_ft FROM @sql_ft;
EXECUTE stmt_ft;
DEALLOCATE PREPARE stmt_ft;
