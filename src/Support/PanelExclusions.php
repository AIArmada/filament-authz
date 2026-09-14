<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use Filament\Panel;

/**
 * Resolve entity exclusions for a panel.
 *
 * An explicit per-panel fluent setting wins; otherwise global config
 * applies. This keeps multi-panel apps isolated: unlike the old
 * last-registered-panel-wins global writes, one panel's exclusions never
 * leak into another panel's discovery.
 */
final class PanelExclusions
{
    /**
     * @param  'resources'|'pages'|'widgets'|'panels'  $type
     * @return list<string>
     */
    public static function resolve(?Panel $panel, string $type): array
    {
        $override = match ($type) {
            'resources' => FilamentAuthzPlugin::resolveForPanel($panel)?->getExcludedResourcesOverride(),
            'pages' => FilamentAuthzPlugin::resolveForPanel($panel)?->getExcludedPagesOverride(),
            'widgets' => FilamentAuthzPlugin::resolveForPanel($panel)?->getExcludedWidgetsOverride(),
            'panels' => FilamentAuthzPlugin::resolveForPanel($panel)?->getExcludedPanelsOverride(),
            default => null,
        };

        if ($override !== null) {
            return $override;
        }

        $configured = config("filament-authz.{$type}.exclude", []);

        return is_array($configured) ? array_values($configured) : [];
    }
}
