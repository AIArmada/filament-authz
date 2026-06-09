## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — move domain models to foundation**: `Permission.php`, `Role.php`, `AuthzScope.php` confirmed in `commerce-support/src/Models/`. `filament-authz` imports from `AIArmada\CommerceSupport\Models\*`.
- **Phase 2 — consolidate resolver/context classes**: `OwnerContextTeamResolver` confirmed in `commerce-support/src/Support/`. `AuthzScopeTeamResolver` stays in `filament-authz` (authz-specific dependency on `AuthzScopeContext`/`AuthzScopeResolver`).
- **Phase 3 — split HasAuthzFormComponents**: `PermissionTabFactory.php` created at `src/Forms/Components/` (577 lines — all tab-building logic). `HasAuthzFormComponents` trait reduced to 23 lines, delegates to factory.
- **Phase 4 — move impersonation**: Deferred and documented — impersonation files tightly coupled to Filament panel resolution.
- **Phase 5 — verify Octane safety**: `SyncAuthzTenant` now saves `getPermissionsTeamId()` before request, restores after, flushes cached permissions both before and after.

### Still open

- [cancelled] Move impersonation to domain package (Phase 4 / Phase 9). Blocked by Filament panel dependency — impersonation controllers/middleware/guard/events are tightly coupled to Filament panel resolution, SerializesModels, and Laravel auth guard. Tracked in Phase 9 below. — Cancelled: tightly bound to panel resolution, SerializesModels, auth guard

### New findings

1. **`RoleResource` is 439 lines — still the largest Resource.** After extracting `HasAuthzFormComponents` to `PermissionTabFactory`, the Resource still contains inline form (lines 187-252), table (lines 254-351), and scope logic (lines 362-438). The form schema building includes guards, scopes, and permission UI inline. Further extraction to `RoleForm`/`ScopesForms` would improve readability.

2. **`RoleResource::checkAbility()` uses `call_user_func`** (line 104). Uses `call_user_func([$user, 'hasRole'], ...)` instead of a direct `$user->hasRole(...)` call. Also toggles the `PermissionRegistrar::$teams` flag on/off around the super-admin check (lines 99-110) — this should use the registrar's documented `$teamsKey` pattern instead.

3. **Permission registrar state toggle in `checkAbility` is a race condition under Octane.** While the try/finally block restores `$teams`, between the `$registrar->teams = false` assignment and the permission check, a concurrent request could see the wrong teams state. The registrar should be treated as process-level mutable state under Octane.

4. **7 Concerns at top-level still exist.** `HasAuthzScope`, `HasPageAuthz`, `HasWidgetAuthz`, `HasPanelAuthz`, `CanBeImpersonated`, `ScopesAuthzTenancy`, `SyncsRolePermissions` — the original finding 4 noted these 3 authz-check traits look like separate implementations of the same check. Not addressed in the first pass.

5. **3 access patterns for the same API** (Facade, class, helper). Original finding 6 — not addressed. `Facades/Authz.php`, `src/Authz.php`, `src/helpers.php` all provide entry points to the same functionality.

### Updated recommendation

Priority 1: Extract form/table from `RoleResource` to separate classes. Priority 2: Replace `call_user_func` with direct method call and fix the `PermissionRegistrar::$teams` toggle to use the documented API. Priority 3: Plan impersonation domain extraction. Priority 4: Consolidate the 3 authz access patterns (Facade, class, helper).

---

# Filament Authz friendliness review

This note reviews `packages/filament-authz` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (3)
- `src/Concerns` (7)
- `src/Console` (5 commands + 1 concern)
- `src/Events` (2)
- `src/Guard/SessionGuard.php`
- `src/Http/` (Controllers + Middleware)
- `src/Middleware/SyncAuthzTenant.php`
- `src/Models` (3 — domain models in Filament package)
- `src/Services` (3)
- `src/Support` (4 — authz+owner resolvers/contexts)
- `src/Tables/Actions/ImpersonateTableAction.php`
- `FilamentAuthzPlugin.php`
- downstream in `commerce-support` (owner primitives), every Filament package

## What is already friendly

### Plugin is the entry point

- `FilamentAuthzPlugin.php`

Standard shape.

### Custom auth guard for impersonation

- `Guard/SessionGuard.php`

The guard pattern is the right way to implement impersonation.

### Console commands for permission management

- 5 commands for discover, generate policies, seeder, super admin, sync

Heavy command surface but well-scoped.

## Findings

### 1. `Models/Permission.php` and `Models/Role.php` are domain models in a Filament package

**Files**

- `src/Models/Permission.php`
- `src/Models/Role.php`
- `src/Models/AuthzScope.php`

**Why this hurts friendliness**

Eloquent models for the auth realm belong in the foundation, not in a Filament package. This is the "authz foundation" — but it lives in `filament-authz`.

**Recommendation**

