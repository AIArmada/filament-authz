<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Support\Str;

/**
 * Builds permission keys with configurable case and separator.
 *
 * Features: Supports multiple cases, consistent formatting.
 */
class PermissionKeyBuilder
{
    /**
     * Build a permission key from subject and action.
     */
    public function build(string $subject, string $action): string
    {
        $separator = $this->getSeparator();
        $case = $this->getCase();

        $formattedSubject = $this->formatCase($subject, $case);
        $formattedAction = $this->formatCase($action, $case);

        return $formattedSubject . $separator . $formattedAction;
    }

    /**
     * Get the configured separator.
     */
    public function getSeparator(): string
    {
        return (string) config('filament-authz.permissions.separator', '.');
    }

    /**
     * Get the configured case.
     */
    public function getCase(): string
    {
        return (string) config('filament-authz.permissions.case', 'kebab');
    }

    /**
     * Format a string according to the specified case.
     */
    public function formatCase(string $value, string $case): string
    {
        return match ($case) {
            'snake' => Str::snake($value),
            'kebab' => Str::kebab($value),
            'camel' => Str::camel($value),
            'pascal' => Str::studly($value),
            'upper_snake' => Str::upper(Str::snake($value)),
            'lower' => Str::lower($value),
            default => Str::kebab($value),
        };
    }
}
