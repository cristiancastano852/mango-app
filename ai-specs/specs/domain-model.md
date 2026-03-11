# Domain Model — Mango App

## Company (app/Domain/Company/)
- Models: Company, Holiday, SurchargeRule
- Observers: CompanyObserver — auto-seeds SurchargeRule + ColombianHolidays al crear empresa
- Seeders: ColombianHolidaysSeeder::seedForCompany(int $id) — 6 fijos (recurring) + 12 móviles (2026)
- Controllers: Settings/HolidayController, Settings/SurchargeRuleController (admin-only)

## Employee (app/Domain/Employee/)
- Models: Employee
- Actions: CreateEmployee, UpdateEmployee, DeleteEmployee (si existen)

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
- DashboardController — KPIs + employee status, redirige non-admin a time-clock
- Admin/ManualCheckInController — POST /admin/manual-check-in (admin + super-admin)
- Admin/TimeEntryController — index (filterable) + edit + update (llama CalculateWorkHours)
- SchedulesController — CRUD completo /schedules (admin + super-admin)
- CalendarController — GET /calendar?month=Y-m&employee_id=optional
- Settings/HolidayController — CRUD holidays (admin + super-admin)
- Settings/SurchargeRuleController — read/update surcharge rules (admin + super-admin)
