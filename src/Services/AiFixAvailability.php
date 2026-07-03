<?php

namespace LensForLaravel\LensForLaravel\Services;

use RuntimeException;

class AiFixAvailability
{
    public function available(): bool
    {
        return $this->messageKey() === null;
    }

    /**
     * @return array{available: bool, message: string|null}
     */
    public function status(): array
    {
        return [
            'available' => $this->available(),
            'message' => $this->message(),
        ];
    }

    public function message(): ?string
    {
        $key = $this->messageKey();

        return $key === null ? null : trans("lens-for-laravel::messages.ai_fix.{$key}");
    }

    public function ensureAvailable(): void
    {
        if (! $this->available()) {
            throw new RuntimeException($this->message() ?? __('lens-for-laravel::messages.errors.ai_unavailable'));
        }
    }

    protected function messageKey(): ?string
    {
        if (! config('lens-for-laravel.ai_enabled', true)) {
            return 'disabled';
        }

        if ($this->phpVersionId() < 80300 || version_compare($this->laravelVersion(), '12.0.0', '<')) {
            return 'unsupported_runtime';
        }

        if (! $this->sdkInstalled()) {
            return 'sdk_missing';
        }

        return null;
    }

    protected function phpVersionId(): int
    {
        return PHP_VERSION_ID;
    }

    protected function laravelVersion(): string
    {
        return app()->version();
    }

    protected function sdkInstalled(): bool
    {
        return class_exists('Laravel\\Ai\\Enums\\Lab')
            && function_exists('Laravel\\Ai\\agent');
    }
}
