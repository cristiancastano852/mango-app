# Data Model — Mango App

## companies
- id, name, slug (unique), logo (nullable), timezone (default: America/Bogota), country (default: CO)
- settings (jsonb nullable), onboarding_completed (boolean, default: false), subscription_plan (default: free), trial_ends_at (nullable)
- timestamps

## users
- id, company_id (nullable → companies), name, email (unique), email_verified_at (nullable)
- password, avatar (nullable), phone (nullable), is_active (boolean, default: true)
- remember_token, timestamps

## employees
- id, user_id → users, company_id → companies
- department_id (nullable → departments), position_id (nullable → positions)
- employee_code (nullable), document_number (string 50, nullable), hire_date (date nullable), hourly_rate (decimal 10,2 nullable) — valor hora usado para recargos y extras en ambos modos
- monthly_base_salary (decimal 10,2 nullable) — salario base mensual; obligatorio cuando salary_type = monthly
- salary_type (default: hourly) — `hourly` (cálculo por horas) | `monthly` (salario base fijo), schedule_id (nullable → schedules), location_id (nullable → locations)
- timestamps
- indexes: company_id, (company_id, user_id)
- unique: (document_number, company_id)

## time_entries
- id, employee_id → employees, company_id → companies
- date (date), clock_in (timestamp nullable), clock_out (timestamp nullable)
- gross_hours (decimal 5,2 default 0), break_hours (decimal 5,2 default 0), net_hours (decimal 5,2 default 0)
- regular_hours, night_hours, sunday_holiday_hours, night_sunday_hours (decimal 5,2 default 0)
- overtime_day_hours, overtime_night_hours, overtime_day_sunday_hours, overtime_night_sunday_hours (decimal 5,2 default 0)
- status (string, default: pending) — valores persistidos: pending (creado/clock-in), calculated (tras CalculateWorkHours), edited (tras RecalculateTimeEntry)
- edited_by (nullable → users), edit_reason (text nullable), pin_verified (boolean default false)
- timestamps, deleted_at (soft deletes)
- active_marker (tinyint generado: 1 si activo / NULL si eliminado)
- unique: (employee_id, date, active_marker) — garantiza 1 registro activo por empleado/día a nivel BD y permite recrear tras soft-delete
- indexes: company_id, (company_id, date), (company_id, employee_id)

## breaks
- id, time_entry_id → time_entries, employee_id → employees, company_id → companies
- break_type_id → break_types
- started_at (timestamp), ended_at (timestamp nullable), duration_minutes (int nullable)
- notes (text nullable), timestamps
- indexes: company_id, (company_id, employee_id)

## break_types
- id, company_id → companies, name, slug, icon (nullable), color (nullable)
- is_paid (boolean default false), max_duration_minutes (int nullable), max_per_day (int nullable)
- is_default (boolean default false), is_active (boolean default true)
- timestamps

## schedules
- id, company_id → companies, name
- start_time (time), end_time (time), break_duration (int default 60)
- days_of_week (jsonb, default [1,2,3,4,5]) — Carbon: 0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb
- timestamps

## departments
- id, company_id → companies, name, timestamps

## positions
- id, company_id → companies, department_id → departments, name, timestamps

## locations
- id, company_id → companies, name, address (nullable)
- latitude (decimal 10,8 nullable), longitude (decimal 11,8 nullable)
- timestamps

## surcharge_rules
- id, company_id → companies (unique)
- night_surcharge (decimal 5,2 default 35), overtime_day (decimal 5,2 default 25)
- overtime_night (decimal 5,2 default 75), sunday_holiday (decimal 5,2 default 75)
- overtime_day_sunday (decimal 5,2 default 100), overtime_night_sunday (decimal 5,2 default 150)
- night_sunday (decimal 5,2 default 110), max_weekly_hours (int default 42), max_daily_hours (int default 8)
- pay_overtime_by_default (boolean default true) — criterio general: pagar horas extra en dinero (true) o compensarlas con tiempo (false)
- night_start_time (time, default '21:00'), night_end_time (time, default '06:00')
- default_monthly_salary (decimal 10,2, default SMLV) — salario base mensual por defecto para empleados nuevos; sembrado con el SMLV vigente, editable por admin
- default_hourly_rate (decimal 10,2, default round(SMLV/220)) — valor hora por defecto para empleados nuevos; derivado del SMLV con divisor 220 al sembrar, editable por admin
- timestamps

## overtime_payment_decisions
- id, company_id → companies, employee_id (nullable → employees) — NULL = decisión del reporte de empresa; lleno = decisión por empleado
- start_date (date), end_date (date) — periodo resuelto del reporte
- pay_overtime (boolean) — si las horas extra se pagan en ese desprendible
- exported_by (nullable → users), exported_at (timestamp nullable)
- timestamps
- unique: (company_id, employee_id, start_date, end_date) → upsert al exportar (gana la última)
- indexes: (company_id, employee_id)

## holidays
- id, company_id → companies, name, date (date)
- is_recurring (boolean default false), country (default: CO)
- timestamps

## Multi-tenancy
Todas las tablas tienen `company_id` excepto `companies`.
Users de `super-admin` tienen `company_id = null`.
El trait `BelongsToCompany` aplica `CompanyScope` como global scope en todos los modelos.
