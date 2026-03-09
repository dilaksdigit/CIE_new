-- SOURCE: CIE_v231_Developer_Build_Pack.pdf intent_taxonomy table spec
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.2
-- ENFORCEMENT: name restricted to locked 9-intent taxonomy per CLAUDE.md §6
-- "Exactly 9 rows. Locked. Changes require quarterly review."
-- CHECK constraint chk_intent_name_locked added in v2.3.2 hardening pass.

SET NAMES utf8mb4;

CREATE TABLE intents (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
 display_name VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 is_locked BOOLEAN DEFAULT true,
 sort_order INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_name (name),
 CONSTRAINT chk_intent_name_locked CHECK (
     name IN (
         'compatibility',
         'comparison',
         'problem_solving',
         'inspiration',
         'specification',
         'installation',
         'safety_compliance',
         'replacement',
         'bulk_trade'
     )
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
