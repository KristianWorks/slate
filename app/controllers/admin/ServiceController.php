<?php
/**
 * app/controllers/admin/ServiceController.php
 *
 * CRUD for the `services` table. Validation lives here; the model is dumb.
 * Delete is HTMX-driven and returns an empty body so the row vanishes.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Service;

final class ServiceController
{
    /** Brand-aligned palette — the only legal colours for a service. */
    public const PRESET_COLORS = [
        '#C84B31', '#2E7D32', '#1A56DB', '#6B21A8',
        '#B45309', '#0F766E', '#BE185D', '#1A1A1A',
    ];

    public function __construct()
    {
        require_admin();
    }

    /** GET /admin/services */
    public function index(): void
    {
        view('admin/services/index', [
            'title'    => 'Services — Slate Admin',
            'active'   => 'services',
            'services' => Service::all(),
            'presets'  => self::PRESET_COLORS,
        ], 'admin');
    }

    /** POST /admin/services */
    public function store(): void
    {
        csrf_check();
        $data = $this->extract();
        $errors = $this->validate($data);

        if ($errors !== []) {
            old_set($data + ['id' => null]);
            errors_set($errors);
            flash('error', 'Please fix the errors below.');
            redirect('/admin/services');
        }

        Service::create($data);
        flash('success', 'Service created.');
        redirect('/admin/services');
    }

    /** POST /admin/services/{id} */
    public function update(string $id): void
    {
        csrf_check();
        $serviceId = (int) $id;
        if ($serviceId <= 0 || Service::findById($serviceId) === null) {
            http_response_code(404);
            return;
        }

        $data   = $this->extract();
        $errors = $this->validate($data);

        if ($errors !== []) {
            old_set($data + ['id' => $serviceId]);
            errors_set($errors);
            flash('error', 'Please fix the errors below.');
            redirect('/admin/services');
        }

        Service::update($serviceId, $data);
        flash('success', 'Service updated.');
        redirect('/admin/services');
    }

    /** POST /admin/services/{id}/toggle  (HTMX) */
    public function toggle(string $id): void
    {
        csrf_check();
        $serviceId = (int) $id;
        if ($serviceId <= 0 || Service::findById($serviceId) === null) {
            http_response_code(404);
            return;
        }
        Service::toggleActive($serviceId);
        // Empty 204 — HTMX doesn't need to swap anything.
        http_response_code(204);
    }

    /** POST /admin/services/{id}/delete  (HTMX) */
    public function destroy(string $id): void
    {
        csrf_check();
        demo_block('Deleting services is disabled in demo mode.');
        $serviceId = (int) $id;
        if ($serviceId <= 0 || Service::findById($serviceId) === null) {
            http_response_code(404);
            return;
        }

        $result = Service::deleteOrDeactivate($serviceId);

        if ($result === 'deactivated') {
            // Tell HTMX to refresh the page so the row appears as inactive,
            // and flash a heads-up next paint.
            flash('success', 'Service has existing appointments — marked inactive instead.');
            header('HX-Refresh: true');
            return;
        }

        flash('success', 'Service deleted.');
        // Empty body → HTMX removes the row via outerHTML swap.
        echo '';
    }

    /**
     * Pull and coerce form fields.
     * @return array<string,scalar|null>
     */
    private function extract(): array
    {
        return [
            'name'             => trim((string) ($_POST['name'] ?? '')),
            'description'      => trim((string) ($_POST['description'] ?? '')) ?: null,
            'duration_minutes' => (int) ($_POST['duration_minutes'] ?? 0),
            'price'            => (string) ($_POST['price'] ?? '0'),
            'color'            => strtoupper(trim((string) ($_POST['color'] ?? ''))),
            'is_active'        => !empty($_POST['is_active']) ? 1 : 0,
        ];
    }

    /**
     * @param  array<string,scalar|null> $d
     * @return array<string,string>
     */
    private function validate(array $d): array
    {
        $errors = [];

        if ($d['name'] === '' || mb_strlen((string) $d['name']) > 150) {
            $errors['name'] = 'Name is required (max 150 characters).';
        }
        if ($d['duration_minutes'] < 5 || $d['duration_minutes'] > 720) {
            $errors['duration_minutes'] = 'Duration must be between 5 and 720 minutes.';
        }
        if (!is_numeric($d['price']) || (float) $d['price'] < 0 || (float) $d['price'] > 99999.99) {
            $errors['price'] = 'Price must be a number between 0 and 99,999.99.';
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', (string) $d['color'])
            || !in_array((string) $d['color'], array_map('strtoupper', self::PRESET_COLORS), true)) {
            $errors['color'] = 'Please pick one of the preset colours.';
        }

        return $errors;
    }
}
