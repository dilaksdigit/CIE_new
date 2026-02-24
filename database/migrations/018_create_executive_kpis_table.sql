CREATE TABLE executive_kpis (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    week_number INT NOT NULL,
    year INT NOT NULL,
    gate_bypass_rate DECIMAL(5, 2) DEFAULT 0.00,
    hero_effort_pct DECIMAL(5, 2) DEFAULT 0.00,
    hero_citation_rate DECIMAL(5, 2) DEFAULT 0.00,
    avg_ctr_improvement DECIMAL(5, 2) DEFAULT 0.00,
    staff_rework_rate DECIMAL(5, 2) DEFAULT 0.00,
    tier_coverage_pct DECIMAL(5, 2) DEFAULT 0.00,
    hero_readiness_avg DECIMAL(5, 2) DEFAULT 0.00,
    kill_sku_effort_hours DECIMAL(10, 2) DEFAULT 0.00,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_week (week_number, year)
);
