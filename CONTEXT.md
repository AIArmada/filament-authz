---
title: Filament Authz Context
package: filament-authz
status: current
surface: filament
family: foundation
keywords:
  - filament
  - roles-ui
  - impersonation
  - discovery
---

# Filament Authz Context

## Snapshot
- Composer: `aiarmada/filament-authz`
- Role: Filament v5 roles/permissions/users UI, discovery, impersonation. (Permission math lives in authz core.)
- Triggers: filament, roles-ui, impersonation, discovery
- Search first: `src/Resources, src/Services, config, docs`
- Related: `commerce-support`, `authz`
- Paired: `authz` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../authz/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `authz`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `authz` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Admin UI for roles/users/impersonation.
- Skip when: Permission resolution — see authz.
- Owner/security: Spatie-teams scoping, not HasOwner.

## Key surfaces
- Resources: `PermissionResource`, `RoleResource`, `UserResource`
- Actions/Services: `Actions/ImpersonateAction`, `Actions/LeaveImpersonationAction`, `Services/EntityDiscoveryService`, `Support/UserAuthzForm`
- Config `filament-authz.php`: `scoped_to_tenant`, `central_app`, `resources`, `pages`, `widgets`, `panels`, `navigation`, `role_resource`, `user_resource`, `impersonate`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-multi-panel.md`, `06-cli-reference.md`, `07-impersonation.md`
