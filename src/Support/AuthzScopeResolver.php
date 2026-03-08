<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\FilamentAuthz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;

final class AuthzScopeResolver
{
    public static function resolveId(mixed $scope): string | int | null
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof AuthzScope) {
            return $scope->getKey();
        }

        if ($scope instanceof Model) {
            $scopeableType = $scope->getMorphClass();
            $scopeableId = $scope->getKey();

            if ($scopeableType === '' || $scopeableId === null) {
                return null;
            }

            $label = null;

            if (method_exists($scope, 'getAuthzScopeLabel')) {
                $label = $scope->getAuthzScopeLabel();
            }

            $query = AuthzScope::query()->where([
                'scopeable_type' => $scopeableType,
                'scopeable_id' => $scopeableId,
            ]);

            if (! config('filament-authz.authz_scopes.auto_create', true)) {
                return $query->value('id');
            }

            $authzScope = AuthzScope::query()->firstOrCreate(
                [
                    'scopeable_type' => $scopeableType,
                    'scopeable_id' => $scopeableId,
                ],
                [
                    'label' => $label,
                ],
            );

            if ($label !== null && $authzScope->label !== $label) {
                $authzScope->forceFill(['label' => $label])->save();
            }

            return $authzScope->getKey();
        }

        if (is_string($scope) || is_int($scope)) {
            return $scope;
        }

        return null;
    }
}
