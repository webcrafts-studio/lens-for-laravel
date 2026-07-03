<?php

namespace LensForLaravel\LensForLaravel\Services;

use InvalidArgumentException;

class InteractionScriptParser
{
    protected const MAX_STATES = 10;

    protected const MAX_ACTIONS = 30;

    protected const MAX_LABEL_LENGTH = 80;

    protected const MAX_SELECTOR_LENGTH = 500;

    protected const MAX_VALUE_LENGTH = 1000;

    protected const MAX_WAIT_MS = 5000;

    /**
     * @return array<int, array{label: string, actions: array<int, array<string, mixed>>}>
     */
    public function parse(string $script): array
    {
        $states = [];
        $current = null;
        $actionCount = 0;

        foreach (preg_split('/\r\n|\r|\n/', $script) ?: [] as $index => $rawLine) {
            $lineNumber = $index + 1;
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }

            if ($this->isStateLine($line)) {
                if ($current !== null) {
                    $states[] = $current;
                }

                $current = [
                    'label' => $this->parseStateLabel($line, $lineNumber),
                    'actions' => [],
                ];

                if (count($states) + 1 > self::MAX_STATES) {
                    throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.max_states', ['max' => self::MAX_STATES]));
                }

                continue;
            }

            if ($current === null) {
                throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.state_before_actions', ['line' => $lineNumber]));
            }

            $current['actions'][] = $this->parseAction($line, $lineNumber);
            $actionCount++;

            if ($actionCount > self::MAX_ACTIONS) {
                throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.max_actions', ['max' => self::MAX_ACTIONS]));
            }
        }

        if ($current !== null) {
            $states[] = $current;
        }

        if (empty($states)) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.at_least_one_state'));
        }

        return $states;
    }

    protected function isStateLine(string $line): bool
    {
        return (bool) preg_match('/^state\s*:/i', $line)
            || (bool) preg_match('/^state\s+["\']?.+/i', $line);
    }

    protected function parseStateLabel(string $line, int $lineNumber): string
    {
        $label = preg_replace('/^state\s*:?\s*/i', '', $line);
        $label = $this->unquote(trim((string) $label));

        if ($label === '') {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.empty_label', ['line' => $lineNumber]));
        }

        if (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.label_too_long', ['line' => $lineNumber]));
        }

        return $label;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseAction(string $line, int $lineNumber): array
    {
        [$command, $arguments] = $this->splitCommand($line, $lineNumber);
        $command = strtolower($command);

        return match ($command) {
            'click' => ['type' => 'click', 'selector' => $this->requireSelector($arguments, $lineNumber)],
            'check' => ['type' => 'check', 'selector' => $this->requireSelector($arguments, $lineNumber)],
            'uncheck' => ['type' => 'uncheck', 'selector' => $this->requireSelector($arguments, $lineNumber)],
            'type' => $this->parseSelectorValueAction('type', $arguments, $lineNumber),
            'select' => $this->parseSelectorValueAction('select', $arguments, $lineNumber),
            'wait' => ['type' => 'wait', 'ms' => $this->parseWait($arguments, $lineNumber)],
            default => throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.unsupported_action', ['line' => $lineNumber, 'action' => $command])),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitCommand(string $line, int $lineNumber): array
    {
        if (! preg_match('/^([a-z]+)(?::|\s)\s*(.*)$/i', $line, $matches)) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.action_format', ['line' => $lineNumber]));
        }

        return [$matches[1], trim($matches[2])];
    }

    protected function parseSelectorValueAction(string $type, string $arguments, int $lineNumber): array
    {
        [$selector, $value] = $this->splitSelectorValue($arguments, $lineNumber);

        return [
            'type' => $type,
            'selector' => $selector,
            'value' => $value,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitSelectorValue(string $arguments, int $lineNumber): array
    {
        foreach (['=>', '|'] as $delimiter) {
            if (str_contains($arguments, $delimiter)) {
                [$selector, $value] = array_map('trim', explode($delimiter, $arguments, 2));

                return [
                    $this->requireSelector($selector, $lineNumber),
                    $this->requireValue($value, $lineNumber),
                ];
            }
        }

        if (preg_match('/^(.+?)\s+["\'](.+)["\']$/', $arguments, $matches)) {
            return [
                $this->requireSelector($matches[1], $lineNumber),
                $this->requireValue($matches[2], $lineNumber),
            ];
        }

        throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.selector_value_format', ['line' => $lineNumber]));
    }

    protected function requireSelector(string $selector, int $lineNumber): string
    {
        $selector = $this->unquote(trim($selector));

        if ($selector === '') {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.selector_empty', ['line' => $lineNumber]));
        }

        if (mb_strlen($selector) > self::MAX_SELECTOR_LENGTH) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.selector_too_long', ['line' => $lineNumber]));
        }

        return $selector;
    }

    protected function requireValue(string $value, int $lineNumber): string
    {
        $value = $this->unquote(trim($value));

        if ($value === '') {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.value_empty', ['line' => $lineNumber]));
        }

        if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.value_too_long', ['line' => $lineNumber]));
        }

        return $value;
    }

    protected function parseWait(string $arguments, int $lineNumber): int
    {
        $arguments = trim($arguments);

        if (! preg_match('/^\d+$/', $arguments)) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.wait_milliseconds', ['line' => $lineNumber]));
        }

        $ms = (int) $arguments;
        if ($ms < 0 || $ms > self::MAX_WAIT_MS) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.interaction_errors.wait_range', ['line' => $lineNumber, 'max' => self::MAX_WAIT_MS]));
        }

        return $ms;
    }

    protected function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
