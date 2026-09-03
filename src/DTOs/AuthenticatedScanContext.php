<?php

namespace LensForLaravel\LensForLaravel\DTOs;

class AuthenticatedScanContext
{
    /**
     * Browser cookies issued server-side for an authenticated scan.
     *
     * The session id is captured after a server-side login and is never
     * accepted from the client, never logged, and never persisted to history.
     *
     * @param  array<string, string>  $cookies
     */
    public function __construct(public readonly array $cookies = []) {}

    public function isEmpty(): bool
    {
        return $this->cookies === [];
    }
}
