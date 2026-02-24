-- CIE v2.3.2 – audit_log is immutable: no UPDATE, no DELETE.

DROP TRIGGER IF EXISTS tr_audit_log_no_update;
DROP TRIGGER IF EXISTS tr_audit_log_no_delete;
DELIMITER //
CREATE TRIGGER tr_audit_log_no_update
BEFORE UPDATE ON audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is immutable: UPDATE not allowed';
END//
CREATE TRIGGER tr_audit_log_no_delete
BEFORE DELETE ON audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is immutable: DELETE not allowed';
END//
DELIMITER ;
