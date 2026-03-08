<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

final class AuthzScopeContext
{
    private static bool $hasOverride = false;

    private static string | int | null $scopeId = null;

    public static function resolve(): string | int | null
    {
        return self::$hasOverride ? self::$scopeId : null;
    }

    public static function set(string | int | null $scopeId): void
    {
        self::$scopeId = $scopeId;
        self::$hasOverride = true;
    }

    public static function clear(): void
    {
        self::$scopeId = null;
        self::$hasOverride = false;
    }

    public static function withScope(string | int | null $scopeId, callable $callback): mixed
    {
        $previousId = self::$scopeId;
        $previousHasOverride = self::$hasOverride;

        self::$scopeId = $scopeId;
        self::$hasOverride = true;

        try {
            return $callback();
        } finally {
            self::$scopeId = $previousId;
            self::$hasOverride = $previousHasOverride;
        }
    }
}
