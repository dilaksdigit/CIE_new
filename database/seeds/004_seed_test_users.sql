-- Seed test users
INSERT INTO users (id, email, password_hash, first_name, last_name) VALUES (UUID(), 'test@example.com', 'hash', 'Test', 'User');
