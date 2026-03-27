## ADDED Requirements

### Requirement: Decimal hours displayed as hours and minutes
The system SHALL display all static hour values in the UI using the format `Xh Ym` (e.g., `7h 59m`) instead of decimal notation (e.g., `7.99h`). This applies to `net_hours`, `gross_hours`, `break_hours`, `regular_hours`, `overtime_hours`, `night_hours`, and `sunday_holiday_hours` wherever they appear as read-only values.

#### Scenario: Typical work day hours
- **WHEN** a time entry has `net_hours = 7.99`
- **THEN** the UI displays `7h 59m`

#### Scenario: Half hour
- **WHEN** a time entry has `net_hours = 0.5`
- **THEN** the UI displays `0h 30m`

#### Scenario: Exact full hours
- **WHEN** a time entry has `net_hours = 8.0`
- **THEN** the UI displays `8h 0m`

#### Scenario: Zero hours
- **WHEN** a time entry has `net_hours = 0` or `null`
- **THEN** the UI displays `0h 0m` without errors

#### Scenario: Floating point precision
- **WHEN** a decimal value like `7.99` is converted (`7.99 * 60 = 479.4`)
- **THEN** the system SHALL round to the nearest minute (`480` rounds to `8h 0m`; `479.4` rounds to `7h 59m`)

### Requirement: Reusable hour formatting utility
The system SHALL provide a single `formatDecimalHours(hours)` function in `resources/js/lib/utils.ts` as the unique source of truth for hour display formatting. All Vue components MUST import and use this function instead of implementing inline formatting logic.

#### Scenario: Function handles all input types
- **WHEN** `formatDecimalHours` receives a `number`, `string`, `null`, or `undefined`
- **THEN** it returns a valid `Xh Ym` string without throwing exceptions

### Requirement: Real-time timer unaffected
The active session timer in `TimeClock/Index.vue` that displays elapsed time in `HH:MM:SS` format SHALL NOT be modified. This format is appropriate for a live countdown/countup context.

#### Scenario: Timer continues in HH:MM:SS format
- **WHEN** an employee has an active clock-in session
- **THEN** the elapsed time counter shows `HH:MM:SS` format (e.g., `07:59:00`)

### Requirement: Consistent format across all views
All pages that display hour values to users (admin and employee roles) SHALL use `formatDecimalHours`. Affected pages: Dashboard, TimeClock history, Reports (Employee and Company), Admin TimeEntries index, Calendar.

#### Scenario: Admin views hours in Dashboard KPIs
- **WHEN** the admin opens the Dashboard
- **THEN** `net_hours_today` and `avg_net_hours` show in `Xh Ym` format

#### Scenario: Employee views own day summary in TimeClock
- **WHEN** the employee opens TimeClock and sees today's summary card
- **THEN** `gross_hours`, `break_hours`, and `net_hours` show in `Xh Ym` format

#### Scenario: Admin views time entries list
- **WHEN** the admin opens Admin > Time Entries
- **THEN** the `net_hours` column shows in `Xh Ym` format

#### Scenario: Calendar badge shows hours
- **WHEN** a calendar day has time entries with `net_hours > 0`
- **THEN** the badge displays in `Xh Ym` format
