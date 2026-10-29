<?php
/**
 * app/controllers/api/admin/ServicesController.php
 *
 *   POST   /api/v1/admin/services       create
 *   PATCH  /api/v1/admin/services/{id}  update
 *   DELETE /api/v1/admin/services/{id}  destroy (or deactivate if FK-locked)
 */

declare(strict_types=1);

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\ApiController;
use App\Models\Service;

final class ServicesController extends ApiController
{
    private const PRESET_COLORS = [
        '#C84B31', '#2E7D32', '#1A56DB', '#6B21A8',
        '#B45309', '#0F766E', '#BE185D', '#1A1A1A',
    ];

    public function store(): void
    {
        $token = $this->requireAdminToken();
        $this->applyAdminRateLimit('svc_create', (int) $token['id']);

        $body = $this->input();
        [$data, $errors] = $this->validate($body);
        if ($errors !== []) $this->unprocessable($errors);

        $id = Service::create($data);
        $row = Service::findById($id);
        $this->created($this->present($row));
    }

    public function update(string $id): void
    {
        $token = $this->requireAdminToken();
        $this->applyAdminRateLimit('svc_update', (int) $token['id']);

        $svcId = (int) $id;
        if (Service::findById($svcId) === null) {
            $this->error(404, 'not_found', 'No service with that id.');
        }

        $body = $this->input();
        [$data, $errors] = $this->validate($body, partial: true);
        if ($errors !== []) $this->unprocessable($errors);
        if ($data === [])  $this->error(400, 'no_changes', 'Provide at least one field to update.');

        Service::update($svcId, $data);
        $this->ok($this->present(Service::findById($svcId)));
    }

    public function destroy(string $id): void
    {
        $token = $this->requireAdminToken();
        $this->applyAdminRateLimit('svc_delete', (int) $token['id']);

        $svcId = (int) $id;
        if (Service::findById($svcId) === null) {
            $this->error(404, 'not_found', 'No service with that id.');
        }

        $result = Service::deleteOrDeactivate($svcId);
        $this->ok([
            'id'      => $svcId,
            'outcome' => $result,
            'message' => $result === 'deactivated'
                ? 'Service has existing appointments — marked inactive instead of deleted.'
                : 'Service deleted.',
        ]);
    }

    /**
     * @param  array<string,mixed> $body
     * @return array{0: array<string,scalar|null>, 1: array<string,string>}
     */
    private function validate(array $body, bool $partial = false): array
    {
        $errors = [];
        $data   = [];

        if (!$partial || array_key_exists('name', $body)) {
            $name = trim((string) ($body['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 150) {
                $errors['name'] = 'name is required (max 150 characters).';
            } else {
                $data['name'] = $name;
            }
        }

        if (array_key_exists('description', $body)) {
            $desc = trim((string) $body['description']);
            $data['description'] = $desc !== '' ? $desc : null;
        }

        if (!$partial || array_key_exists('duration_minutes', $body)) {
            $dur = (int) ($body['duration_minutes'] ?? 0);
            if ($dur < 5 || $dur > 720) {
                $errors['duration_minutes'] = 'duration_minutes must be between 5 and 720.';
            } else {
                $data['duration_minutes'] = $dur;
            }
        }

        if (!$partial || array_key_exists('price', $body)) {
            $price = (float) ($body['price'] ?? 0);
            if ($price < 0 || $price > 99999.99) {
                $errors['price'] = 'price must be between 0 and 99,999.99.';
            } else {
                $data['price'] = $price;
            }
        }

        if (!$partial || array_key_exists('color', $body)) {
            $color = strtoupper(trim((string) ($body['color'] ?? '')));
            $allowed = array_map('strtoupper', self::PRESET_COLORS);
            if (!in_array($color, $allowed, true)) {
                $errors['color'] = 'color must be one of: ' . implode(', ', self::PRESET_COLORS);
            } else {
                $data['color'] = $color;
            }
        }

        if (array_key_exists('is_active', $body)) {
            $data['is_active'] = !empty($body['is_active']) ? 1 : 0;
        } elseif (!$partial) {
            $data['is_active'] = 1;
        }

        return [$data, $errors];
    }

    /** @param array<string,mixed> $s */
    private function present(array $s): array
    {
        return [
            'id'               => (int)    $s['id'],
            'name'             => (string) $s['name'],
            'description'      => $s['description'] !== null ? (string) $s['description'] : null,
            'duration_minutes' => (int)    $s['duration_minutes'],
            'price'            => (float)  $s['price'],
            'color'            => (string) $s['color'],
            'is_active'        => ((int) $s['is_active']) === 1,
            'created_at'       => (new \DateTimeImmutable((string) $s['created_at']))->format('c'),
        ];
    }
}
