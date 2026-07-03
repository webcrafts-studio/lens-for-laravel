<?php

namespace LensForLaravel\LensForLaravel\Support;

use InvalidArgumentException;

final class Wcag
{
    public const DEFAULT_VERSION = '2.0';

    public const VERSIONS = ['2.0', '2.1', '2.2'];

    private const LEVEL_A_TAGS = ['wcag2a', 'wcag21a'];

    private const LEVEL_AA_TAGS = ['wcag2aa', 'wcag21aa', 'wcag22aa'];

    private const LEVEL_AAA_TAGS = ['wcag2aaa'];

    /**
     * Return the cumulative axe-core tags required by a WCAG version.
     *
     * @return list<string>
     */
    public static function tags(string $version): array
    {
        self::assertVersion($version);

        return match ($version) {
            '2.0' => ['wcag2a', 'wcag2aa', 'wcag2aaa', 'best-practice'],
            '2.1' => ['wcag2a', 'wcag2aa', 'wcag2aaa', 'wcag21a', 'wcag21aa', 'best-practice'],
            '2.2' => ['wcag2a', 'wcag2aa', 'wcag2aaa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'],
        };
    }

    public static function configuredVersion(): string
    {
        $version = (string) config('lens-for-laravel.wcag_version', self::DEFAULT_VERSION);

        return in_array($version, self::VERSIONS, true) ? $version : self::DEFAULT_VERSION;
    }

    public static function assertVersion(string $version): void
    {
        if (! in_array($version, self::VERSIONS, true)) {
            throw new InvalidArgumentException(
                'Unsupported WCAG version. Choose one of: '.implode(', ', self::VERSIONS).'.'
            );
        }
    }

    /**
     * @param  list<string>  $tags
     */
    public static function level(array $tags): string
    {
        if (array_intersect(self::LEVEL_A_TAGS, $tags) !== []) {
            return 'a';
        }

        if (array_intersect(self::LEVEL_AA_TAGS, $tags) !== []) {
            return 'aa';
        }

        if (array_intersect(self::LEVEL_AAA_TAGS, $tags) !== []) {
            return 'aaa';
        }

        return 'other';
    }

    /**
     * @param  list<string>  $tags
     */
    public static function matchesLevel(array $tags, string $level): bool
    {
        $issueLevel = self::level($tags);

        return match ($level) {
            'a' => $issueLevel === 'a',
            'aa' => in_array($issueLevel, ['a', 'aa'], true),
            default => true,
        };
    }
}
