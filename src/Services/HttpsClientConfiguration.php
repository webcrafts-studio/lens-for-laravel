<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Http\Client\PendingRequest;
use Spatie\Browsershot\Browsershot;

class HttpsClientConfiguration
{
    public function configureHttp(PendingRequest $request): PendingRequest
    {
        if ($this->shouldIgnoreErrors()) {
            $request->withoutVerifying();
        }

        return $request;
    }

    public function configureBrowser(Browsershot $browsershot): Browsershot
    {
        if ($this->shouldIgnoreErrors()) {
            $browsershot->ignoreHttpsErrors();
        }

        return $browsershot;
    }

    protected function shouldIgnoreErrors(): bool
    {
        return (bool) config('lens-for-laravel.ignore_https_errors', false);
    }
}
