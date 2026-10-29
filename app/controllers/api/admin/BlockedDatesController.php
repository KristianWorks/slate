<?php
/**
 * app/controllers/api/admin/BlockedDatesController.php
 *
 * POST /api/v1/admin/blocked-dates — block a specific date.
 */

declare(strict_types=1);

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\ApiController;
use App\Models\BlockedDate;

final class BlockedDatesController extends ApiController
{
    public function store(): void
    {
        $token = $this->requireAdminToken();
        $this->applyAdminRateLimit('blocked_create', (int) $token['id']);

        $body = $this->input();
        $date   = (string) ($body['date']   ?? '');
        $reason = trim((string) ($body['reason'] ?? '')) ?: null;

        $errors = [];
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)
            || !checkdate(
                (int) substr($date, 5, 2),
                (int) substr($date, 8, 2),
                (int) substr($date, 0, 4),
            )
        ) {
            $errors['date'] = 'date must be a valid YYYY-MM-DD.';
        }
        if ($errors !== []) $this->unprocessable($errors);

        if (BlockedDate::findByDate($date) !== null) {
            $this->error(409, 'already_blocked', "{$date} is already on the block list.");
        }

        $id = BlockedDate::create($date, $reason);

        $this->created([
            'id'         => $id,
            'date'       => $date,
            'reason'     => $reason,
            'created_at' => date('c'),
        ]);
    }
}
