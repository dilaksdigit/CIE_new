ALTER TABLE clusters 
ADD COLUMN category TEXT NOT NULL DEFAULT 'cables';

CREATE INDEX idx_cluster_category ON clusters (category);
