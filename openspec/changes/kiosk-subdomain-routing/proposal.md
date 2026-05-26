## Why

The app has migrated to a subdomain-per-tenant architecture (`acme.mango-app.test`), but the kiosk routes still embed the company slug in the URL path (`/kiosk/{company:slug}`). This creates a redundancy: the tenant is already resolved from the subdomain before the route is matched, yet the path still carries an explicit company identifier that duplicates that information and forces all kiosk URLs and their Wayfinder-generated TypeScript to carry a `company` argument.

## What Changes

- **New middleware** `EnsureTenantContext` — aborts 404 if no tenant is in context (guards kiosk routes on the apex domain).
- **Kiosk routes** lose the `{company:slug}` prefix segment; they become `/kiosk`, `/kiosk/lookup`, `/kiosk/clock-in`, etc., served exclusively on tenant subdomains.
- **`KioskController`** resolves the company from `TenantContext` (injected via constructor) instead of a route-bound `Company $company` parameter; all redirects drop the `['company' => $company->slug]` argument.
- **Wayfinder TS files** are regenerated — kiosk functions no longer require a `company` argument.
- **`Kiosk/Index.vue`** removes the `props.company.slug` argument from all six Wayfinder calls.
- **Kiosk feature tests** are updated to send requests from a tenant subdomain host (`HTTP_HOST: acme.mango-app.test`).

## Capabilities

### New Capabilities

- `kiosk-tenant-guard`: A dedicated `EnsureTenantContext` middleware that protects routes requiring an active tenant context, aborting 404 when accessed from the apex domain or any host where `IdentifyTenant` did not resolve a company.

### Modified Capabilities

- `tenant-subdomain-resolution`: The kiosk module now participates in subdomain-based tenant resolution — company identity comes from the subdomain, not the URL path.

## Impact

- **Routes**: `routes/web.php` — kiosk route group prefix and method signatures change.
- **Middleware**: `bootstrap/app.php` — new `tenant` alias registered.
- **Controller**: `app/Http/Controllers/KioskController.php` — constructor injection, all method signatures, all redirects.
- **Frontend**: `resources/js/pages/Kiosk/Index.vue` — six Wayfinder call sites.
- **Wayfinder generated files**: `resources/js/actions/App/Http/Controllers/KioskController.ts`, `resources/js/routes/kiosk/index.ts`, `resources/js/routes/kiosk/break/index.ts`.
- **Tests**: any existing kiosk feature tests need subdomain host headers.
- **No database migration required.**
- **No role changes** — kiosk remains public (no auth required).
- **Multi-tenant**: this change tightens tenant isolation by removing the path-level company identifier, relying entirely on the subdomain (already the authoritative tenant signal).

## Non-goals

- Changing the kiosk UI or user-facing behavior.
- Adding kiosk authentication beyond the existing document-number lookup flow.
- Migrating other non-kiosk routes to subdomain-only routing.
