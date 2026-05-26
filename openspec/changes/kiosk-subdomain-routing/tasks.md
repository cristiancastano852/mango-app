## 1. Middleware

- [x] 1.1 Create `app/Http/Middleware/EnsureTenantContext.php` — aborts 404 if `TenantContext::check()` is false
- [x] 1.2 Register `tenant` alias in `bootstrap/app.php` middleware aliases (next to `admin-host`)

## 2. Routes

- [x] 2.1 Update `routes/web.php` — change kiosk prefix from `kiosk/{company:slug}` to `kiosk` and add `->middleware('tenant')` to the group

## 3. Controller

- [x] 3.1 Inject `TenantContext` via constructor in `KioskController` — `public function __construct(private TenantContext $tenant) {}`
- [x] 3.2 Remove `Company $company` param from `index()`, `lookup()`, `clockIn()`, `clockOut()`, `startBreak()`, `endBreak()`, `reset()` — replace with `$company = $this->tenant->get()`
- [x] 3.3 Update all `redirect()->route('kiosk.index', ['company' => $company->slug])` calls — remove the route params array (route takes no params now)
- [x] 3.4 Update `resolveKioskEmployee()` — compare `$companyId !== $this->tenant->get()->id` instead of comparing against route-bound company
- [x] 3.5 Run `vendor/bin/pint --dirty --format agent` to fix formatting

## 4. Wayfinder

- [x] 4.1 Run `php artisan wayfinder:generate` to regenerate TS files without the `company` param

## 5. Frontend

- [x] 5.1 Update `Kiosk/Index.vue` — remove `props.company.slug` argument from all 6 Wayfinder calls (`lookup`, `clockIn`, `clockOut`, `startBreak`, `endBreak`, `reset`)
- [x] 5.2 Run `npm run build` to confirm no TypeScript errors

## 6. Tests

- [x] 6.1 Find existing kiosk feature tests (`grep -r "kiosk" tests/`)
- [x] 6.2 Update all kiosk test requests to include `withServerVariables(['HTTP_HOST' => 'acme.mango-app.test'])` (or use a helper)
- [x] 6.3 Add test: kiosk index on apex domain returns 404
- [x] 6.4 Add test: kiosk action with session `kiosk_company_id` from a different company returns 403
- [x] 6.5 Run kiosk tests: `php artisan test --compact --filter=Kiosk`
