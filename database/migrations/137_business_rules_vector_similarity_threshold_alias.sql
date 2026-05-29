-- Alias key for spec vector.similarity_threshold (gates.vector_similarity_min remains canonical for code).
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'vector.similarity_threshold', '0.72', 'float',
       'Cosine similarity threshold (alias for gates.vector_similarity_min per spec)'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'vector.similarity_threshold');
