---
title: Filament Shield v4.x Feature Checklist
---

# Filament Shield v4.x Complete Feature Checklist

This document provides a comprehensive analysis of all features offered by [bezhanSalleh/filament-shield](https://github.com/bezhanSalleh/filament-shield) v4.x for Filament 4.x.

---

## 1. Installation & Setup

### Commands
| Feature | Shield Command | Description |
|---------|---------------|-------------|
| Interactive Setup | `shield:setup [--fresh] [--tenant=] [--force] [--starred]` | Interactive wizard for configuration |
| Panel Install | `shield:install {panel} [--tenant]` | Register plugin to a specific panel |
| Generate | `shield:generate [--all] [--option=] [--resource=] [--page=] [--widget=] [--exclude] [--ignore-existing-policies] [--panel=] [--relationships]` | Generate permissions/policies |
| Super Admin | `shield:super-admin [--user=] [--panel=] [--tenant=]` | Assign super admin role |
| Seeder | `shield:seeder [--generate] [--option=] [--force] [--with-users]` | Generate seeder file |
| Publish Resource | `shield:publish --panel={panel} [--cluster=] [--nested] [--force]` | Publish RoleResource |
| Translation | `shield:translation {locale} [--panel=] [--path=]` | Generate translation file |

### Setup Options
- [x] Fresh installation support
- [x] Tenant configuration during setup
- [x] Force mode to overwrite existing
- [x] Starred mode (unknown purpose)
- [x] Prohibitable commands (can be disabled)

---

## 2. Permission Configuration

### Permission Key Building
```php
// Config: filament-shield.php
'permissions' => [
    'separator' => ':',  // Options: ':', '_', '.', etc.
    'case' => 'pascal',  // Options: snake, kebab, pascal, camel, upper_snake, lower_snake
    'generate' => true,
],
```

**Supported Cases:**
| Case | Example |
|------|---------|
| snake | `view_any_user` |
| kebab | `view-any-user` |
| pascal | `ViewAnyUser` |
| camel | `viewAnyUser` |
| upper_snake | `VIEW_ANY_USER` |
| lower_snake | `view_any_user` |

### Custom Permission Key Builder
```php
// In AppServiceProvider
FilamentShield::buildPermissionKeyUsing(function (string $entity, string $affix, string $subject, string $case, string $separator) {
    return match(true) {
        is_subclass_of($entity, Resource::class) => ...,
        is_subclass_of($entity, Page::class) => ...,
        is_subclass_of($entity, Widget::class) => ...,
    };
});
```

---

## 3. Policy Generation

### Configuration
```php
'policies' => [
    'path' => app_path('Policies'),  // Custom policy path
    'merge' => true,                  // Merge with resource-specific methods
    'generate' => true,               // Enable policy generation
    'methods' => [
        'viewAny', 'view', 'create', 'update', 
        'delete', 'deleteAny', 'forceDelete', 
        'forceDeleteAny', 'restore', 'restoreAny', 'reorder'
    ],
],
```

### Features
- [x] Automatic policy generation
- [x] Custom policy path
- [x] Method merging with resource-specific methods
- [x] Single parameter methods support
- [x] Policy enforcement via traits

---

## 4. Resource Permissions

### Configuration
```php
'resources' => [
    'subject' => 'model',  // Options: 'model' or 'resource'
    'manage' => false,     // Group all into single 'manage' permission
    'exclude' => [
        // ShieldResource excluded by default
    ],
],
```

### Resource Subject Options
| Option | Description | Example |
|--------|-------------|---------|
| `model` | Uses model class basename | `User` from `UserResource` |
| `resource` | Uses resource class name | `UserResource` |

### Resource-Specific Permissions
Can be customized per-resource via policies section methods.

---

## 5. Page & Widget Permissions

### Pages Configuration
```php
'pages' => [
    'subject' => 'basename',  // Options: 'basename' or 'fqcn'
    'prefix' => 'page',       // Prefix for page permissions
    'exclude' => [
        // Pages to exclude from permission generation
    ],
],
```

### Widgets Configuration
```php
'widgets' => [
    'subject' => 'basename',
    'prefix' => 'widget',
    'exclude' => [
        // Widgets to exclude
    ],
],
```

### Permission Enforcement Traits
```php
// For Pages
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class MyPage extends Page
{
    use HasPageShield;
}

// For Widgets  
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class MyWidget extends Widget
{
    use HasWidgetShield;
}
```

**Trait Features:**
- [x] Auto-hide navigation if no permission
- [x] Block access if no permission
- [x] Cached permission lookups
- [x] Super admin bypass

---

## 6. Custom Permissions

### Configuration
```php
'custom_permissions' => [
    'access_api',
    'manage_settings',
    // Custom permissions visible in role form
],
```

### Features
- [x] Custom permission tab in role form
- [x] Configurable tab visibility

---

## 7. Super Admin Configuration

### Configuration
```php
'super_admin' => [
    'enabled' => true,
    'name' => 'super_admin',
    'define_via_gate' => false,  // Gate::before vs role with all permissions
    'intercept_gate' => 'before', // Options: 'before' or 'after'
],
```

### Super Admin Modes
| Mode | Description |
|------|-------------|
| Gate-based (`define_via_gate: true`) | Uses `Gate::before()` to bypass all checks |
| Permission-based (`define_via_gate: false`) | Assigns all permissions to super admin role |

---

## 8. Panel User Role

### Configuration
```php
'panel_user' => [
    'enabled' => true,
    'name' => 'panel_user',
],
```

### Features
- [x] Auto-create panel_user role
- [x] Auto-assign to new users (via `HasPanelShield` trait)
- [x] Basic panel access without specific permissions

### HasPanelShield Trait
```php
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;

class User extends Authenticatable
{
    use HasPanelShield;
    
    // Automatically handles canAccessPanel()
}
```

---

## 9. Multi-Tenancy Support

### Configuration
```php
'tenant_model' => null,  // Auto-detected during shield:setup
```

### Plugin Configuration
```php
FilamentShieldPlugin::make()
    ->scopeToTenant(true)
    ->tenantRelationshipName('organization')
    ->tenantOwnershipRelationshipName('owner');
```

### Features
- [x] Tenant-scoped roles/permissions
- [x] Central app mode (manage all tenants from one panel)
- [x] Tenant selector in seeder command
- [x] Tenant-aware super-admin creation

---

## 10. Shield Plugin Configuration

### Navigation
```php
FilamentShieldPlugin::make()
    ->navigationGroup(__('filament-shield::filament-shield.nav.group'))
    ->navigationLabel(__('filament-shield::filament-shield.nav.role.label'))
    ->navigationIcon('heroicon-o-shield-check')
    ->navigationSort(99);
```

### Labels & Localization
```php
FilamentShieldPlugin::make()
    ->modelLabel(__('filament-shield::filament-shield.resource.label.role'))
    ->pluralModelLabel(__('filament-shield::filament-shield.resource.label.roles'))
    ->localizePermissionLabels();  // Use translation keys for permission labels
```

### Global Search
```php
FilamentShieldPlugin::make()
    ->globalSearchKeys(['name', 'guard_name'])
    ->globalSearchResultAction(fn () => ...);
```

### Parent Resource
```php
FilamentShieldPlugin::make()
    ->parentResource(ParentResource::class);
```

### Layout Customization
```php
FilamentShieldPlugin::make()
    ->gridColumns(2)
    ->sectionColumnSpan(1)
    ->checkboxListColumns(2)
    ->resourceCheckboxListColumns(3);
```

### Tab Visibility
```php
FilamentShieldPlugin::make()
    ->resourcesTabEnabled(true)
    ->pagesTabEnabled(true)
    ->widgetsTabEnabled(true)
    ->customPermissionsTabEnabled(false);
```

### View Modes
```php
FilamentShieldPlugin::make()
    ->simpleResourcePermissionView();  // Flat checkbox list instead of grouped
```

---

## 11. Role Resource Features

### Configuration
```php
'shield_resource' => [
    'slug' => 'shield/roles',
    'show_model_path' => true,  // Show full model path in descriptions
    'cluster' => null,          // Cluster for resource grouping
    'tabs' => [
        'pages' => true,
        'widgets' => true,
        'resources' => true,
        'custom_permissions' => false,
    ],
],
```

### Form Components
- [x] Tabs for Resources/Pages/Widgets/Custom
- [x] Collapsible sections per resource
- [x] Select All toggle
- [x] Checkbox lists with configurable columns
- [x] Model path display in descriptions
- [x] Badge counts on tabs

---

## 12. Entity Discovery

### Configuration
```php
'discovery' => [
    'discover_all_resources' => false,  // Discover from all panels
    'discover_all_pages' => false,
    'discover_all_widgets' => false,
],
```

### Features
- [x] Auto-discover resources from panel
- [x] Auto-discover pages from panel
- [x] Auto-discover widgets from panel
- [x] Cross-panel discovery option
- [x] Cluster page filtering (excludes cluster pages)

---

## 13. Seeder Generation

### Command Options
```bash
shield:seeder [--generate] [--option=] [--force] [--with-users]
```

### Export Options
| Option | Description |
|--------|-------------|
| `permissions_via_roles` | Export permissions through role assignments |
| `direct_permissions` | Export standalone permissions |
| `all` | Export everything |

### User Export Features
- [x] Include users with roles
- [x] Include users with direct permissions
- [x] Password handling options (none/include/generate)
- [x] Random or constant password generation
- [x] Tenant-aware user export

---

## 14. Localization

### Supported Languages (30+)
| Language | Code | Language | Code |
|----------|------|----------|------|
| English | `en` | German | `de` |
| Arabic | `ar` | Spanish | `es` |
| French | `fr` | Italian | `it` |
| Japanese | `ja` | Korean | `ko` |
| Dutch | `nl` | Polish | `pl` |
| Portuguese (BR) | `pt_BR` | Portuguese (PT) | `pt_PT` |
| Russian | `ru` | Turkish | `tr` |
| Ukrainian | `uk` | Vietnamese | `vi` |
| Chinese (Simplified) | `zh_CN` | Chinese (Traditional) | `zh_TW` |
| Czech | `cs` | Slovakian | `sk` |
| Romanian | `ro` | Indonesian | `id` |
| Swedish | `sv` | Bengali | `bn` |
| Persian | `fa` | Filipino | `fil` |
| Norwegian Bokmål | `nb_NO` | More... | ... |

### Translation Command
```bash
php artisan shield:translation fr --panel=admin
```

---

## 15. Advanced Features

### About Command Integration
Shield registers with `php artisan about` to show version and configuration.

### Facade
```php
use BezhanSalleh\FilamentShield\Facades\FilamentShield;

FilamentShield::getResources();
FilamentShield::getPages();
FilamentShield::getWidgets();
FilamentShield::getCustomPermissions();
FilamentShield::getEntitiesPermissions();
FilamentShield::prohibitDestructiveCommands(true);
```

### Prohibitable Commands
```php
// In AppServiceProvider
FilamentShield::prohibitDestructiveCommands(true);
```

This prevents running destructive commands in production.

---

## 16. Role Policy Registration

### Configuration
```php
'register_role_policy' => true,
```

Automatically registers a policy for managing roles themselves.

---

## Feature Summary Checklist

### Core Features
- [x] Resource permission generation
- [x] Page permission generation  
- [x] Widget permission generation
- [x] Custom permissions support
- [x] Policy generation
- [x] Super admin role
- [x] Panel user role
- [x] Gate::before authorization
- [x] Multi-tenancy support
- [x] Central app mode
- [x] Seeder generation

### Commands (7 total)
- [x] `shield:setup`
- [x] `shield:install`
- [x] `shield:generate`
- [x] `shield:super-admin`
- [x] `shield:seeder`
- [x] `shield:publish`
- [x] `shield:translation`

### Traits (4 total)
- [x] `HasPageShield`
- [x] `HasWidgetShield`
- [x] `HasPanelShield`
- [x] `HasShieldFormComponents`

### UI/UX
- [x] Tabbed permission interface
- [x] Select All toggle
- [x] Collapsible sections
- [x] Badge counts
- [x] Model path display
- [x] Configurable grid layout
- [x] Simple vs detailed view mode
- [x] Localized permission labels

### Configuration Options
- [x] Permission separator customization
- [x] Permission case customization
- [x] Custom permission key builder
- [x] Resource subject (model vs resource)
- [x] Entity exclusions
- [x] Tab visibility
- [x] Layout columns
- [x] Navigation customization
- [x] Cluster support

### Missing Features in Shield
- [ ] User impersonation ❌
- [ ] Wildcard permissions ❌
- [ ] Instant search/filtering within tabs ❌
- [ ] Package grouping for permissions ❌
- [ ] Built-in User resource ❌
- [ ] Permission resource ❌
- [ ] Laravel Octane compatibility ❌
- [ ] OwnerContext integration ❌
- [ ] Config-based role/permission sync ❌
- [ ] Discover command with preview ❌

---

*Document generated for comparison with filament-authz.*