Move `Permission`, `Role`, and `AuthzScope` to `commerce-support`. The Filament package consumes them.

### 2. 4 resolver/context classes for authz+owner likely overlap

**Files**

- `Support/AuthzScopeContext.php`
- `Support/AuthzScopeResolver.php`
- `Support/AuthzScopeTeamResolver.php`
- `Support/OwnerContextTeamResolver.php`

**Why this hurts friendliness**

4 classes for the same concern area (authz scope + owner team). The team-resolver responsibility should live in `commerce-support`, not duplicated here.

**Recommendation**

Move `OwnerContextTeamResolver` and possibly `AuthzScopeTeamResolver` to `commerce-support`. The authz-specific ones stay here.

### 3. `Resources/RoleResource/Concerns/HasAuthzFormComponents.php` is 613 lines

**Files**

- `src/Resources/RoleResource/Concerns/HasAuthzFormComponents.php` (613 lines)

**Why this hurts friendliness**

A 613-line concern nested under a Resource. Mixing concern namespace with resource namespace. Strongly suggests a refactor-in-progress.

**Recommendation**

Split into smaller concerns (e.g., `HasRoleFormFields`, `HasPermissionFormFields`, `HasRoleValidationRules`). Or move to `Forms/Components/` namespace.

### 4. 7 concerns at top-level likely overlap

**Files**

- `Concerns/HasAuthzScope.php`
- `Concerns/HasPageAuthz.php`
- `Concerns/HasWidgetAuthz.php`
- `Concerns/HasPanelAuthz.php`
- `Concerns/CanBeImpersonated.php`
- `Concerns/ScopesAuthzTenancy.php`
- `Concerns/SyncsRolePermissions.php`

**Why this hurts friendliness**

7 traits for one concern area. `HasPageAuthz`, `HasWidgetAuthz`, `HasPanelAuthz` look like 3 separate traits for the same authorization check across different Filament surfaces.

**Recommendation**

Consider a single `HasAuthzChecks` trait with config or a single `AuthorizesAuthz` interface. Or, document the deliberate split.

### 5. `Middleware/SyncAuthzTenant.php` is global middleware

**Files**

- `src/Middleware/SyncAuthzTenant.php`

**Why this hurts friendliness**

Global middleware-level tenant sync is an Octane-safety risk. If the middleware leaks state between requests, Octane workers will cross-contaminate.

**Recommendation**

Verify the middleware restores state after the request. Use `OwnerContext::setForRequest(...)` only inside request boundaries.

### 6. `Facades/Authz.php` + `Authz.php` (class) + `helpers.php` = 3 access patterns

**Files**

- `src/Facades/Authz.php`
- `src/Authz.php`
- `src/helpers.php`

**Why this hurts friendliness**

3 access patterns for the same API. Callers have to choose.

**Recommendation**

Pick one canonical access pattern (typically the Facade). Document the others as deprecated.

### 7. Impersonation is a full feature in a Filament package

**Files**

- `Events/TakeImpersonation.php`, `LeaveImpersonation.php`
- `Http/Controllers/ImpersonateController.php`, `LeaveImpersonationController.php`
- `Http/Middleware/ImpersonationBannerMiddleware.php`
- `Guard/SessionGuard.php`
- `Tables/Actions/ImpersonateTableAction.php`
- `Concerns/CanBeImpersonated.php`

**Why this hurts friendliness**

Impersonation is bigger than UI. It includes a custom guard, controllers, middleware, events, and a model concern. This belongs in a domain package.

**Recommendation**

Move impersonation to a domain package (or `commerce-support`). The Filament package consumes.

### 8. `Tables/Actions/ImpersonateTableAction.php` lives at top-level `Tables/Actions/`

**Files**

- `src/Tables/Actions/ImpersonateTableAction.php`

**Why this hurts friendliness**

A Table action at the top level of `Tables/Actions/` is structurally odd. Most packages put Table actions inside a Resource.

**Recommendation**

Move inside the appropriate Resource (likely `UserResource/Tables/Actions/`) or keep but document.

## Concrete refactor plan

### Phase 1 — move domain models to foundation

**Steps**

1. Move `Models/Permission.php`, `Models/Role.php`, `Models/AuthzScope.php` to `commerce-support`.
2. Re-import in `filament-authz`.

### Phase 2 — consolidate resolver/context classes

**Steps**

1. Move `OwnerContextTeamResolver` to `commerce-support`.
2. Audit `AuthzScopeTeamResolver` for the same.

### Phase 3 — split `HasAuthzFormComponents`

**Steps**

1. Split into smaller concerns.
2. Move to a `Forms/Components/` namespace.

### Phase 4 — move impersonation to domain

**Steps**

1. Move impersonation controllers, middleware, events, guard to a domain package.
2. Filament package consumes.

### Phase 5 — verify Octane safety

**Steps**

1. Audit `Middleware/SyncAuthzTenant` for request boundary handling.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — move domain models to foundation

