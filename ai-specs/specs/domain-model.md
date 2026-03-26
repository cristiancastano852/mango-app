# Domain Model — Mango App

## Company (app/Domain/Company/)
- Models: Company (+ campo onboarding_completed boolean), Holiday, SurchargeRule
- Observers: CompanyObserver — auto-seeds SurchargeRule + ColombianHolidays al crear empresa
- Seeders: ColombianHolidaysSeeder::seedForCompany(int $id) — 6 fijos (recurring) + 12 móviles (2026)
- Actions: RegisterCompany — crea Company + User admin en DB::transaction, autentica usuario, dispara CompanyObserver automáticamente
- Controllers: Settings/HolidayController, Settings/SurchargeRuleController, Settings/CompanyProfileController, Settings/CompanySettingsController (admin-only)

## Employee (app/Domain/Employee/)
- Models: Employee
- Actions: CreateEmployee (with default schedule fallback from company settings), UpdateEmployee, DeleteEmployee (si existen)

## TimeTracking (app/Domain/TimeTracking/)
- Actions (clock): ClockIn, ClockOut, StartBreak, EndBreak, AdminClockIn
- Actions (cálculo): CalculateWorkHours — clasificación minuto a minuto en regular/night/sunday_holiday/overtime
- Actions (reportes): GenerateCompanyReport, GenerateEmployeeReport, CalculateReportCosts
- Models: TimeEntry, BreakEntry, BreakType

## Organization (app/Domain/Organization/)
- Models: Department, Location, Position, Schedule

## Shared (app/Domain/Shared/)
- Traits: BelongsToCompany — aplica CompanyScope como global scope
- Scopes: CompanyScope — filtra por company_id del usuario autenticado

## Controllers HTTP (app/Http/Controllers/)
- LandingController — GET / landing pública (sin auth ni tenant)
- CompanyRegistrationController — GET/POST /register/company (sin auth); honeypot en POST
- TourController — POST /tour/dismiss; guarda tour_dismissed en sesión
- DashboardController — KPIs + employee status + showTour prop; redirige non-admin a time-clock
- Admin/ManualCheckInController — POST /admin/manual-check-in (admin + super-admin)
- Admin/TimeEntryController — index (filterable) + edit + update (llama CalculateWorkHours)
- SchedulesController — CRUD completo /schedules (admin + super-admin)
- CalendarController — GET /calendar?month=Y-m&employee_id=optional
- Settings/HolidayController — CRUD holidays (admin + super-admin)
- Settings/SurchargeRuleController — read/update surcharge rules (admin + super-admin)
- Settings/CompanyProfileController — edit/update company name, logo, country, timezone (admin + super-admin)
- Settings/CompanySettingsController — edit/update working days + default schedule (admin + super-admin)
- Settings/BreakTypeController — index/store/update/toggleActive break types (admin + super-admin)
- Onboarding/OnboardingCompanyController — GET/POST /onboarding/company (admin, middleware onboarding)
- Onboarding/OnboardingScheduleController — GET/POST /onboarding/schedule (admin, middleware onboarding)
- Onboarding/OnboardingBreakTypesController — GET/POST /onboarding/break-types; setea onboarding_completed=true al finalizar

## Middleware (app/Http/Middleware/)
- EnsureOnboardingNotCompleted (alias: onboarding) — redirige a /dashboard si company.onboarding_completed=true
