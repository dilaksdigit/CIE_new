-- 1. Create Golden Queries Table (The 20 locked questions)
CREATE TABLE ai_golden_queries (
    question_id VARCHAR(20) PRIMARY KEY, -- e.g., CAB-Q01
    category TEXT NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    intent_type_id SMALLINT, -- FK to intent_taxonomy if needed, using INT for now as per report sketch
    query_family TEXT NOT NULL,
    target_tier TEXT NOT NULL,
    target_skus JSON, -- Array of sku_ids
    success_criteria VARCHAR(300),
    locked_until DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create AI Audit Runs Table (Weekly execution)
CREATE TABLE ai_audit_runs (
    run_id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    category TEXT NOT NULL,
    run_date DATE NOT NULL,
    status TEXT DEFAULT 'running',
    total_questions SMALLINT,
    aggregate_citation_rate DECIMAL(5,4), -- 0.7500 = 75%
    pass_fail TEXT,
    engines_available SMALLINT, -- v2.3.2 Patch 2
    quorum_met BOOLEAN, -- v2.3.2 Patch 2
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Re-create Audit Results Table (Linked to Run + Question)
-- Dropping old non-compliant table if exists
DROP TABLE IF EXISTS audit_results;

CREATE TABLE ai_audit_results (
    result_id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    run_id CHAR(36) NOT NULL,
    question_id VARCHAR(20) NOT NULL,
    engine TEXT NOT NULL,
    score SMALLINT CHECK (score BETWEEN 0 AND 3),
    response_snippet TEXT, -- First 500 chars
    cited_sku_id CHAR(36), -- Nullable, FK to skus
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (run_id) REFERENCES ai_audit_runs(run_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES ai_golden_queries(question_id),
    FOREIGN KEY (cited_sku_id) REFERENCES skus(id) ON DELETE SET NULL
);
