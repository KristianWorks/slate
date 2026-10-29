<?php
/**
 * app/controllers/admin/EmailTestController.php
 *
 * Developer convenience: send a test of any email template to a chosen
 * address, against a chosen real appointment. Admin-only.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Models\Appointment;
use App\Models\Setting;
use App\Services\Mailer;

final class EmailTestController
{
    public function __construct()
    {
        require_admin();
    }

    /** GET /admin/email-test */
    public function index(): void
    {
        $recent = Database::getInstance()->fetchAll(
            "SELECT a.id, a.customer_name, a.customer_email, a.starts_at, a.status,
                    s.name AS service_name
               FROM appointments a
               JOIN services s ON s.id = a.service_id
              ORDER BY a.created_at DESC
              LIMIT 25",
        );

        view('admin/email_test', [
            'title'        => 'Email test — Slate Admin',
            'active'       => 'email_test',
            'recent'       => $recent,
            'defaultTo'    => (string) (Setting::get('contact_email') ?? ''),
            'mailHost'     => (string) ($_ENV['MAIL_HOST']     ?? ''),
            'mailUser'     => (string) ($_ENV['MAIL_USERNAME'] ?? ''),
            'mailFromAddr' => (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''),
        ], 'admin');
    }

    /** POST /admin/email-test/send */
    public function send(): void
    {
        csrf_check();

        $apptId = (int) ($_POST['appointment_id'] ?? 0);
        $type   = (string) ($_POST['type'] ?? '');
        $toRaw  = trim((string) ($_POST['to'] ?? ''));
        $to     = $toRaw !== '' ? $toRaw : null;

        if ($to !== null && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Recipient is not a valid email address.');
            redirect('/admin/email-test');
        }

        $appt = Appointment::findById($apptId);
        if ($appt === null) {
            flash('error', 'Appointment not found.');
            redirect('/admin/email-test');
        }

        $m = new Mailer();
        $ok = match ($type) {
            Mailer::TYPE_CONFIRMATION => $m->sendConfirmation($appt, $to),
            Mailer::TYPE_CANCELLATION => $m->sendCancellation($appt, $to),
            Mailer::TYPE_RESCHEDULE   => $m->sendReschedule($appt, (string) $appt['starts_at'], $to),
            Mailer::TYPE_REMINDER     => $m->sendReminder($appt, $to),
            Mailer::TYPE_ADMIN_NOTICE => $m->sendAdminNotification($appt, $to),
            default                   => false,
        };

        if ($ok) {
            flash('success', 'Test email sent (' . $type . ').');
        } else {
            flash('error', 'Send failed — check SMTP config and the PHP error log.');
        }
        redirect('/admin/email-test');
    }
}
