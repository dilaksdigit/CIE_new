-- THE 9 LOCKED INTENTS - These are NEVER editable by users
INSERT INTO intents (id, name, display_name, description, is_locked, sort_order) VALUES
(UUID(), 'problem_solving', 'Problem Solving', 'Troubleshooting, fixes, and solutions', true, 1),
(UUID(), 'comparison', 'Comparison', 'Product A vs Product B comparisons', true, 2),
(UUID(), 'compatibility', 'Compatibility', 'What works with what', true, 3),
(UUID(), 'product_specs', 'Product Specifications', 'Technical specifications and features', true, 4),
(UUID(), 'installation', 'Installation', 'Setup and installation guides', true, 5),
(UUID(), 'troubleshooting', 'Troubleshooting', 'Diagnostic and repair information', true, 6),
(UUID(), 'buyer_guide', 'Buyer Guide', 'Purchasing decision support', true, 7),
(UUID(), 'use_case', 'Use Case', 'Real-world application scenarios', true, 8),
(UUID(), 'product_overview', 'Product Overview', 'General product information', true, 9);
