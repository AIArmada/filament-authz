<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Console\SuperAdminCommand;
use AIArmada\FilamentAuthz\Console\SyncAuthzCommand;

/**
 * Utility class for prohibiting destructive commands in production.
 *
 * Usage in AppServiceProvider::boot():
 *   CommandProhibitor::prohibitDestructiveCommands($this->app->isProduction());
 */
final class CommandProhibitor
{
    /**
     * Prohibit all destructive Authz commands.
     */
    public static function prohibitDestructiveCommands(bool $prohibit = true): void
    {
        GeneratePoliciesCommand::prohibit($prohibit);
        SeederCommand::prohibit($prohibit);
        SuperAdminCommand::prohibit($prohibit);
        SyncAuthzCommand::prohibit($prohibit);
    }
}
