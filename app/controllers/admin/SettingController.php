<?php
/**
 * app/controllers/admin/SettingController.php
 *
 * Business-wide configuration (name, timezone, booking buffers).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Setting;

final class SettingController
{
    public const BUFFER_CHOICES        = [0, 5, 10, 15, 30];
    public const DEFAULT_ADVANCE_DAYS  = 60;

    public function __construct()
    {
        require_admin();
    }

    /** GET /admin/settings */
    public function index(): void
    {
        view('admin/settings', [
            'title'      => 'Settings — Slate Admin',
            'active'     => 'settings',
            'settings'   => Setting::all(),
            'timezones'  => timezone_identifiers_list(),
            'buffers'    => self::BUFFER_CHOICES,
        ], 'admin');
    }

    /** POST /admin/settings */
    public function save(): void
    {
        csrf_check();

        $businessName       = trim((string) ($_POST['business_name'] ?? ''));
        $timezone           = (string) ($_POST['timezone'] ?? 'UTC');
        $buffer             = (int)    ($_POST['buffer_minutes'] ?? 0);
        $advanceDays        = (int)    ($_POST['advance_booking_days'] ?? self::DEFAULT_ADVANCE_DAYS);
        $minNoticeHours     = (int)    ($_POST['min_notice_hours'] ?? 0);
        $contactEmail       = trim((string) ($_POST['contact_email'] ?? ''));
        $contactPhone       = trim((string) ($_POST['contact_phone'] ?? ''));
        $location           = trim((string) ($_POST['location'] ?? ''));

        $errors = [];
        if ($businessName === '' || mb_strlen($businessName) > 120) {
            $errors['business_name'] = 'Business name is required (max 120 characters).';
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Please choose a valid timezone.';
        }
        if (!in_array($buffer, self::BUFFER_CHOICES, true)) {
            $errors['buffer_minutes'] = 'Please choose a valid buffer.';
        }
        if ($advanceDays < 1 || $advanceDays > 365) {
            $errors['advance_booking_days'] = 'Choose a value between 1 and 365 days.';
        }
        if ($minNoticeHours < 0 || $minNoticeHours > 168) {
            $errors['min_notice_hours'] = 'Minimum notice must be 0–168 hours.';
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Please enter a valid email address.';
        }
        if (mb_strlen($location) > 255) {
            $errors['location'] = 'Location must be 255 characters or fewer.';
        }

        if ($errors !== []) {
            old_set([
                'business_name'        => $businessName,
                'timezone'             => $timezone,
                'buffer_minutes'       => (string) $buffer,
                'advance_booking_days' => (string) $advanceDays,
                'min_notice_hours'     => (string) $minNoticeHours,
                'contact_email'        => $contactEmail,
                'contact_phone'        => $contactPhone,
                'location'             => $location,
            ]);
            errors_set($errors);
            flash('error', 'Please fix the errors below.');
            redirect('/admin/settings');
        }

        Setting::setMany([
            'business_name'        => $businessName,
            'timezone'             => $timezone,
            'buffer_minutes'       => (string) $buffer,
            'advance_booking_days' => (string) $advanceDays,
            'min_notice_hours'     => (string) $minNoticeHours,
            'contact_email'        => $contactEmail,
            'contact_phone'        => $contactPhone,
            'location'             => $location,
        ]);

        flash('success', 'Settings saved.');
        redirect('/admin/settings');
    }
}
