-- Hard-reset all user credentials so signup can start fresh
-- WARNING: This deletes all existing users and their role assignments.

DELETE FROM user_roles;
DELETE FROM users;

