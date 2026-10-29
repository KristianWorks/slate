<?php
/**
 * app/controllers/admin/ApiTokenController.php
 *
 *   GET  /admin/settings/api-tokens          list + create form
 *   POST /admin/settings/api-tokens          generate (returns plain token via flash)
 *   POST /admin/settings/api-tokens/{id}/revoke
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ApiToken;

final class ApiTokenController
{
    public function __construct()
    {
        require_admin();
    }

    public function index(): void
    {
        // Plain token is one-shot — passed through the session by create().
        $justCreated = $_SESSION['_new_api_token'] ?? null;
        unset($_SESSION['_new_api_token']);

        view('admin/api_tokens/index', [
            'title'       => 'API tokens — Slate Admin',
            'active'      => 'api_tokens',
            'tokens'      => ApiToken::listForUser((int) current_user_id()),
            'newToken'    => $justCreated,
        ], 'admin');
    }

    public function create(): void
    {
        csrf_check();
        demo_block('Generating API tokens is disabled in demo mode.');

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            flash('error', 'Token name is required (max 120 characters).');
            redirect('/admin/settings/api-tokens');
        }

        $token = ApiToken::generate((int) current_user_id(), $name);

        $_SESSION['_new_api_token'] = $token['plain'];
        flash('success', 'Token generated. Copy it now — you won\'t see it again.');
        redirect('/admin/settings/api-tokens');
    }

    public function revoke(string $id): void
    {
        csrf_check();
        demo_block('Revoking tokens is disabled in demo mode.');

        $deleted = ApiToken::revoke((int) $id, (int) current_user_id());
        flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Token revoked.' : 'Token not found.');
        redirect('/admin/settings/api-tokens');
    }
}