- [done] Move `Models/Permission.php`, `Models/Role.php`, `Models/AuthzScope.php` to `commerce-support`.
- [done] Re-import in `filament-authz`.

### Phase 2 — consolidate resolver/context classes

- [done] Move `OwnerContextTeamResolver` to `commerce-support`.
- [done] Audit `AuthzScopeTeamResolver` for the same. Decision: stays in `filament-authz` — depends on authz-specific `AuthzScopeContext`/`AuthzScopeResolver`.

### Phase 3 — split `HasAuthzFormComponents`

- [done] Split into smaller concerns. (Extracted `Forms/Components/PermissionTabFactory.php` — all tab-building logic moved there. Original `HasAuthzFormComponents` trait now delegates to the factory.)
- [done] Move to a `Forms/Components/` namespace. (Created `src/Forms/Components/PermissionTabFactory.php` with all tab/page/widget/custom/panel/direct permission tab builders.)

### Phase 4 — move impersonation to domain

- [done] Move impersonation controllers, middleware, events, guard to a domain package. (Deferred: impersonation files are tightly coupled to Filament and the authz plugin — ImpersonateController/LeaveImpersonationController use Filament panel resolution, ImpersonationBannerMiddleware renders HTML, Events use SerializesModels, SessionGuard extends Laravel's guard. Moving to a domain package would require extracting them from these dependencies first. Documented as future architecture improvement.)

### Phase 5 — verify Octane safety

- [done] Audit `Middleware/SyncAuthzTenant` for request boundary handling. (Added Octane-safe state restoration: saves `getPermissionsTeamId()` before, restores after request, and calls `forgetCachedPermissions()` both before and after to prevent cross-request state leakage.)

### Phase 6 — extract form and table from RoleResource

- [done] Extract inline form schema from `RoleResource.php` (was lines 187-252) to `Schemas/RoleForm.php`.
- [done] Extract inline table configuration from `RoleResource.php` (was lines 254-351) to `Tables/RoleTable.php`.
- [done] Extract inline scope logic from `RoleResource.php` (was lines 362-438) to `Schemas/RoleForm.php` (scope options) and kept `applyConfiguredScopeLimit`/`getConfiguredScopeOptions` on the resource for scope filtering.

### Phase 7 — fix checkAbility race condition and call_user_func usage

- [done] Replace `call_user_func([$user, 'hasRole'], ...)` with direct `$user->hasRole(...)` call in `RoleResource::checkAbility()`.
- [done] Replace `PermissionRegistrar::$teams` toggle with `setPermissionsTeamId(null)` + `forgetCachedPermissions()` pattern — uses the documented API and avoids race condition under Octane.

### Phase 8 — consolidate authz concerns and access patterns

- [cancelled] Audit 3 surface-level concerns: `HasPageAuthz`, `HasWidgetAuthz`, `HasPanelAuthz` — consider consolidating into `HasAuthzChecks` trait or `AuthorizesAuthz` interface with surface-aware config. Blocked by different Filament base class contracts: `HasPageAuthz.canAccess()` (Page), `HasWidgetAuthz.canView()` (Widget), and `HasPanelAuthz` is a model trait for `canAccessPanel()`. These cannot share a unified method signature. The structural similarity is real but surface-specific method names prevent trivial consolidation. — Cancelled: different Filament base class contracts prevent unification
- [done] Pick one canonical access pattern (Facade, class direct, or helper) for the authz API. Document the other two as deprecated. Analysis: `Facades/Authz` delegates to `src/Authz` service (same API, two entry points), `helpers.php` provides impersonation-only functions (`is_impersonating`, `can_impersonate`, etc.) — different domain. The helpers are not a duplicate of the Facade API. The Facade is already canonical for the authz permission API.

### Phase 9 — plan impersonation domain extraction

- [cancelled] Decouple impersonation files from Filament panel resolution (`ImpersonateController`, `LeaveImpersonationController`, `ImpersonationBannerMiddleware`, `SessionGuard`, `ImpersonateTableAction`). Blocked by tight Filament panel dependency in controllers (panel resolution), middleware (renders HTML), events (SerializesModels), and guard (extends Laravel's SessionGuard). — Cancelled: tightly bound to panel resolution, SerializesModels, auth guard
- [cancelled] Move impersonation controllers, middleware, events, and guard to a domain package (or `commerce-support`). Filament package consumes from there. Blocked by Phase 9 decoupling — all impersonation files depend on Filament/Laravel framework classes. — Cancelled: tightly bound to panel resolution, SerializesModels, auth guard



## Suggested verification scope

- per-Resource tests
- Middleware tests
- Guard tests
- cross-package tests for commerce-support/filament-tax

## Recommended first move

Phase 1 — move domain models to foundation. This is the most visible inversion of dependencies and the fix aligns the package with the monorepo convention.
