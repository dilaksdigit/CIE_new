-- SOURCE: CIE_Master_Developer_Build_Spec.docx §4.5
-- FIX: AI-14 — AI Agent call logging table

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ai_agent_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku_id VARCHAR(50) NOT NULL,
  function_called VARCHAR(100) NOT NULL,
  prompt_hash VARCHAR(64) NULL,
  response_received BOOLEAN DEFAULT FALSE,
  confidence_score DECIMAL(3,2) NULL,
  status ENUM('pending','accepted','edited','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sku_function (sku_id, function_called)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
