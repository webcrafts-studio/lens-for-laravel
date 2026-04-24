<?php

namespace LensForLaravel\LensForLaravel\Services;

use Symfony\Component\Finder\Finder;

class FileLocator
{
    protected array $reactExtensions = ['js', 'jsx', 'ts', 'tsx'];

    /**
     * Locate the file and line number for a given HTML snippet and CSS selector.
     * Uses heuristics to find the best matching Blade or React source file in the host application.
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

        $bladeLocation = $this->locateInBlade($tagName, $id, $name, $selector, allowTagOnlyMatch: false);
        if ($bladeLocation) {
            return $bladeLocation;
        }

        $reactLocation = $this->locateInReact($tagName, $id, $name, $selector, $htmlSnippet);
        if ($reactLocation) {
            return $reactLocation;
        }

        return $this->locateInBlade($tagName, $id, $name, $selector);
    }

    protected function locateInBlade(string $tagName, ?string $id, ?string $name, string $selector, bool $allowTagOnlyMatch = true): ?array
    {
        $viewsPath = resource_path('views');

        if (! is_dir($viewsPath)) {
            return null;
        }

        $finder = new Finder;
        $finder->files()->in($viewsPath)->name('*.blade.php');

        foreach ($finder as $file) {
            $contents = file_get_contents($file->getRealPath());
            $lines = preg_split('/\r?\n/', $contents);

            foreach ($lines as $index => $line) {
                if ($this->isMatch($line, $tagName, $id, $name, $selector, $allowTagOnlyMatch)) {
                    return [
                        'file' => $file->getRelativePathname(),
                        'line' => $index + 1,
                    ];
                }
            }
        }

        return null;
    }

    protected function locateInReact(string $tagName, ?string $id, ?string $name, string $selector, string $htmlSnippet): ?array
    {
        $jsPath = resource_path('js');

        if (! is_dir($jsPath)) {
            return null;
        }

        $finder = new Finder;
        $finder->files()->in($jsPath)->name($this->reactFilePatterns());

        $attributes = [
            'id' => $id,
            'name' => $name,
            'src' => $this->extractAttribute($htmlSnippet, 'src'),
            'href' => $this->extractAttribute($htmlSnippet, 'href'),
            'aria-label' => $this->extractAttribute($htmlSnippet, 'aria-label'),
            'title' => $this->extractAttribute($htmlSnippet, 'title'),
        ];

        foreach ($finder as $file) {
            $contents = file_get_contents($file->getRealPath());
            $lines = preg_split('/\r?\n/', $contents);

            foreach ($lines as $index => $line) {
                if ($this->isReactMatch($line, $tagName, $attributes, $selector)) {
                    return [
                        'file' => 'js/'.$file->getRelativePathname(),
                        'line' => $index + 1,
                    ];
                }
            }
        }

        return null;
    }

    protected function reactFilePatterns(): array
    {
        return array_map(fn ($extension) => '*.'.$extension, $this->reactExtensions);
    }

    /**
     * Determine if a given line of Blade code matches our target criteria.
     */
    protected function isMatch(string $line, string $tagName, ?string $id, ?string $name, string $selector, bool $allowTagOnlyMatch = true): bool
    {
        // 1. Check if the line contains the tag name (or a Blade component tag <x-)
        if (stripos($line, '<'.$tagName) === false && stripos($line, '<x-') === false) {
            return false;
        }

        // 2. Check for exact ID match
        if ($id && (stripos($line, 'id="'.$id.'"') !== false || stripos($line, "id='".$id."'") !== false)) {
            return true;
        }

        // 3. Check for exact Name match
        if ($name && (stripos($line, 'name="'.$name.'"') !== false || stripos($line, "name='".$name."'") !== false)) {
            return true;
        }

        // 4. Fallback: check if the line contains parts of the CSS selector (like class names or IDs)
        preg_match_all('/[#\.]([a-zA-Z0-9\-_]+)/', $selector, $matches);
        $selectorParts = $matches[1] ?? [];

        foreach ($selectorParts as $part) {
            if (stripos($line, $part) !== false) {
                return true;
            }
        }

        // If there's no ID, no Name, and no specific classes/IDs in the selector,
        // we assume just matching the tag is the best we can do.
        if ($allowTagOnlyMatch && empty($id) && empty($name) && empty($selectorParts)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a line of JSX/TSX likely emitted the failing DOM element.
     */
    protected function isReactMatch(string $line, string $tagName, array $attributes, string $selector): bool
    {
        if (stripos($line, '<'.$tagName) === false) {
            return false;
        }

        foreach ($attributes as $attribute => $value) {
            if ($value && $this->lineContainsJsxAttribute($line, $attribute, $value)) {
                return true;
            }
        }

        preg_match_all('/[#\.]([a-zA-Z0-9\-_]+)/', $selector, $matches);
        $selectorParts = $matches[1] ?? [];

        foreach ($selectorParts as $part) {
            if (stripos($line, $part) !== false) {
                return true;
            }
        }

        return empty(array_filter($attributes)) && empty($selectorParts);
    }

    protected function lineContainsJsxAttribute(string $line, string $attribute, string $value): bool
    {
        $jsxAttribute = match ($attribute) {
            'class' => 'className',
            'for' => 'htmlFor',
            default => $attribute,
        };

        $quotedValue = preg_quote($value, '/');
        $quotedAttribute = preg_quote($jsxAttribute, '/');

        return (bool) preg_match('/\b'.$quotedAttribute.'\s*=\s*(["\'])'.$quotedValue.'\1/i', $line)
            || (bool) preg_match('/\b'.$quotedAttribute.'\s*=\s*\{\s*(["\'])'.$quotedValue.'\1\s*\}/i', $line);
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
