-- Example admin user for u248320297_eastren
-- 1. Generate hash on your Mac:
--    php -r "echo password_hash('YOUR_PASSWORD_HERE', PASSWORD_BCRYPT);"
-- 2. Replace PASTE_HASH_BELOW with the output
-- 3. Run this in phpMyAdmin (database u248320297_eastren selected)

INSERT INTO users (username, password_hash, auth_provider, app_role, is_active)
VALUES (
  'admin',
  'PASTE_HASH_BELOW',
  'local',
  'admin',
  1
)
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  is_active = 1;

-- Check it worked:
-- SELECT id, username, auth_provider, app_role, is_active FROM users;
