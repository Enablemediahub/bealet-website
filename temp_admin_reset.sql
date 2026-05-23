UPDATE users
SET password_hash = '$2y$10$MPNWHMdx1E9ETtlnOLSSTOegwZRSmiuUTQAmgB6HujQidBEsVyjgW', login_attempts = 0, locked_until = NULL
WHERE email IN ('admin@bealet.com','crepindale@gmail.com') AND is_admin = 1;
SELECT id,email,password_hash,login_attempts,locked_until FROM users WHERE email IN ('admin@bealet.com','crepindale@gmail.com');
