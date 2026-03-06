SET NAMES utf8mb4;

CREATE TABLE weekly_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    score INT NOT NULL,
    notes TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_weekly_scores_score_range CHECK (score BETWEEN 1 AND 10),
    UNIQUE KEY uniq_week_start (week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
