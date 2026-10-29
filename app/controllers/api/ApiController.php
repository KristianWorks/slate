<?php
/**
 * app/controllers/api/ApiController.php
 *
 * Shared base for every /api/v1/* controller.
 *
 * JSON envelope (every response):
 *   {
 *     "data":   <object | array | null>,
 *     "errors": [ { "code": "...", "message": "...", "field": "..." } ],
 *     "meta":   { ... }   // optional, pagination etc.
 *   }
 *
 * Status codes follow HTTP convention. Each helper terminates the
 * request with `exit` — controllers shouldn't return from these.
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Middleware\RateLimit;
use App\Models\ApiToken;

abstract class ApiController
{
    /* ============================================================
     * Response helpers
     * ============================================================ */

    /** @param array<string,mixed> $payload */
    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        echo (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        exit;
    }

    /**
     * @param mixed                $data
     * @param array<string,mixed>  $meta
     */
    protected function ok(mixed $data, ?array $meta = null, int $status = 200): never
    {
        $payload = ['data' => $data, 'errors' => []];
        if ($meta !== null) $payload['meta'] = $meta;
        $this->json($payload, $status);
    }

    protected function created(mixed $data): never
    {
        $this->ok($data, null, 201);
    }

    protected function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    protected function error(
        int $status,
        string $code,
        string $message,
        ?string $field = null,
    ): never {
        $err = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $err['field'] = $field;
        }
        $this->json(['data' => null, 'errors' => [$err]], $status);
    }

    /** @param array<string,string> $fieldErrors */
    protected function unprocessable(array $fieldErrors): never
    {
        $errs = [];
        foreach ($fieldErrors as $field => $msg) {
            $errs[] = [
                'code'    => 'validation_failed',
                'field'   => (string) $field,
                'message' => (string) $msg,
            ];
        }
        $this->json(['data' => null, 'errors' => $errs], 422);
    }

    /* ============================================================
     * Request parsing
     * ============================================================ */

    /**
     * Returns the decoded request body — JSON if Content-Type indicates,
     * otherwise the $_POST array. Never throws — invalid JSON yields [].
     *
     * @return array<string,mixed>
     */
    protected function input(): array
    {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') !== false) {
            $raw  = (string) file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }

    /* ============================================================
     * Auth
     * ============================================================ */

    /**
     * Read + verify the Bearer token, return the token row joined with
     * the user. Issues 401 otherwise. Touches `last_used_at` on success.
     *
     * @return array<string,mixed>
     */
    protected function requireAdminToken(): array
    {
        $header = $this->authorizationHeader();
        if (!preg_match('/^Bearer\s+(\S+)\s*$/i', $header, $m)) {
            $this->error(401, 'unauthorized', 'Missing or malformed Authorization header.');
        }

        $row = ApiToken::findByPlainToken($m[1]);
        if ($row === null) {
            $this->error(401, 'unauthorized', 'Invalid or revoked API token.');
        }
        if ((string) $row['user_role'] !== 'admin') {
            $this->error(403, 'forbidden', 'This token is not authorised for admin endpoints.');
        }

        ApiToken::touch((int) $row['id']);
        return $row;
    }

    private function authorizationHeader(): string
    {
        // Apache + PHP-FPM expose it as HTTP_AUTHORIZATION. mod_php on some
        // hosts strips it — check the fallback our root .htaccess sets.
        return (string) (
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        );
    }

    /* ============================================================
     * Rate limiting
     * ============================================================ */

    /** Public endpoints — 60 requests / minute per IP. */
    protected function applyPublicRateLimit(string $bucket): void
    {
        RateLimit::guard('api_public_' . $bucket, maxAttempts: 60, windowSeconds: 60);
    }

    /** Admin endpoints — 600/min per (IP + token). */
    protected function applyAdminRateLimit(string $bucket, int $tokenId): void
    {
        RateLimit::guard('api_admin_' . $bucket . '_' . $tokenId, maxAttempts: 600, windowSeconds: 60);
    }
}
