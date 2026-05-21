<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

final class AuthzScopeContext
{
    private bool $hasOverride = false;

    private string | int | null $scopeId = null;

    private static function instance(): self
    {
        return app(self::class);
    }

    public static function resolve(): string | int | null
    {
        $context = self::instance();

        return $context->hasOverride ? $context->scopeId : null;
    }

    public static function hasOverride(): bool
    {
        return self::instance()->hasOverride;
    }

    public static function set(string | int | null $scopeId): void
    {
        $context = self::instance();
        $context->scopeId = $scopeId;
        $context->hasOverride = true;
    }

    public static function clear(): void
    {
        $context = self::instance();
        $context->scopeId = null;
        $context->hasOverride = false;
    }

    public static function withScope(string | int | null $scopeId, callable $callback): mixed
    {
        $context = self::instance();
        $previousId = $context->scopeId;
        $previousHasOverride = $context->hasOverride;

        $context->scopeId = $scopeId;
        $context->hasOverride = true;

        try {
            return $callback();
        } finally {
            $context->scopeId = $previousId;
            $context->hasOverride = $previousHasOverride;
        }
    }
}
