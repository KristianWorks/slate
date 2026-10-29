<?php
/**
 * app/core/TestRedirect.php
 *
 * Thrown by redirect() under APP_TESTING. Carries the target URL so
 * tests can assert which page the controller wanted to send the user to.
 */

declare(strict_types=1);

namespace App\Core;

final class TestRedirect extends TestExit
{
    public readonly string $targetUrl;

    public function __construct(string $url)
    {
        parent::__construct("Redirect to: {$url}");
        $this->targetUrl = $url;
    }
}
