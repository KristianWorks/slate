<?php
/**
 * app/controllers/api/AvailabilityController.php
 *
 * GET /api/v1/availability?service_id=N&date=YYYY-MM-DD
 *
 * Returns the slot grid for one day + service. Mirrors what the web
 * /book calendar shows — same SlotGenerator, same conflict rules,
 * same timezone handling.
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Service;
use App\Services\SlotGenerator;

final class AvailabilityController extends ApiController
{
    public function index(): void
    {
        $this->applyPublicRateLimit('availability');

        $serviceId = (int) ($_GET['service_id'] ?? 0);
        $date      = (string) ($_GET['date'] ?? '');

        $errors = [];
        if ($serviceId <= 0) {
            $errors['service_id'] = 'service_id is required and must be a positive integer.';
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)
            || !checkdate(
                (int) substr($date, 5, 2),
                (int) substr($date, 8, 2),
                (int) substr($date, 0, 4),
            )
        ) {
            $errors['date'] = 'date must be in YYYY-MM-DD format and be a real date.';
        }
        if ($errors !== []) {
            $this->unprocessable($errors);
        }

        $service = null;
        foreach (Service::active() as $s) {
            if ((int) $s['id'] === $serviceId) {
                $service = $s;
                break;
            }
        }
        if ($service === null) {
            $this->error(404, 'service_not_found', "No active service with id {$serviceId}.");
        }

        $gen   = SlotGenerator::fromSettings();
        $slots = array_map(static fn(array $slot): array => [
            'starts_at' => $slot['starts_at']->setTimezone(new \DateTimeZone('UTC'))->format('c'),
            'ends_at'   => $slot['ends_at']->setTimezone(new \DateTimeZone('UTC'))->format('c'),
        ], $gen->slotsForDate($date, $service));

        $this->ok([
            'date'              => $date,
            'service_id'        => $serviceId,
            'timezone'          => $gen->timezone->getName(),
            'slots'             => $slots,
            'duration_minutes'  => (int) $service['duration_minutes'],
            'buffer_minutes'    => $gen->bufferMinutes,
        ], ['count' => count($slots)]);
    }
}
