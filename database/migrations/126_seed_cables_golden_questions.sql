-- SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §5.4
-- SOURCE: CLAUDE.md §12
-- FIX: DEC-05 — Seed cables golden questions with 8/7/5 distribution and 90-day lock.

SET NAMES utf8mb4;

INSERT INTO ai_golden_queries (
    question_id,
    category,
    question_text,
    intent_type_id,
    query_family,
    target_tier,
    target_skus,
    success_criteria,
    locked_until,
    is_active
) VALUES
-- Primary family (8)
('CAB-Q01', 'cables', 'Will this pendant cable work with an E27 bulb holder?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q02', 'cables', 'Can I use a 3-core braided cable for a bathroom pendant?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q03', 'cables', 'What cable do I need for a ceiling pendant light?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q04', 'cables', 'Is this cable compatible with dimmer switches?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q05', 'cables', 'What is the maximum wattage for a pendant cable set?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q06', 'cables', 'Can I connect two pendant lights to one ceiling rose?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q07', 'cables', 'What is the difference between 2-core and 3-core pendant cable?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q08', 'cables', 'Will this cable support a heavy lampshade?', NULL, 'primary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),

-- Secondary family (7)
('CAB-Q09', 'cables', 'How do I wire a pendant light to the ceiling?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q10', 'cables', 'How do I shorten a pendant cable?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q11', 'cables', 'Do I need an electrician to install a pendant light?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q12', 'cables', 'How do I change a pendant light fitting?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q13', 'cables', 'What tools do I need to install a ceiling pendant?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q14', 'cables', 'How do I wire a pendant light with an earth cable?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q15', 'cables', 'Can I install a pendant light without a junction box?', NULL, 'secondary', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),

-- Other family (5)
('CAB-Q16', 'cables', 'Best pendant cable for kitchen island lighting?', NULL, 'other', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q17', 'cables', 'Braided vs PVC pendant cable: which is better?', NULL, 'other', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q18', 'cables', 'My pendant light keeps flickering. What should I check?', NULL, 'other', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q19', 'cables', 'Are braided pendant cables safe for old wiring?', NULL, 'other', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1),
('CAB-Q20', 'cables', 'Where can I buy replacement pendant cable in the UK?', NULL, 'other', 'hero', JSON_ARRAY(), 'Score >=1 on next weekly audit', DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY), 1)
ON DUPLICATE KEY UPDATE
    question_text = VALUES(question_text),
    query_family = VALUES(query_family),
    locked_until = VALUES(locked_until),
    is_active = 1,
    target_tier = VALUES(target_tier),
    success_criteria = VALUES(success_criteria);
