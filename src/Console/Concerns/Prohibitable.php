<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console\Concerns;

/**
 * Allow commands to be prohibited in production environments.
 *
 * Usage:
 *   GeneratePoliciesCommand::prohibit($app->isProduction());
 */
trait Prohibitable
{
    protected static bool $prohibited = false;

    /**
     * Prohibit the command from running.
     */
    public static function prohibit(bool $prohibit = true): void
    {
        static::$prohibited = $prohibit;
    }

    /**
     * Check if the command is prohibited.
     */
    public static function isProhibited(): bool
    {
        return static::$prohibited;
    }

    /**
     * Initialize the command and check prohibition.
     */
    protected function initializeProhibitable(): void
    {
        if (static::$prohibited) {
            $this->components->error('This command is prohibited in this environment.');

            exit(1);
        }
    }
}
