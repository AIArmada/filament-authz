<?php

declare(strict_types=1);
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    'guards' => ['web', 'api'],

    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    'scoped_to_tenant' => true,

    'central_app' => false,

    'resources' => [
        'subject' => 'model',
        'actions' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
        'extra_actions' => [],
        'action_labels' => [],
        'exclude' => [],
    ],

    'pages' => [
        'prefix' => 'page',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    'widgets' => [
        'prefix' => 'widget',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    'panels' => [
        'prefix' => 'panel',
        'exclude' => [],
    ],

    'navigation' => [
        'register' => true,
        'group' => 'Authz',
        'sort' => 99,
        'label' => null,
        'badge' => null,
        'badge_color' => null,
        'parent_item' => null,
        'cluster' => null,
        'icons' => [
            'roles' => 'heroicon-o-shield-check',
            'roles_active' => null,
            'permissions' => 'heroicon-o-key',
        ],
    ],

    'role_resource' => [
        'slug' => 'authz/roles',
        'scope_options' => null,
        'tabs' => [
            'resources' => true,
            'pages' => true,
            'widgets' => true,
            'custom_permissions' => true,
            'direct_permissions' => true,
            'panels' => true,
        ],
        'grid_columns' => 2,
        'checkbox_columns' => 3,
        'section_column_span' => 1,
    ],

    'user_resource' => [
        'enabled' => true,
        'auto_register' => true,
        'model' => null,
        'slug' => 'authz/users',
        'navigation' => [
            'group' => 'Authz',
            'sort' => 98,
            'icon' => 'heroicon-o-user-group',
        ],
        'form' => [
            'fields' => ['name', 'email', 'password'],
            'roles' => true,
            'role_scope_mode' => 'all',
            'permissions' => true,
        ],
    ],

    'impersonate' => [
        'enabled' => true,
    ],
];
