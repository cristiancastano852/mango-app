# Domain Model — Mango App

## Company (app/Domain/Company/)
- Models: Company (+ campo onboarding_completed boolean), Holiday, SurchargeRule (+ pay_overtime_by_default, dominical_weekday, pay_dominical_by_default, default_dominical_payment_mode, default_normal_day_value, default_holiday_payment_mode), OvertimePaymentDecision (decisión de pago de horas extra por empleado/periodo; employee_id NULL = reporte de empresa), DominicalPaymentDecision (cuántos dominicales pagar por empleado/periodo; employee_id NOT NULL; solo modo `day`)
- Observers: CompanyObserver — auto-seeds SurchargeRule + ColombianHolidays al crear empresa
- Seeders: ColombianHolidaysSeeder::seedForCompany(int $id) — 6 fijos (recurring) + 12 móviles (2026)
- Actions: RegisterCompany — crea Company + User admin en DB::transaction, autentica usuario, dispara CompanyObserver automáticamente
- Controllers: Settings/HolidayController, Settings/SurchargeRuleController, Settings/CompanyProfileController, Settings/CompanySettingsController (admin-only)

## Employee (app/Domain/Employee/)
- Models: Employee, EmployeeAdjustment (ajustes de nómina: Bonus/Deduction por empleado y fecha; se aplican en el reporte después del neto)
- Enums: AdjustmentType (Bonus | Deduction; sign() = +1/-1)
- Actions: CreateEmployee (with default schedule fallback from company settings), UpdateEmployee, DeleteEmployee (si existen), SaveEmployeeAdjustment y DeleteEmployeeAdjustment (CRUD de ajustes de nómina)

## TimeTracking (app/Domain/TimeTracking/)
- Actions (clock): ClockIn, ClockOut, StartBreak, EndBreak, AdminClockIn
- Actions (cálculo): CalculateWorkHours — clasificación minuto a minuto en 12 buckets (semana/dominical/festivo × diurno/nocturno × dentro-límite/extra); día dominical configurable (dominical_weekday) y precedencia festivo > dominical; RecalculateTimeEntry — recomputa gross/break (solo pausas no pagadas finalizadas)/net, invoca CalculateWorkHours y marca status='edited' (usada por edición admin de registros y pausas)
- Actions (reportes): GenerateCompanyReport, GenerateEmployeeReport (daily_breakdown enriquecido: horario ISO 8601, status con 'in_progress' derivado para turnos abiertos —no suman a totals— y breaks[] anidadas; cuentan días dominicales trabajados N), CalculateReportCosts (acepta flag payOvertime + config dominical: pay/mode/day_value/payable_count/worked_days; modo `day` = base por horas + plus por día pagado (valor_día_normal × sunday_holiday%); festivo siempre paga, configurable por hora/día sin conteo editable), ResolveOvertimePaymentDecision y ResolveDominicalPaymentDecision (precedencia request → decisión guardada → default)
- Models: TimeEntry (SoftDeletes; unique employee_id+date+active_marker — 1 activo/día), BreakEntry (toDisplayArray() — shape único de pausa para vistas/reportes), BreakType

## Organization (app/Domain/Organization/)
- Models: Department, Location, Position, Schedule

## Shared (app/Domain/Shared/)
- Traits: BelongsToCompany — aplica CompanyScope como global scope
- Scopes: CompanyScope — prefiere el tenant del subdominio (TenantContext); si no hay, cae a company_id del usuario autenticado
- Tenancy/TenantContext — binding `scoped` que guarda la Company actual de la petición (set por IdentifyTenant)
- Tenancy/Tenancy — helpers de host: baseDomain(), adminHost(), isAdminHost(), rootUrl(slug)

## Controllers HTTP (app/Http/Controllers/)
- LandingController — GET / landing pública (sin auth ni tenant)
- CompanyRegistrationController — GET/POST /register/company (sin auth); honeypot en POST
- TourController — POST /tour/dismiss; guarda tour_dismissed en sesión
- DashboardController — KPIs + employee status + showTour prop; redirige non-admin a time-clock
- Admin/ManualCheckInController — POST /admin/manual-check-in (admin + super-admin)
- Admin/TimeEntryController — index (filtrable: empleado + rango date_from/date_to) + create/store + edit + update + destroy (soft-delete); delega recálculo en RecalculateTimeEntry. Requests: StoreTimeEntryRequest (unicidad activa por empleado/día), UpdateTimeEntryRequest (valida pausas dentro del nuevo rango)
- Admin/TimeEntryBreakController — store/update/destroy de pausas anidadas bajo un registro (admin + super-admin); recalcula el registro tras cada cambio. Requests: StoreBreakRequest, UpdateBreakRequest (break_type de la empresa, rango dentro del turno)
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
- IdentifyTenant (en grupo web) — resuelve la Company por el subdominio del host y la fija en TenantContext; host central/reservado → sin tenant; subdominio desconocido → 404
- EnsureAdminHost (alias: admin-host) — 404 si el host no es admin.{base_domain}; protege las rutas super-admin
- Login gate (FortifyServiceProvider::authenticateUsing): en subdominio solo usuarios de esa company; en admin host solo super-admin; sin login en host público
