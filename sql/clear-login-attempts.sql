-- Clear login rate limit after failed attempts during setup
-- Run in phpMyAdmin on your portal database (e.g. u248320297_sor_management)

DELETE FROM login_attempts;
