## ADDED Requirements

### Requirement: Clock times displayed in 12-hour format

The system SHALL display clock times (shift `clock_in`/`clock_out` and break start/end times) in 12-hour format with AM/PM markers (e.g., `7:00 AM`, `4:11 PM`) in the employee report daily table and the admin time entries list. The system SHALL provide a single `formatTime12h(iso)` function in `resources/js/lib/utils.ts` as the unique source of truth for this formatting. Vue components MUST import and use this function instead of inline formatting. Backend payloads SHALL carry clock times as ISO 8601 strings with timezone offset; PHP-rendered exports (PDF) SHALL format equivalently server-side.

#### Scenario: Morning and afternoon times

- **WHEN** a time entry has `clock_in` at 07:00 and `clock_out` at 16:11 (America/Bogota)
- **THEN** the UI displays `7:00 AM → 4:11 PM`

#### Scenario: Midnight and noon

- **WHEN** a clock time falls at 00:05 or 12:00
- **THEN** the UI displays `12:05 AM` and `12:00 PM` respectively

#### Scenario: Missing clock_out

- **WHEN** a time entry has no `clock_out`
- **THEN** the UI shows the formatted `clock_in` and an "in progress" indicator instead of an end time, without errors

#### Scenario: Function handles invalid input

- **WHEN** `formatTime12h` receives `null`, `undefined`, or an unparseable string
- **THEN** it returns a safe placeholder (e.g., `—`) without throwing exceptions
