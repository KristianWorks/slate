<?php
/**
 * app/core/TestExit.php
 *
 * Thrown by production code paths that would normally call exit() —
 * but ONLY when the APP_TESTING constant is defined (set by the test
 * bootstrap). In production this exception is never instantiated, so
 * the runtime behaviour is unchanged.
 *
 * Tests catch this to assert that a request was terminated (rate limit,
 * CSRF mismatch, etc.) without having the test process itself exit.
 */

declare(strict_types=1);

namespace App\Core;

class TestExit extends \RuntimeException
{
}
