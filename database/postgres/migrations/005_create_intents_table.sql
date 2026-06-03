-- SOURCE: CIE_v231_Developer_Build_Pack.pdf intent_taxonomy table spec
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.2
-- ENFORCEMENT: name restricted to locked 9-intent taxonomy per CLAUDE.md §6
-- "Exactly 9 rows. Locked. Changes require quarterly review."
-- CHECK constraint chk_intent_name_locked added in v2.3.2 hardening pass.

CREATE TABLE intents (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 name VARCHAR(100) NOT NULL UNIQUE,
 display_name VARCHAR(150),
 description TEXT,
 is_locked BOOLEAN DEFAULT true,
 sort_order INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

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
);
