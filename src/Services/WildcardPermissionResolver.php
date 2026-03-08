<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

/**
 * Resolves wildcard permission patterns like 'orders.*' to match 'orders.view', 'orders.create', etc.
 */
class WildcardPermissionResolver
{
    /**
     * Check if a permission string is a wildcard pattern.
     */
    public function isWildcard(string $permission): bool
    {
        return str_contains($permission, '*');
    }

    /**
     * Check if a specific permission matches a wildcard pattern.
     */
    public function matches(string $wildcardPattern, string $permission): bool
    {
        if ($wildcardPattern === $permission) {
            return true;
        }

        if ($wildcardPattern === '*') {
            return true;
        }

        if (str_ends_with($wildcardPattern, '.*')) {
            $prefix = mb_substr($wildcardPattern, 0, -2);

            return str_starts_with($permission, $prefix . '.');
        }

        if (str_contains($wildcardPattern, '*')) {
            $pattern = $this->wildcardToRegex($wildcardPattern);

            return preg_match($pattern, $permission) === 1;
        }

        return false;
    }

    /**
     * Convert a wildcard pattern to a regex pattern.
     */
    protected function wildcardToRegex(string $wildcard): string
    {
        $escaped = preg_quote($wildcard, '/');
        $pattern = str_replace('\*', '[^.]+', $escaped);

        return '/^' . $pattern . '$/';
    }
}
