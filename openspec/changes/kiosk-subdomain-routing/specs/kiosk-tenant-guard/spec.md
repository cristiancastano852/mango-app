## ADDED Requirements

### Requirement: Kiosk routes require an active tenant context
The system SHALL reject kiosk route access with HTTP 404 when no tenant company has been resolved from the request subdomain. This guard SHALL run before any kiosk controller logic executes.

#### Scenario: Valid tenant subdomain grants access
- **WHEN** a request arrives at `/kiosk` with host `acme.mango-app.test` and `acme` resolves to an existing company
- **THEN** `IdentifyTenant` sets the company in `TenantContext` and the request proceeds to the controller

#### Scenario: Apex domain is rejected
- **WHEN** a request arrives at `/kiosk` with host `mango-app.test` (no subdomain)
- **THEN** the system returns HTTP 404

#### Scenario: Unknown subdomain is rejected
- **WHEN** a request arrives at `/kiosk` with host `unknown-company.mango-app.test` and no company has that slug
- **THEN** `IdentifyTenant` aborts with HTTP 404 before the guard middleware is reached

#### Scenario: Admin subdomain is rejected
- **WHEN** a request arrives at `/kiosk` with host `admin.mango-app.test`
- **THEN** `IdentifyTenant` treats this as the admin host (not a tenant) and `EnsureTenantContext` aborts with HTTP 404

### Requirement: Kiosk URLs contain no company identifier in the path
The system SHALL serve kiosk routes at paths without a company slug segment: `/kiosk`, `/kiosk/lookup`, `/kiosk/clock-in`, `/kiosk/clock-out`, `/kiosk/break/start`, `/kiosk/break/end`, `/kiosk/reset`.

#### Scenario: Kiosk index accessible at path without slug
- **WHEN** a request arrives at `GET /kiosk` on a valid tenant subdomain
- **THEN** the kiosk index page is returned with the tenant company's data

#### Scenario: Old slug-based path is no longer registered
- **WHEN** a request arrives at `GET /kiosk/acme-corp` on any host
- **THEN** the system returns HTTP 404 (route does not exist)

### Requirement: Company identity is resolved from TenantContext, not the URL
The `KioskController` SHALL obtain the current company exclusively from `TenantContext`. It SHALL NOT accept a `Company` parameter via route model binding.

#### Scenario: Controller reads company from tenant context
- **WHEN** a kiosk action (clock-in, clock-out, lookup, break) is submitted on a valid tenant subdomain
- **THEN** the controller uses the company resolved by `IdentifyTenant` with no additional company lookup from the URL

#### Scenario: Session company ID is validated against tenant context
- **WHEN** a kiosk action is submitted and the session contains a `kiosk_company_id`
- **THEN** the system validates that `kiosk_company_id` matches `TenantContext->id()` before proceeding
- **THEN** if they do not match, the request is rejected with HTTP 403
