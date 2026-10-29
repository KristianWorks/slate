<?php
/**
 * app/controllers/api/admin/AppointmentsController.php
 *
 *   GET   /api/v1/admin/appointments?status=&from=&to=&page=
 *   PATCH /api/v1/admin/appointments/{id}
 *
 * Bearer-token authenticated. Admin tokens only.
 */

declare(strict_types=1);

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\ApiController;
use App\Models\Appointment;
use App\Services\Mailer;
use App\Services\WebhookDispatcher;

final class AppointmentsController extends ApiController
{
    private const PER_PAGE = 25;
    private const MAX_PAGE = 100;

    public function index(): void
    {
        $token = $this->requireAdminToken();
        $this->applyAdminRateLimit('appts_list', (int) $token['id']);

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $status = (string) ($_GET['status'] ?? 'all');
        $from   = (string) ($_GET['from']   ?? '');
        $to     = (string) ($_GET['to']     ?? '');
        $q      = trim((string) ($_GET['q'] ?? ''));

        $filters = [
            'status'    => $status,
            'q'         => $q,
            'order'     => 'desc',
        ];
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $filters['date_from'] = $from . ' 00:00:00';
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $filters['date_to'] = $to . ' 23:59:59';
        }

        $total = Appointment::countFiltered($filters);
        $rows  = Appointment::listFiltered(
            $filters,
            limit:  self::PER_PAGE,
            offset: ($page - 1) * self::PER_PAGE,
        );

        $this->ok(
            array_map([$this, 'present'], $rows),
            [
                'page'        => $page,
                'per_page'    => self::PER_PAGE,
                'total'       => $total,
                'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            ],
        );
    }

    public function update(string $id): void
    {
        $token = $this->requireAdminToken();
        $this->applyAdminRateLimit('appts_update', (int) $token['id']);

        $apptId = (int) $id;
        $appt = Appointment::findById($apptId);
        if ($appt === null) {
            $this->error(404, 'not_found', 'No appointment with that id.');
        }

        $body = $this->input();
        $errors = [];

        $newStatus = isset($body['status']) ? (string) $body['status'] : null;
        if ($newStatus !== null && !in_array($newStatus, Appointment::VALID_STATUSES, true)) {
            $errors['status'] = 'status must be one of: '
                . implode(', ', Appointment::VALID_STATUSES);
        }

        $newNotes = isset($body['notes']) ? (string) $body['notes'] : null;
        if ($newNotes !== null && mb_strlen($newNotes) > 5000) {
            $errors['notes'] = 'notes must be 5000 characters or fewer.';
        }

        if ($errors !== []) {
            $this->unprocessable($errors);
        }
        if ($newStatus === null && $newNotes === null) {
            $this->error(400, 'no_changes', 'Provide at least one of: status, notes.');
        }

        // Apply status change first so we can fire the right webhook.
        if ($newStatus !== null && $newStatus !== (string) $appt['status']) {
            Appointment::updateStatus($apptId, $newStatus);
            logger()->info('API appointment status changed', [
                'appointment_id' => $apptId,
                'from'           => (string) $appt['status'],
                'to'             => $newStatus,
                'token_id'       => (int) $token['id'],
            ]);

            if ($newStatus === 'cancelled') {
                Mailer::queueCancellation($apptId);
                WebhookDispatcher::dispatch('booking.cancelled', $apptId);
            } elseif ($newStatus === 'completed') {
                WebhookDispatcher::dispatch('booking.completed', $apptId);
            }
        }

        if ($newNotes !== null) {
            Appointment::updateNotes($apptId, trim($newNotes));
        }

        $this->ok($this->present(Appointment::findById($apptId)));
    }

    /**
     * @param  array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function present(array $a): array
    {
        $utc = new \DateTimeZone('UTC');
        return [
            'id'             => (int)    $a['id'],
            'reference'      => strtoupper(substr((string) $a['cancel_token'], -8)),
            'status'         => (string) $a['status'],
            'service' => [
                'id'               => (int)    $a['service_id'],
                'name'             => (string) $a['service_name'],
                'duration_minutes' => (int)    $a['service_duration'],
                'price'            => (float)  $a['service_price'],
                'color'            => (string) $a['service_color'],
            ],
            'customer' => [
                'name'  => (string) $a['customer_name'],
                'email' => (string) $a['customer_email'],
                'phone' => (string) $a['customer_phone'],
            ],
            'starts_at' => (new \DateTimeImmutable((string) $a['starts_at']))->setTimezone($utc)->format('c'),
            'ends_at'   => (new \DateTimeImmutable((string) $a['ends_at']))->setTimezone($utc)->format('c'),
            'notes'     => $a['notes'] !== null ? (string) $a['notes'] : null,
            'created_at'=> (new \DateTimeImmutable((string) $a['created_at']))->setTimezone($utc)->format('c'),
            'updated_at'=> (new \DateTimeImmutable((string) $a['updated_at']))->setTimezone($utc)->format('c'),
        ];
    }
}
