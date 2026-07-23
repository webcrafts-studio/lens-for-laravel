<?php

namespace LensForLaravel\LensForLaravel\Services;

use Symfony\Component\Finder\Finder;

class FileLocator
{
    protected array $frontendExtensions = ['js', 'jsx', 'ts', 'tsx', 'vue'];

    /**
     * Locate the file and line number for a given HTML snippet and CSS selector.
     * Uses heuristics to find the best matching Blade, React, or Vue source file in the host application.
     *
     * @return array|null Returns ['file' => string, 'line' => int] or null if not found.
     */
    public function locate(string $htmlSnippet, string $selector): ?array
    {
        $tagName = $this->extractTagName($htmlSnippet);
        $id = $this->extractAttribute($htmlSnippet, 'id');
        $name = $this->extractAttribute($htmlSnippet, 'name');

        if (! $tagName) {
            return null;
        }

        $bladeLocation = $this->locateInBlade($tagName, $id, $name, $selector, $htmlSnippet, allowTagOnlyMatch: false);
        if ($bladeLocation) {
            return $bladeLocation;
        }

        $frontendLocation = $this->locateInFrontend($tagName, $id, $name, $selector, $htmlSnippet);
        if ($frontendLocation) {
            return $frontendLocation;
        }

        return $this->locateInBlade($tagName, $id, $name, $selector, $htmlSnippet);
    }

