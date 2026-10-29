<?php
/**
 * app/controllers/api/ServicesController.php
 *
 * GET /api/v1/services — list active bookable services.
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Service;

final class ServicesController extends ApiController
{
    public function index(): void
    {
        $this->applyPublicRateLimit('services');

        $services = array_map(static fn(array $s): array => [
            'id'               => (int)    $s['id'],
            'name'             => (string) $s['name'],
            'description'      => $s['description'] !== null ? (string) $s['description'] : null,
            'duration_minutes' => (int)    $s['duration_minutes'],
            'price'            => (float)  $s['price'],
            'color'            => (string) $s['color'],
        ], Service::active());

        $this->ok($services, ['count' => count($services)]);
    }
}
