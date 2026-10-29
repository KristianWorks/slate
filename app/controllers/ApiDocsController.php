<?php
/**
 * app/controllers/ApiDocsController.php
 *
 * GET /api/docs — public, no auth required.
 */

declare(strict_types=1);

namespace App\Controllers;

final class ApiDocsController
{
    public function index(): void
    {
        view('api_docs/index', [
            'title' => 'API documentation — Slate',
            'baseUrl' => rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') ?: 'https://your-domain.tld',
        ]);
    }
}
