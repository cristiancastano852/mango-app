# MangoApp - Sistema de Control de Asistencia y Horas Laborales

## Resumen del Proyecto
SaaS multi-tenant para empresas, restaurantes y negocios en general.
Permite llevar control de asistencia (check-in/check-out), calculo automatico de horas laborales,
horas extras, nocturnas y dominicales segun legislacion colombiana.

**Enfoque Mobile-First**: La app es web responsive, diseñada primero para celular y adaptada a escritorio.
Los empleados fichan desde su celular, los admins gestionan desde celular o computador.

## Stack Tecnologico
- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue 3 + Inertia.js
- **CSS/UI**: Tailwind CSS (componentes custom, sin librería UI externa)
- **Base de datos**: PostgreSQL (Supabase)
- **Multi-tenancy**: spatie/laravel-multitenancy (v4)
- **Auth**: Laravel Breeze (adaptado con Inertia + Vue)
- **Pagos**: Laravel Cashier (Stripe)
- **Reportes**: Laravel Excel (Maatwebsite), DomPDF
- **Real-time**: Laravel Echo + Pusher (o Soketi self-hosted)
- **Queue**: Laravel Queues (Redis o database driver)
- **Testing**: PHPUnit + Pest

## Deployment
- **Serverless**: Bref (Laravel en AWS Lambda)
- **Base de datos**: Supabase (PostgreSQL gestionado, free tier)
- **Assets/Frontend**: AWS S3 + CloudFront (o similar CDN)
- **Queues**: SQS (nativo con Bref) o database driver en etapa inicial
- **Nota config DB**: Usar siempre URL del pooler de Supabase (`*.pooler.supabase.com:6543`), nunca conexión directa. Setear `PGS_MAX_CONNS=15` en Lambda env vars. Opcional: `PDO::ATTR_EMULATE_PREPARES => true` en `config/database.php` si hay errores con prepared statements en transaction mode.

---

## Fases de Desarrollo

### FASE 1: Fundacion (Semana 1-2)
1. Crear proyecto Laravel 12 (PHP 8.4)
2. Configurar Inertia.js + Vue 3 + Tailwind CSS
3. Configurar PostgreSQL
4. Instalar y configurar spatie/laravel-multitenancy v4
   - Estrategia: **single-DB con tenant_id** (no multi-DB)
   - Modelo Tenant (Company) con IsTenant + ImplementsTenant
   - TenantFinder por dominio/subdomain
   - Middleware group 'tenant' (NeedsTenant + EnsureValidTenantSession)
   - Queues tenant-aware
   - Indices compuestos en todas las tablas: company_id, (company_id, date), (company_id, employee_id)
5. Configurar autenticacion (Laravel Breeze + Inertia)
6. Definir roles: Super Admin, Admin Empresa, Empleado
7. Crear migraciones base de datos
8. Seed de datos de prueba
9. Layout base mobile-first responsive (bottom nav en mobile, sidebar en desktop)
10. Diseño de referencia: `image.png` (dashboard admin con cards de KPIs)

### FASE 2: Gestion de Empleados (Semana 2-3)
1. CRUD completo de empleados
2. Asignacion de turnos/horarios
3. Estados (activo/inactivo)
4. Departamentos y cargos
5. Configuracion de salario/hora base
6. Foto de perfil

### FASE 3: Check-in / Check-out + Sistema de Pausas (Semana 3-4)
1. Interfaz de fichaje (boton check-in/out)
2. Timer en tiempo real (cuenta horas trabajadas netas)
3. **Sistema de Pausas/Breaks:**
   - Almuerzo (pausa programada, no descuenta si es dentro del horario)
   - Descanso corto / Break (pausa breve, ej: 15 min, configurable)
   - Ida al bano (pausa rapida, se registra pero no descuenta)
   - Pausa personal (pausa voluntaria, puede descontar segun config)
   - Pausa medica (justificada, no descuenta)
   - Tipos de pausa configurables por el admin (puede crear nuevos)
   - Cada pausa registra: tipo, hora inicio, hora fin, duracion
   - Indicador visual del estado actual (trabajando, almuerzo, break, bano, etc.)
   - Limites configurables por tipo (ej: max 2 breaks de 15 min por dia)
4. PIN o password para validar identidad
5. Historial de registros del dia (incluyendo todas las pausas)
6. Vista del empleado (su propio dashboard con resumen de pausas)
7. Prevencion de doble check-in
8. Resumen diario: horas brutas, tiempo en pausas, horas netas trabajadas

### FASE 4: Calculo de Horas - Legislacion Colombiana (Semana 4-5)
1. Motor de calculo de horas laborales
2. Jornada maxima: 42 horas/semana (2026)
3. Hora ordinaria diurna: 6:00 AM - 9:00 PM (0%)
4. Recargo nocturno: 9:00 PM - 6:00 AM (35%)
5. Hora extra diurna: 25%
6. Hora extra nocturna: 75%
7. Recargo dominical/festivo: 75%
8. Extra diurna dominical: 100% (75% + 25%)
9. Extra nocturna dominical: 150% (75% + 75%)
10. Nocturno dominical: 110% (75% + 35%)
11. Configuracion de festivos colombianos
12. Redondeo configurable

