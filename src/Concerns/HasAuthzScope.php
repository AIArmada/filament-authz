<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @mixin Model
 *
 * @property-read AuthzScope|null $authzScope
 */
trait HasAuthzScope
{
    public static function bootHasAuthzScope(): void
    {
        static::created(function ($model): void {
            $model->ensureAuthzScope();
        });

        static::updated(function ($model): void {
            $model->syncAuthzScopeLabel();
        });

        static::deleted(function ($model): void {
            $model->authzScope()?->delete();
        });
    }

    /**
     * @return MorphOne<AuthzScope, $this>
     */
    public function authzScope(): MorphOne
    {
        return $this->morphOne(AuthzScope::class, 'scopeable');
    }

    public function ensureAuthzScope(): AuthzScope
    {
        /** @var AuthzScope $authzScope */
        $authzScope = $this->authzScope()->firstOrCreate([], [
            'label' => $this->getAuthzScopeLabel(),
        ]);

        return $authzScope;
    }

    public function syncAuthzScopeLabel(): void
    {
        $scope = $this->authzScope;
        $label = $this->getAuthzScopeLabel();

        if (! $scope instanceof AuthzScope || $label === $scope->label) {
            return;
        }

        $scope->forceFill(['label' => $label])->save();
    }

    public function getAuthzScopeLabel(): string
    {
        $label = (string) $this->getKey();
        $name = $this->getAttribute('name');

        if (is_string($name) && $name !== '') {
            $label = $name;
        }

        return class_basename($this) . ': ' . $label;
    }
}
