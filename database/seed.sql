-- ============================================================
-- database/seed.sql
-- Slate booking system — realistic development sample data.
-- Run AFTER schema.sql.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Admin user
-- Password: "admin123"  (bcrypt, cost 10)
--
-- IMPORTANT: This hash is a PRECOMPUTED placeholder. Regenerate
-- with your own salt before deploying:
--
--   php -r "echo password_hash('admin123', PASSWORD_BCRYPT), PHP_EOL;"
--
-- See database/seeders/make_admin.php for a small generator.
-- ------------------------------------------------------------
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `is_super`) VALUES
    ('Admin', 'admin@slate.test',
     '$argon2id$v=19$m=65536,t=4,p=1$djBLZzh6RmZNalBZaDFGeA$XuOn9/S7qyqv1D9xJ8Ysvsh7njtGnHFnW2cnNWpkZXE',
     'admin', 1);

-- ------------------------------------------------------------
-- Services (4 with different durations & prices)
-- ------------------------------------------------------------
INSERT INTO `services` (`name`, `description`, `duration_minutes`, `price`, `color`, `is_active`) VALUES
    ('Quick Consultation', 'Short intro / Q&A session.',                      30,  35.00, '#3b82f6', 1),
    ('Standard Appointment','Regular hour-long appointment.',                 60,  80.00, '#10b981', 1),
    ('Follow-up Session',  'Mid-length follow-up appointment.',               45,  60.00, '#f59e0b', 1),
    ('Extended Session',   'Deep-dive / extended consultation.',              90, 140.00, '#ef4444', 1);

-- ------------------------------------------------------------
-- Business hours — Mon-Fri 09:00–18:00, Sat 10:00–14:00, Sun closed.
-- day_of_week: 0 = Sunday … 6 = Saturday
-- ------------------------------------------------------------
INSERT INTO `business_hours` (`day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
    (0, NULL,       NULL,       1),  -- Sunday
    (1, '09:00:00', '18:00:00', 0),  -- Monday
    (2, '09:00:00', '18:00:00', 0),  -- Tuesday
    (3, '09:00:00', '18:00:00', 0),  -- Wednesday
    (4, '09:00:00', '18:00:00', 0),  -- Thursday
    (5, '09:00:00', '18:00:00', 0),  -- Friday
    (6, '10:00:00', '14:00:00', 0);  -- Saturday

-- ------------------------------------------------------------
-- Default settings
-- ------------------------------------------------------------
INSERT INTO `settings` (`key`, `value`) VALUES
    ('business_name',         'Slate Bookings'),
    ('timezone',              'America/Toronto'),
    ('buffer_minutes',        '15'),   -- gap between back-to-back appointments
    ('advance_booking_days',  '30'),   -- how far ahead customers can book
    ('min_notice_hours',      '2'),    -- earliest allowed slot from "now"
    ('contact_email',         'admin@slate.test'),
    ('contact_phone',         '+1-555-0100'),
    ('location',              'In person · Toronto studio');