### FASE 5: Panel Administrativo (Semana 5-6)
Referencia visual: `image.png`
1. Dashboard con resumen del dia (4 cards KPI):
   - Employees Present (total + comparacion con ayer)
   - On Break (desglose por tipo: almuerzo, descanso, etc.)
   - Absent Today (scheduled vs unexcused)
   - Net Hours Today (total + promedio por empleado)
2. Seccion Employee Status debajo de cards (lista en tiempo real)
3. Indicador "live" de actualizacion en tiempo real
4. FAB o boton rapido para check-in manual de empleado
5. Vista calendario mensual
6. Alertas de llegadas tarde
7. Edicion manual de registros (con justificacion)
8. Gestion de turnos semanales
9. **Mobile**: cards en columna scrolleable, bottom nav reemplaza sidebar
10. **Desktop**: sidebar colapsable (como en referencia) + cards en grid 2x2

**Sidebar/Navigation**:
- Dashboard
- Employees
- Time Entries
- Reports
- Locations
- Departments (sub-menu o dentro de config)
- Schedules (sub-menu o dentro de config)
- Settings (incluye Break Types, Surcharge Rules)
- Billing

### FASE 6: Reportes (Semana 6-7)
1. Reporte individual por empleado
2. Reporte general de la empresa
3. Filtros por rango de fechas (dia, semana, quincena, mes, personalizado)
4. Desglose de horas (ordinarias, extras, nocturnas, dominicales)
5. Desglose de pausas por tipo (almuerzo, breaks, bano, personal, medica)
6. Horas brutas vs netas trabajadas
7. Exportar a PDF
8. Exportar a Excel
9. Graficas de tendencias (Chart.js o ApexCharts)
10. Resumen de costos por recargos

### FASE 7: Configuracion del Tenant (Semana 7-8)
1. Dias laborales de la empresa
2. Horarios de trabajo por defecto
3. **Configuracion de tipos de pausa:**
   - Crear/editar/desactivar tipos de pausa
   - Definir si cada tipo es pagada o descuenta
   - Limites de duracion y frecuencia por tipo
   - Duracion por defecto de almuerzo
4. Gestion de dias festivos
5. Reglas de recargos (porcentajes configurables)
6. Logo y datos de la empresa
7. Zona horaria

### FASE 8: Notificaciones (Semana 8)
1. Email: empleado no ha fichado
2. Email: llegada tarde
3. Recordatorio de check-out
4. Notificaciones in-app (bell icon)
5. Configuracion de preferencias de notificacion

### FASE 9: Suscripciones SaaS (Semana 9-10)
1. Integracion con Stripe (Laravel Cashier)
2. Planes:
   - Free: hasta 5 empleados
   - Basico ($15/mes): hasta 25 empleados
   - Pro ($35/mes): hasta 100 empleados
   - Enterprise ($75/mes): ilimitado
3. Pagina de pricing
4. Portal de facturacion
5. Trials de 14 dias
6. Limitar funcionalidades por plan

### FASE 10: Multi-sede (Semana 10-11)
1. CRUD de sedes/sucursales
2. Asignar empleados a sedes
3. Reportes filtrados por sede
4. Dashboard por sede

### FASE 11: Landing Page y Onboarding (Semana 11-12)
1. Landing page publica
2. Registro de nueva empresa
3. Wizard de configuracion inicial
4. Tour guiado del sistema

---

## Modelo de Base de Datos

### Tablas Principales (Tenant)

