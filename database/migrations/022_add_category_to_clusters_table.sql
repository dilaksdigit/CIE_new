ALTER TABLE clusters 
ADD COLUMN category ENUM('cables', 'lampshades', 'bulbs', 'pendants', 'floor_lamps', 'ceiling_lights', 'accessories') NOT NULL DEFAULT 'cables';

CREATE INDEX idx_cluster_category ON clusters (category);
