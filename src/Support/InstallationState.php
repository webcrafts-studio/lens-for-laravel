<?php

namespace LensForLaravel\LensForLaravel\Support;

use RuntimeException;

class InstallationState
{
    public function path(): string
    {
        return storage_path('app/lens-for-laravel/installed-'.substr(hash('sha256', base_path()), 0, 16));
    }

    public function markInstalled(): bool
    {
        $path = $this->path();
        $directory = dirname($path);
        if ((! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) || is_link($path)) {
            throw new RuntimeException('Cannot record Lens installation.');
        }
        $stream = @fopen($path, 'c+b');
        if ($stream === false) {
            throw new RuntimeException('Cannot record Lens installation.');
        }
        try {
            if (! @flock($stream, LOCK_EX)) {
                throw new RuntimeException('Cannot lock Lens installation state.');
            }
            $identity = hash('sha256', base_path());
            $contents = stream_get_contents($stream, 65);
            if ($contents === $identity) {
                return false;
            }
            if ($contents !== '') {
                throw new RuntimeException('Cannot record Lens installation.');
            }
            if (! @chmod($path, 0600) || @fwrite($stream, $identity) !== strlen($identity) || ! @fflush($stream)) {
                @ftruncate($stream, 0);
                @fflush($stream);
                throw new RuntimeException('Cannot record Lens installation.');
            }

            return true;
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }
}
