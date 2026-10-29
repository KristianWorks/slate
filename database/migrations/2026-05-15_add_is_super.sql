-- Migration: add users.is_super (super-admin flag).
-- Safe to run once against an existing database.
--
-- After the ALTER, promote every existing admin to super so you don't lose
-- access to the new admin/logs page. If you only want ONE super-admin,
-- replace the UPDATE with WHERE email = 'your-email@example.com'.

ALTER TABLE `users`
    ADD COLUMN `is_super` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`;

UPDATE `users` SET `is_super` = 1 WHERE `role` = 'admin';
