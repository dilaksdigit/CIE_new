-- CIE v2.3.2: Database-level protection — prevent updates to Kill-tier SKUs
-- MySQL: trigger blocks UPDATE when tier remains 'KILL' (application must check first; this is defence in depth)

DROP TRIGGER IF EXISTS prevent_kill_tier_update;

DELIMITER //
CREATE TRIGGER prevent_kill_tier_update
BEFORE UPDATE ON skus
FOR EACH ROW
BEGIN
    IF (OLD.tier = 'KILL' OR OLD.tier = 'kill') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Kill-tier SKUs are locked from updates';
    END IF;
END//
DELIMITER ;
