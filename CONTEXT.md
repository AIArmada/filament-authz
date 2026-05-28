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
- Related: `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../commerce-support/CONTEXT.md` when owner scope or shared authz primitives are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament authorization surfaces, panel glue, and impersonation UI behavior.
- Keep shared contracts and owner primitives in `commerce-support`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