```
companies (tenants)
├── id, name, slug, logo, timezone, country
├── settings (JSON: lunch_duration, round_minutes, etc.)
├── subscription_plan, trial_ends_at
└── created_at, updated_at

users
├── id, company_id, name, email, password, role
├── avatar, phone, is_active
└── created_at, updated_at

departments
├── id, company_id, name
└── created_at, updated_at

positions (cargos)
├── id, company_id, department_id, name
└── created_at, updated_at

employees
├── id, user_id, company_id, department_id, position_id
├── employee_code, hire_date, hourly_rate, salary_type
├── schedule_id, location_id
└── created_at, updated_at

schedules (horarios)
├── id, company_id, name
├── start_time, end_time, break_duration
├── days_of_week (JSON: [1,2,3,4,5])
└── created_at, updated_at

time_entries (registros de jornada diaria)
├── id, employee_id, company_id, date
├── clock_in, clock_out
├── gross_hours (horas brutas sin descontar pausas)
├── break_hours (total tiempo en pausas descontables)
├── net_hours (horas netas trabajadas = gross - break_hours)
├── regular_hours, overtime_hours
├── night_hours, sunday_holiday_hours
├── status (pending, approved, rejected, edited)
├── edited_by, edit_reason
├── pin_verified (boolean)
└── created_at, updated_at

break_types (tipos de pausa - configurables por tenant)
├── id, company_id, name, slug
├── icon (emoji o icono), color
├── is_paid (boolean: si descuenta o no del tiempo trabajado)
├── max_duration_minutes (duracion maxima permitida, nullable)
├── max_per_day (limite de veces por dia, nullable)
├── is_default (boolean: viene por defecto)
├── is_active (boolean)
└── created_at, updated_at
    Defaults: almuerzo, descanso, bano, personal, medica

breaks (registros de pausas individuales)
├── id, time_entry_id, employee_id, company_id
├── break_type_id
├── started_at, ended_at
├── duration_minutes (calculado al finalizar)
├── notes (opcional, ej: razon medica)
└── created_at, updated_at

holidays (festivos)
├── id, company_id, name, date
├── is_recurring (boolean), country
└── created_at, updated_at

surcharge_rules (reglas de recargos)
├── id, company_id
├── night_surcharge (default 35)
├── overtime_day (default 25)
├── overtime_night (default 75)
├── sunday_holiday (default 75)
├── overtime_day_sunday (default 100)
├── overtime_night_sunday (default 150)
├── night_sunday (default 110)
├── max_weekly_hours (default 42)
└── created_at, updated_at

locations (sedes)
├── id, company_id, name, address
├── latitude, longitude (para V2 geolocalizacion)
└── created_at, updated_at

notifications
├── id, user_id, company_id
├── type, title, message, data (JSON)
├── read_at
└── created_at

subscriptions (via Laravel Cashier / Stripe)
├── id, company_id, stripe_id, stripe_status
├── stripe_price, quantity
├── trial_ends_at, ends_at
└── created_at, updated_at
```

---

## Decisiones Arquitectonicas

### Multi-tenancy: Single Database con tenant_id

**Decision**: Single-DB con `company_id` en todas las tablas, gestionado por `spatie/laravel-multitenancy`.

**Razones**:
1. Supabase free tier limita a 2 proyectos — multi-DB por tenant es inviable
2. Para datos de asistencia la aislacion logica es suficiente (no es data financiera/salud)
3. Bref funciona con un solo connection string, sin complejidad de switching
4. Operacionalmente simple: un backup, una migracion, un pool de conexiones
5. Si en el futuro hay cliente enterprise que exija aislacion fisica, se ofrece como tier premium con instancia dedicada

**Clave**: indices compuestos desde el inicio en `company_id`, `(company_id, date)`, `(company_id, employee_id)`.

---

---

## Skills a Instalar

```bash
# Ingenieria y planificacion
npx skills add anthropic/superpowers

# Diseno UI/UX
npx skills add anthropic/ui-ux-pro-max
npx skills add anthropic/frontend-design

# Testing
npx skills add anthropic/webapp-testing

# Browser testing visual
npx skills add nicholasoxford/dev-browser
```

## MCPs Configurados
- Context7 (documentacion en tiempo real)
- GitHub MCP (gestion de repositorio)

## Custom Skills a Crear
- `.claude/skills/colombian-labor-law.md` - Reglas de legislacion laboral colombiana
- `.claude/skills/mangoapp-architecture.md` - Patrones y convenciones del proyecto

---

## Legislacion Laboral Colombiana (Referencia Rapida)

### Ley 2101 de 2021 - Reduccion jornada laboral
- 2024: 46 horas/semana
- 2025: 44 horas/semana
- 2026: 42 horas/semana

### Recargos (Codigo Sustantivo del Trabajo)
- Jornada diurna: 6:00 AM - 9:00 PM
- Jornada nocturna: 9:00 PM - 6:00 AM
- Recargo nocturno: 35% sobre hora ordinaria
- Hora extra diurna: 25%
- Hora extra nocturna: 75%
- Dominical/festivo: 75%
- Extra diurna en dominical: 100%
- Extra nocturna en dominical: 150%
- Nocturna en dominical: 110%

### Festivos Colombia 2026 (Ley 51 de 1983)
- Enero: 1 (Año Nuevo), 12 (Reyes Magos)
- Marzo: 23 (San Jose)
- Abril: 2 (Jueves Santo), 3 (Viernes Santo)
- Mayo: 1 (Dia del Trabajo), 18 (Ascension)
- Junio: 8 (Corpus Christi), 15 (Sagrado Corazon)
- Junio: 29 (San Pedro y San Pablo)
- Julio: 20 (Independencia)
- Agosto: 7 (Batalla de Boyaca), 17 (Asuncion)
- Octubre: 12 (Dia de la Raza)
- Noviembre: 2 (Todos los Santos), 16 (Independencia Cartagena)
- Diciembre: 8 (Inmaculada Concepcion), 25 (Navidad)
