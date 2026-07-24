<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Http\Request;
use Symfony\Component\Finder\Finder;
use Throwable;

class FileLocator
{
    protected const CONTEXT_LINE_LIMIT = 12;

    protected const DESCENDANT_TAG_LIMIT = 8;

    protected const ELEMENT_LINE_LIMIT = 40;

    protected array $frontendExtensions = ['js', 'jsx', 'ts', 'tsx', 'vue'];

    protected array $voidElements = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr',
    ];

    /**
     * Locate the file and line number for a given HTML snippet and CSS selector.
     * Uses heuristics to find the best matching Blade, React, or Vue source file in the host application.
     *
     * @return array|null Returns ['file' => string, 'line' => int] or null if not found.
     */
    public function locate(string $htmlSnippet, string $selector): ?array
    {
        $targetOpeningTag = $this->openingTag($htmlSnippet);
        $tagName = $this->extractTagName($targetOpeningTag);
        $id = $this->extractAttribute($targetOpeningTag, 'id');
        $name = $this->extractAttribute($targetOpeningTag, 'name');

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
        $targetOpeningTag = $this->openingTag($htmlSnippet);
        $attributes = [
            'src' => $this->extractAttribute($targetOpeningTag, 'src'),
            'href' => $this->extractAttribute($targetOpeningTag, 'href'),
            'aria-label' => $this->extractAttribute($targetOpeningTag, 'aria-label'),
            'title' => $this->extractAttribute($targetOpeningTag, 'title'),
        ];
        $targetClasses = $this->extractClassList($targetOpeningTag);
        $descendantSignature = $this->descendantSignature($htmlSnippet);
        $routeHints = $this->bladeRouteHints($attributes);
        $candidates = [];

        foreach ($finder as $file) {
            $contents = file_get_contents($file->getRealPath());
            $lines = preg_split('/\r?\n/', $contents);

            foreach ($lines as $index => $line) {
                $candidate = $this->sourceElementCandidate($lines, $index, $tagName, allowBladeComponents: true);

                if (! $candidate) {
                    continue;
                }

                $score = $this->bladeMatchScore(
                    $candidate,
                    $id,
                    $name,
                    $attributes,
                    $targetClasses,
                    $descendantSignature,
                    $routeHints,
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

        $targetOpeningTag = $this->openingTag($htmlSnippet);
        $attributes = [
            'id' => $id,
            'name' => $name,
            'src' => $this->extractAttribute($targetOpeningTag, 'src'),
            'href' => $this->extractAttribute($targetOpeningTag, 'href'),
            'aria-label' => $this->extractAttribute($targetOpeningTag, 'aria-label'),
            'title' => $this->extractAttribute($targetOpeningTag, 'title'),
        ];
        $targetClasses = $this->extractClassList($targetOpeningTag);
        $descendantSignature = $this->descendantSignature($htmlSnippet);
        $candidates = [];

        foreach ($finder as $file) {
            $contents = file_get_contents($file->getRealPath());
            $lines = preg_split('/\r?\n/', $contents);

            foreach ($lines as $index => $line) {
                $candidate = $this->sourceElementCandidate($lines, $index, $tagName);

                if (! $candidate) {
                    continue;
                }

                $score = $this->frontendMatchScore(
                    $candidate,
                    $attributes,
                    $targetClasses,
                    $descendantSignature,
                    $selector
                );

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

    /**
     * @return array{
     *     opening: string,
     *     block: string,
     *     prefix: string,
     *     contextLines: array<int, string>
     * }|null
     */
    protected function sourceElementCandidate(array $lines, int $index, string $tagName, bool $allowBladeComponents = false): ?array
    {
        $line = $lines[$index];
        $match = $this->sourceTagMatch($line, $tagName);

        if (! $match && $allowBladeComponents) {
            $match = $this->sourceTagMatch($line, 'x-[a-zA-Z0-9_.:-]+', rawPattern: true);
        }

        if (! $match) {
            return null;
        }

        $source = substr($line, $match['offset']);

        for ($i = $index + 1; $i < min(count($lines), $index + self::ELEMENT_LINE_LIMIT); $i++) {
            $source .= "\n".$lines[$i];
        }

        $openingTag = $this->openingTag($source);
        $sourceTagName = $this->extractTagName($openingTag);

        if (! $sourceTagName) {
            return null;
        }

        return [
            'opening' => $openingTag,
            'block' => $this->sourceElementBlock($source, $sourceTagName, $openingTag),
            'prefix' => substr($line, 0, $match['offset']),
            'contextLines' => array_slice(
                $lines,
                max(0, $index - self::CONTEXT_LINE_LIMIT),
                min(self::CONTEXT_LINE_LIMIT, $index)
            ),
        ];
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
        array $candidate,
        ?string $id,
        ?string $name,
        array $attributes,
        array $targetClasses,
        array $descendantSignature,
        array $routeHints,
        string $selector,
        bool $allowTagOnlyMatch
    ): int {
        $openingTag = $candidate['opening'];
        $score = 0;

        if ($id && $this->lineContainsAttributeValue($openingTag, 'id', $id)) {
            $score += 200;
        }

        if ($name && $this->lineContainsAttributeValue($openingTag, 'name', $name)) {
            $score += 200;
        }

        foreach ($attributes as $attribute => $value) {
            if (! $value) {
                continue;
            }

            $score += $this->bladeAttributeMatchScore(
                $openingTag,
                $attribute,
                $value,
                $allowTagOnlyMatch ? ($routeHints[$attribute] ?? null) : null
            );
        }

        $classScore = 0;

        foreach ($targetClasses as $class) {
            if ($this->lineContainsBladeClassToken($openingTag, $class)) {
                $classScore += 20;
            }
        }

        if ($score > 0) {
            return $score
                + $classScore
                + $this->selectorContextScore($candidate, $selector, frontend: false);
        }

        if ($classScore > 0) {
            return $allowTagOnlyMatch
                ? $classScore + $this->selectorContextScore($candidate, $selector, frontend: false)
                : 0;
        }

        if (! $allowTagOnlyMatch) {
            return 0;
        }

        $descendantScore = $this->descendantMatchScore(
            $candidate['block'],
            $descendantSignature,
            frontend: false
        );

        if ($descendantScore > 0) {
            return $descendantScore
                + $this->selectorContextScore($candidate, $selector, frontend: false);
        }

        if ($targetClasses !== []) {
            return 0;
        }

        $selectorParts = $this->selectorParts($selector);

        foreach ($selectorParts as $part) {
            foreach ($this->selectorPartVariants($part) as $variant) {
                if (stripos($openingTag, $variant) !== false) {
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

    protected function frontendMatchScore(
        array $candidate,
        array $attributes,
        array $targetClasses,
        array $descendantSignature,
        string $selector
    ): int {
        $openingTag = $candidate['opening'];
        $score = 0;

        foreach ($attributes as $attribute => $value) {
            if ($value && $this->lineContainsFrontendAttribute($openingTag, $attribute, $value)) {
                $score += 100;
            }
        }

        foreach ($targetClasses as $class) {
            if ($this->lineContainsClassToken($openingTag, $class)) {
                $score += 20;
            }
        }

        if ($score > 0) {
            return $score + $this->selectorContextScore($candidate, $selector, frontend: true);
        }

        $descendantScore = $this->descendantMatchScore(
            $candidate['block'],
            $descendantSignature,
            frontend: true
        );

        if ($descendantScore > 0) {
            return $descendantScore + $this->selectorContextScore($candidate, $selector, frontend: true);
        }

        $selectorParts = $this->selectorParts($selector);

        foreach ($selectorParts as $part) {
            foreach ($this->selectorPartVariants($part) as $variant) {
                if (stripos($openingTag, $variant) !== false) {
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

    protected function bladeAttributeMatchScore(string $openingTag, string $attribute, string $value, ?string $routeHint): int
    {
        if ($this->lineContainsAttributeValue($openingTag, $attribute, $value)) {
            return 120;
        }

        if ($routeHint && $this->lineContainsBladeRoute($openingTag, $routeHint)) {
            return 110;
        }

        $basename = $this->attributeBasename($value);

        if ($basename && stripos($openingTag, $basename) !== false) {
            return 80;
        }

        return 0;
    }

    protected function lineContainsBladeRoute(string $line, string $routeName): bool
    {
        return (bool) preg_match(
            '/\broute\s*\(\s*(?:name\s*:\s*)?(["\'])'.preg_quote($routeName, '/').'\1/i',
            $line
        );
    }

    protected function attributeBasename(string $value): ?string
    {
        $path = parse_url($value, PHP_URL_PATH);
        $basename = basename(rawurldecode(is_string($path) ? $path : $value));

        return $basename !== '' && $basename !== '/' ? $basename : null;
    }

    protected function bladeRouteHints(array $attributes): array
    {
        $href = $attributes['href'] ?? null;

        if (! is_string($href) || $href === '') {
            return [];
        }

        $routeName = $this->routeNameForUrl($href);

        return $routeName ? ['href' => $routeName] : [];
    }

    protected function routeNameForUrl(string $url): ?string
    {
        if (! function_exists('app') || ! app()->bound('router')) {
            return null;
        }

        if (! str_starts_with($url, '/') && ! preg_match('/^https?:\/\//i', $url)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return null;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
            $routeName = $route->getName();

            return is_string($routeName) && $routeName !== '' ? $routeName : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function descendantSignature(string $htmlSnippet): array
    {
        $tags = array_slice($this->renderedOpeningTags($htmlSnippet), 1, self::DESCENDANT_TAG_LIMIT);
        $attributes = [];
        $classes = [];

        foreach ($tags as $tag) {
            foreach (['id', 'name', 'src', 'href', 'aria-label', 'title'] as $attribute) {
                $value = $this->extractAttribute($tag, $attribute);

                if ($value) {
                    $attributes[$attribute."\0".$value] = [
                        'name' => $attribute,
                        'value' => $value,
                    ];
                }
            }

            foreach ($this->extractClassList($tag) as $class) {
                $classes[$class] = $class;
            }
        }

        return [
            'attributes' => array_values($attributes),
            'classes' => array_values($classes),
        ];
    }

    protected function descendantMatchScore(string $sourceBlock, array $signature, bool $frontend): int
    {
        $score = 0;

        foreach ($signature['attributes'] ?? [] as $attribute) {
            $matchesExactly = $frontend
                ? $this->lineContainsFrontendAttribute($sourceBlock, $attribute['name'], $attribute['value'])
                : $this->lineContainsAttributeValue($sourceBlock, $attribute['name'], $attribute['value']);

            if ($matchesExactly) {
                $score += 70;

                continue;
            }

            $basename = $this->attributeBasename($attribute['value']);

            if ($basename && stripos($sourceBlock, $basename) !== false) {
                $score += 60;
            }
        }

        foreach (array_slice($signature['classes'] ?? [], 0, 5) as $class) {
            $containsClass = $frontend
                ? $this->lineContainsClassToken($sourceBlock, $class)
                : $this->lineContainsBladeClassToken($sourceBlock, $class);

            if ($containsClass) {
                $score += 12;
            }
        }

        return $score;
    }

    protected function selectorContextScore(array $candidate, string $selector, bool $frontend): int
    {
        $score = 0;

        foreach ($this->selectorParts($selector) as $part) {
            if ($this->sourceContainsSelectorPart($candidate['opening'], $part, $frontend)) {
                continue;
            }

            if ($this->sourceContainsSelectorPart($candidate['prefix'], $part, $frontend)) {
                $score += 30;

                continue;
            }

            foreach (array_reverse($candidate['contextLines']) as $distance => $line) {
                if (! $this->sourceContainsSelectorPart($line, $part, $frontend)) {
                    continue;
                }

                $score += max(6, 28 - ($distance * 2));
                break;
            }
        }

        return $score;
    }

    protected function sourceContainsSelectorPart(string $source, string $part, bool $frontend): bool
    {
        return $frontend
            ? $this->lineContainsClassToken($source, $part)
            : $this->lineContainsBladeClassToken($source, $part);
    }

    /**
     * @return array{tag: string, offset: int}|null
     */
    protected function sourceTagMatch(string $line, string $tagName, bool $rawPattern = false): ?array
    {
        $tagPattern = $rawPattern ? $tagName : preg_quote($tagName, '/');

        if (! preg_match('/<\s*('.$tagPattern.')(?=[\s>\/]|$)/i', $line, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return [
            'tag' => $matches[1][0],
            'offset' => $matches[0][1],
        ];
    }

    protected function sourceElementBlock(string $source, string $tagName, string $openingTag): string
    {
        if (in_array(strtolower($tagName), $this->voidElements, true) || preg_match('/\/\s*>$/', $openingTag)) {
            return $openingTag;
        }

        $closingPosition = stripos($source, '</'.$tagName, strlen($openingTag));

        if ($closingPosition === false) {
            return $openingTag;
        }

        $closingEnd = strpos($source, '>', $closingPosition);

        return $closingEnd === false
            ? $openingTag
            : substr($source, 0, $closingEnd + 1);
    }

    protected function renderedOpeningTags(string $html): array
    {
        $tags = [];
        $offset = 0;
        $length = strlen($html);

        while ($offset < $length && count($tags) <= self::DESCENDANT_TAG_LIMIT) {
            $start = strpos($html, '<', $offset);

            if ($start === false) {
                break;
            }

            $next = $html[$start + 1] ?? '';
            if ($next === '/' || $next === '!' || $next === '?') {
                $offset = $start + 1;

                continue;
            }

            $tag = $this->openingTag(substr($html, $start));
            if ($tag === '' || ! $this->extractTagName($tag)) {
                $offset = $start + 1;

                continue;
            }

            $tags[] = $tag;
            $offset = $start + strlen($tag);
        }

        return $tags;
    }

    protected function openingTag(string $html): string
    {
        $start = strpos($html, '<');

        if ($start === false) {
            return '';
        }

        $quote = null;
        $escaped = false;
        $braceDepth = 0;
        $length = strlen($html);

        for ($index = $start; $index < $length; $index++) {
            $character = $html[$index];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'" || $character === '`') {
                $quote = $character;

                continue;
            }

            if ($character === '{') {
                $braceDepth++;

                continue;
            }

            if ($character === '}' && $braceDepth > 0) {
                $braceDepth--;

                continue;
            }

            if ($character === '>' && $braceDepth === 0) {
                return substr($html, $start, $index - $start + 1);
            }
        }

        return '';
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
        preg_match('/(?:^|\s)'.preg_quote($attribute, '/').'\s*=\s*(["\'])(.*?)\1/i', $html, $matches);

        return $matches[2] ?? null;
    }
}
