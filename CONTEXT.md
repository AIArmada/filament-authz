---
title: Filament Authz Context
package: filament-authz
status: current
surface: filament
family: foundation
---

# Filament Authz Context

## Snapshot
- Composer: `aiarmada/filament-authz`
- Role: Filament authorization scopes, impersonation, and panel/page/widget authz surfaces.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `authz`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../authz/CONTEXT.md` when core roles, scopes, or impersonation state are involved
6. `../commerce-support/CONTEXT.md` when owner scope primitives are involved
7. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament authorization surfaces, panel glue, and impersonation UI behavior.
- Keep generic authorization in `authz` and shared owner primitives in `commerce-support`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