    protected function locateInBlade(string $tagName, ?string $id, ?string $name, string $selector, string $htmlSnippet, bool $allowTagOnlyMatch = true): ?array
    {
        $viewsPath = resource_path('views');

        if (! is_dir($viewsPath)) {
            return null;
        }

        $finder = new Finder;
        $finder->files()->in($viewsPath)->name('*.blade.php');
        $attributes = [
            'src' => $this->extractAttribute($htmlSnippet, 'src'),
            'href' => $this->extractAttribute($htmlSnippet, 'href'),
            'aria-label' => $this->extractAttribute($htmlSnippet, 'aria-label'),
            'title' => $this->extractAttribute($htmlSnippet, 'title'),
        ];
        $targetClasses = $this->extractClassList($htmlSnippet);
        $candidates = [];

        foreach ($finder as $file) {
            $contents = file_get_contents($file->getRealPath());
            $lines = preg_split('/\r?\n/', $contents);

            foreach ($lines as $index => $line) {
                $score = $this->bladeMatchScore(
                    $line,
                    $tagName,
                    $id,
                    $name,
                    $attributes,
                    $targetClasses,
                    $selector,
                    $allowTagOnlyMatch
                );

                if ($score <= 0) {
                    continue;
                }

                $candidates[] = [
                    'file' => $file->getRelativePathname(),
                    'line' => $index + 1,
                    'type' => 'blade',
                    'score' => $score,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        return $this->bestCandidate($candidates, $selector);
    }

    protected function locateInFrontend(string $tagName, ?string $id, ?string $name, string $selector, string $htmlSnippet): ?array
    {
        $jsPath = resource_path('js');

        if (! is_dir($jsPath)) {
            return null;
        }

        $finder = new Finder;
        $finder->files()->in($jsPath)->name($this->frontendFilePatterns());

        $attributes = [
            'id' => $id,
            'name' => $name,
            'src' => $this->extractAttribute($htmlSnippet, 'src'),
            'href' => $this->extractAttribute($htmlSnippet, 'href'),
            'aria-label' => $this->extractAttribute($htmlSnippet, 'aria-label'),
            'title' => $this->extractAttribute($htmlSnippet, 'title'),
        ];
        $targetClasses = $this->extractClassList($htmlSnippet);
        $candidates = [];

        foreach ($finder as $file) {
            $contents = file_get_contents($file->getRealPath());
            $lines = preg_split('/\r?\n/', $contents);

            foreach ($lines as $index => $line) {
                $candidate = $this->frontendElementCandidate($lines, $index, $tagName);

                if (! $candidate) {
                    continue;
                }

                $score = $this->frontendMatchScore($candidate, $tagName, $attributes, $targetClasses, $selector);

                if ($score <= 0) {
                    continue;
                }

                $candidates[] = [
                    'file' => 'js/'.$file->getRelativePathname(),
                    'line' => $index + 1,
                    'type' => $this->sourceTypeForExtension($file->getExtension()),
                    'score' => $score,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        return $this->bestCandidate($candidates, $selector);
    }

    protected function bestCandidate(array $candidates, string $selector): array
    {
        $highestScore = max(array_column($candidates, 'score'));
        $bestMatches = array_values(array_filter(
            $candidates,
            fn (array $candidate): bool => $candidate['score'] === $highestScore
        ));
        $position = $this->selectorPosition($selector);
        $bestMatch = $bestMatches[0];

        if ($position !== null) {
            foreach (array_values(array_unique(array_column($bestMatches, 'file'))) as $file) {
                $fileMatches = array_values(array_filter(
                    $bestMatches,
                    fn (array $candidate): bool => $candidate['file'] === $file
                ));

                if (isset($fileMatches[$position - 1])) {
                    $bestMatch = $fileMatches[$position - 1];
                    break;
                }
            }
        }

        unset($bestMatch['score']);

        return $bestMatch;
    }

    protected function frontendElementCandidate(array $lines, int $index, string $tagName): ?string
    {
        if (stripos($lines[$index], '<'.$tagName) === false) {
            return null;
        }

        $candidate = $lines[$index];

        for ($i = $index + 1; $i < min(count($lines), $index + 12); $i++) {
            if (str_contains($candidate, '>')) {
                break;
            }

            $candidate .= "\n".$lines[$i];
        }

        return $candidate;
    }

    protected function frontendFilePatterns(): array
    {
        return array_map(fn ($extension) => '*.'.$extension, $this->frontendExtensions);
    }

    protected function sourceTypeForExtension(string $extension): string
    {
        return strtolower($extension) === 'vue' ? 'vue' : 'react';
    }

    protected function bladeMatchScore(
        string $line,
        string $tagName,
        ?string $id,
        ?string $name,
        array $attributes,
        array $targetClasses,
        string $selector,
        bool $allowTagOnlyMatch
    ): int {
        if (stripos($line, '<'.$tagName) === false && stripos($line, '<x-') === false) {
            return 0;
        }

        if ($id && (stripos($line, 'id="'.$id.'"') !== false || stripos($line, "id='".$id."'") !== false)) {
            return 200;
        }

        if ($name && (stripos($line, 'name="'.$name.'"') !== false || stripos($line, "name='".$name."'") !== false)) {
            return 200;
        }

        $score = 0;

        foreach ($attributes as $attribute => $value) {
            if (! $value) {
                continue;
            }

            if ($this->lineContainsAttributeValue($line, $attribute, $value)) {
                $score += 100;

                continue;
            }

            $path = parse_url($value, PHP_URL_PATH);
            $basename = basename(rawurldecode(is_string($path) ? $path : $value));

            if ($basename !== '' && $basename !== '/' && stripos($line, $basename) !== false) {
                $score += 80;
            }
        }

        $classScore = 0;

        foreach ($targetClasses as $class) {
            if ($this->lineContainsBladeClassToken($line, $class)) {
                $classScore += 20;
            }
        }

        if ($score > 0) {
            return $score + $classScore;
        }

        if ($classScore > 0) {
            return $allowTagOnlyMatch ? $classScore : 0;
        }

        if ($targetClasses !== []) {
            return 0;
        }

        $selectorParts = $this->selectorParts($selector);

        foreach ($selectorParts as $part) {
            foreach ($this->selectorPartVariants($part) as $variant) {
                if (stripos($line, $variant) !== false) {
                    $score++;
                    break;
                }
            }
        }

        if ($score > 0) {
            return $score;
        }

        return $allowTagOnlyMatch && empty($id) && empty($name) && empty($selectorParts) ? 1 : 0;
    }

    protected function lineContainsBladeClassToken(string $line, string $class): bool
    {
        return (bool) preg_match(
            '/(?<![a-zA-Z0-9:_-])'.preg_quote($class, '/').'(?![a-zA-Z0-9:_-])/i',
            $line
        );
    }

    /**
     * Determine if a line of JSX/TSX/Vue likely emitted the failing DOM element.
     */
    protected function isFrontendMatch(string $line, string $tagName, array $attributes, string $selector): bool
    {
        return $this->frontendMatchScore($line, $tagName, $attributes, [], $selector) > 0;
    }

    protected function frontendMatchScore(string $line, string $tagName, array $attributes, array $targetClasses, string $selector): int
    {
        foreach ($attributes as $attribute => $value) {
            if ($value && $this->lineContainsFrontendAttribute($line, $attribute, $value)) {
                return 100;
            }
        }

        $score = 0;
        foreach ($targetClasses as $class) {
            if ($this->lineContainsClassToken($line, $class)) {
                $score += 20;
            }
        }

        if ($score > 0) {
            return $score;
        }

        $selectorParts = $this->selectorParts($selector);

        foreach ($selectorParts as $part) {
            foreach ($this->selectorPartVariants($part) as $variant) {
                if (stripos($line, $variant) !== false) {
                    $score += 1;
                    break;
                }
            }
        }

        if ($score > 0) {
            return $score;
        }

        return empty(array_filter($attributes)) && empty($targetClasses) && empty($selectorParts) ? 1 : 0;
    }

    protected function lineContainsClassToken(string $line, string $class): bool
    {
        foreach ($this->selectorPartVariants($class) as $variant) {
            if (stripos($line, $variant) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function selectorPartVariants(string $part): array
    {
        $camel = preg_replace_callback('/-([a-z0-9])/i', fn ($matches) => strtoupper($matches[1]), strtolower($part));

        return array_values(array_unique(array_filter([
            $part,
            $camel,
            ucfirst((string) $camel),
        ])));
    }

    protected function selectorParts(string $selector): array
    {
        preg_match_all('/[#\.]((?:\\\\.|[a-zA-Z0-9\-_])+)/', $selector, $matches);

        return array_values(array_unique(array_map(
            fn ($part) => str_replace('\\:', ':', $part),
            $matches[1] ?? []
        )));
    }

    /**
     * Use a numeric :nth-child() hint to disambiguate repeated source
     * elements. Axe selectors commonly put the position on the nearest
     * ancestor, so the value is treated as the matching occurrence within
     * one source file rather than as a literal source-line child index.
     */
    protected function selectorPosition(string $selector): ?int
    {
        preg_match_all('/:nth-child\(\s*(\d+)\s*\)/i', $selector, $matches);

        if (empty($matches[1])) {
            return null;
        }

        $position = (int) end($matches[1]);

        return $position > 0 ? $position : null;
    }

    protected function extractClassList(string $html): array
    {
        $class = $this->extractAttribute($html, 'class');

        if (! $class) {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($class)) ?: []));
    }

    protected function lineContainsFrontendAttribute(string $line, string $attribute, string $value): bool
    {
        foreach ($this->attributeNamesForFrontend($attribute) as $frontendAttribute) {
            if ($this->lineContainsAttributeValue($line, $frontendAttribute, $value)) {
                return true;
            }
        }

        return false;
    }

    protected function attributeNamesForFrontend(string $attribute): array
    {
        $jsxAttribute = match ($attribute) {
            'class' => 'className',
            'for' => 'htmlFor',
            default => $attribute,
        };

        return array_values(array_unique([$attribute, $jsxAttribute]));
    }

    protected function lineContainsAttributeValue(string $line, string $attribute, string $value): bool
    {
        $quotedValue = preg_quote($value, '/');
        $quotedAttribute = preg_quote($attribute, '/');

        return (bool) preg_match('/\b'.$quotedAttribute.'\s*=\s*(["\'])'.$quotedValue.'\1/i', $line)
            || (bool) preg_match('/\b'.$quotedAttribute.'\s*=\s*\{\s*(["\'])'.$quotedValue.'\1\s*\}/i', $line)
            || (bool) preg_match('/(?::|v-bind:)'.$quotedAttribute.'\s*=\s*(["\'])\s*([\'"])'.$quotedValue.'\2\s*\1/i', $line);
    }

    /**
     * Extract the main HTML tag from the snippet.
     */
    protected function extractTagName(string $html): ?string
    {
        preg_match('/^<([a-zA-Z0-9\-]+)/', trim($html), $matches);

        return $matches[1] ?? null;
    }

    /**
     * Extract a specific attribute value from the HTML snippet.
     */
    protected function extractAttribute(string $html, string $attribute): ?string
    {
        // Matches `attr="value"` or `attr='value'`
        preg_match('/'.preg_quote($attribute, '/').'\s*=\s*(["\'])(.*?)\1/i', $html, $matches);

        return $matches[2] ?? null;
    }
}
