<?php

namespace LensForLaravel\LensForLaravel\Services;

use LensForLaravel\LensForLaravel\DTOs\AiFixCodeContext;
use RuntimeException;

class AiFixContextExtractor
{
    protected const MAX_CONTEXT_BYTES = 6000;

    protected const MAX_SEARCH_LINES = 120;

    /** @var list<string> */
    protected array $voidElements = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    public function extract(string $content, int $lineNumber, string $htmlSnippet): AiFixCodeContext
    {
        $lines = preg_split('/(?<=\n)/', $content) ?: [];

        if ($lineNumber < 1 || $lineNumber > count($lines)) {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.source_line_missing'));
        }

        $targetStart = 0;
        for ($i = 0; $i < $lineNumber - 1; $i++) {
            $targetStart += strlen($lines[$i]);
        }
        $targetEnd = $targetStart + strlen($lines[$lineNumber - 1]);

        $renderedTag = $this->tagNameFrom($htmlSnippet);
        if ($renderedTag !== null) {
            $openingStart = $this->findNearbyOpeningTag($content, $lines, $lineNumber, $renderedTag);
            if ($openingStart !== null) {
                $context = $this->extractElement($content, $openingStart, $renderedTag);
                if ($context !== null) {
                    return $context;
                }
            }
        }

        $targetLine = substr($content, $targetStart, $targetEnd - $targetStart);
        if (preg_match('/<(?![\/?!])([a-zA-Z][a-zA-Z0-9:_-]*)\b/', $targetLine, $match, PREG_OFFSET_CAPTURE)) {
            $tagName = strtolower($match[1][0]);
            $context = $this->extractElement($content, $targetStart + $match[0][1], $tagName);
            if ($context !== null) {
                return $context;
            }
        }

        return new AiFixCodeContext(
            code: rtrim($targetLine, "\r\n"),
            startLine: $lineNumber,
            scope: 'source line',
        );
    }

    protected function tagNameFrom(string $htmlSnippet): ?string
    {
        return preg_match('/<(?![\/?!])([a-zA-Z][a-zA-Z0-9:_-]*)\b/', $htmlSnippet, $match)
            ? strtolower($match[1])
            : null;
    }

    /**
     * @param  list<string>  $lines
     */
    protected function findNearbyOpeningTag(string $content, array $lines, int $lineNumber, string $tagName): ?int
    {
        $firstLine = max(1, $lineNumber - 8);
        $lastLine = min(count($lines), $lineNumber + 2);
        $searchStart = 0;

        for ($i = 0; $i < $firstLine - 1; $i++) {
            $searchStart += strlen($lines[$i]);
        }

        $searchLength = 0;
        for ($i = $firstLine - 1; $i < $lastLine; $i++) {
            $searchLength += strlen($lines[$i]);
        }

        $slice = substr($content, $searchStart, $searchLength);
        preg_match_all(
            '/<(?!\/|!|\?)'.preg_quote($tagName, '/').'(?=[\s\/>])/i',
            $slice,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if (empty($matches[0])) {
            return null;
        }

        $targetOffset = 0;
        for ($i = 0; $i < $lineNumber - 1; $i++) {
            $targetOffset += strlen($lines[$i]);
        }

        $candidates = array_map(
            fn (array $match): int => $searchStart + $match[1],
            $matches[0],
        );

        usort($candidates, fn (int $left, int $right): int => abs($left - $targetOffset) <=> abs($right - $targetOffset));

        return $candidates[0];
    }

    protected function extractElement(string $content, int $openingStart, string $tagName): ?AiFixCodeContext
    {
        $openingEnd = $this->findTagEnd($content, $openingStart);
        if ($openingEnd === null) {
            return null;
        }

        $openingTag = substr($content, $openingStart, $openingEnd - $openingStart + 1);
        $startLine = substr_count(substr($content, 0, $openingStart), "\n") + 1;

        if (in_array($tagName, $this->voidElements, true) || preg_match('/\/\s*>$/', $openingTag)) {
            return new AiFixCodeContext($openingTag, $startLine, 'element');
        }

        $maxEnd = $this->offsetAtLine($content, $startLine + self::MAX_SEARCH_LINES);
        $cursor = $openingEnd + 1;
        $depth = 1;
        $tokenPattern = '/<\/?\s*'.preg_quote($tagName, '/').'(?=[\s\/>])/i';

        while ($cursor < $maxEnd && preg_match($tokenPattern, $content, $match, PREG_OFFSET_CAPTURE, $cursor)) {
            $tokenStart = $match[0][1];
            if ($tokenStart >= $maxEnd) {
                break;
            }

            $tokenEnd = $this->findTagEnd($content, $tokenStart);
            if ($tokenEnd === null || $tokenEnd >= $maxEnd) {
                break;
            }

            $token = substr($content, $tokenStart, $tokenEnd - $tokenStart + 1);
            if (preg_match('/^<\s*\//', $token)) {
                $depth--;
            } elseif (! preg_match('/\/\s*>$/', $token)) {
                $depth++;
            }

            if ($depth === 0) {
                $element = substr($content, $openingStart, $tokenEnd - $openingStart + 1);

                return strlen($element) <= self::MAX_CONTEXT_BYTES
                    ? new AiFixCodeContext($element, $startLine, 'element or component')
                    : new AiFixCodeContext($openingTag, $startLine, 'opening element');
            }

            $cursor = $tokenEnd + 1;
        }

        return new AiFixCodeContext($openingTag, $startLine, 'opening element');
    }

    protected function findTagEnd(string $content, int $start): ?int
    {
        $quote = null;
        $limit = min(strlen($content), $start + 3000);

        for ($i = $start; $i < $limit; $i++) {
            $character = $content[$i];

            if ($quote !== null) {
                if ($character === $quote && ($i === 0 || $content[$i - 1] !== '\\')) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '>') {
                return $i;
            }
        }

        return null;
    }

    protected function offsetAtLine(string $content, int $lineNumber): int
    {
        $offset = 0;
        for ($line = 1; $line < $lineNumber; $line++) {
            $newline = strpos($content, "\n", $offset);
            if ($newline === false) {
                return strlen($content);
            }
            $offset = $newline + 1;
        }

        return min($offset, strlen($content));
    }
}
