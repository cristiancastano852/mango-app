## Context

The app uses `IdentifyTenant` (global web middleware) to resolve the tenant company from the request subdomain into a singleton `TenantContext`. This runs before any controller. The kiosk module currently duplicates that information in the URL path via `{company:slug}` route model binding, forcing every kiosk URL and its Wayfinder-generated TypeScript to carry a `company` argument. With the subdomain architecture fully in place, the path segment is redundant.

**Current flow:**
```
Request → IdentifyTenant (sets TenantContext) → Route match /kiosk/{company:slug} → 
  Company resolved again via route model binding → KioskController($company)
```

**Target flow:**
```
Request → IdentifyTenant (sets TenantContext) → Route match /kiosk → 
  EnsureTenantContext (abort 404 if no tenant) → KioskController reads from TenantContext
```

## Goals / Non-Goals

**Goals:**
- Remove `{company:slug}` from all kiosk route paths.
- Guard kiosk routes so they only resolve on tenant subdomains (not the apex domain).
- Have `KioskController` resolve the company exclusively from `TenantContext`.
- Update Wayfinder-generated TS and the Vue page to stop passing a company argument.
- Keep `kiosk_company_id` in session for defense-in-depth against wildcard cookie domain scenarios.

**Non-Goals:**
- Changing kiosk UI/UX or the employee lookup flow.
- Migrating any other route group to subdomain-only routing.
- Adding authentication to the kiosk module.

## Decisions

### Decision 1: Dedicated `EnsureTenantContext` middleware (not inline controller abort)

**Choice:** New `app/Http/Middleware/EnsureTenantContext.php` with a `tenant` alias, applied to the kiosk route group.

**Rationale:** Mirrors the existing `EnsureAdminHost` pattern exactly — the project uses route-group middleware aliases for host/context guards. An inline abort in the controller constructor would work but is less visible, harder to reuse, and diverges from the established pattern.

**Alternative considered:** Inline `abort_if(!$this->tenant->check(), 404)` in the controller constructor. Rejected because it hides the guard in the controller rather than at the routing layer.

### Decision 2: Inject `TenantContext` via constructor, not `app()` resolution

**Choice:** `public function __construct(private TenantContext $tenant) {}`

**Rationale:** Explicit constructor injection is the Laravel/project standard for service dependencies. It makes dependencies visible and testable (can be mocked or overridden in tests via `app()->instance()`).

### Decision 3: Keep `kiosk_company_id` in session

**Choice:** Retain the `kiosk_company_id` session key and validate it against `$this->tenant->get()->id` in `resolveKioskEmployee`.

**Rationale:** If `SESSION_DOMAIN` is configured as `.mango-app.test` (wildcard), a session cookie from `acme.` could technically reach `other.`. The session check is cheap and provides defense-in-depth. It costs nothing to keep.

### Decision 4: Regenerate Wayfinder TS via artisan command

**Choice:** Run `php artisan wayfinder:generate` after route changes; commit the regenerated files.

**Rationale:** Wayfinder is the project's established pattern for type-safe route references in Vue. Manually editing the generated TS files is fragile; the command is the canonical way to keep them in sync.

## Risks / Trade-offs

- **Broken kiosk URLs if accessed from apex domain** → Mitigated by `EnsureTenantContext` returning 404 before any controller logic runs.
- **Session leakage across subdomains** → Mitigated by retaining `kiosk_company_id` validation (Decision 3).
- **Wayfinder files out of sync** → Mitigated by running `wayfinder:generate` as an explicit task step and committing the output.
- **Existing kiosk tests using path-based company resolution** → Tests must be updated to set `HTTP_HOST` to a tenant subdomain; this is a required breaking test change, not a risk.

## Migration Plan

1. Create `EnsureTenantContext` middleware and register alias.
2. Update `routes/web.php` — change prefix and add middleware.
3. Refactor `KioskController` — constructor injection, remove `$company` params, update redirects.
4. Run `php artisan wayfinder:generate` — commit regenerated TS files.
5. Update `Kiosk/Index.vue` — remove company slug from Wayfinder calls.
6. Update feature tests — add subdomain host headers.
7. Run full test suite to confirm no regressions.

**Rollback:** Revert the PR. No database changes, so rollback is a clean git revert.

## Open Questions

_None — all decisions are resolved._
