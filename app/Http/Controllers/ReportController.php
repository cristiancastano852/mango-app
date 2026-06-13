<?php

namespace App\Http\Controllers;

use App\Domain\Company\Models\OvertimePaymentDecision;
use App\Domain\Employee\Models\Employee;
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore import when re-enabling.
// use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Actions\GenerateCompanyReport;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Domain\TimeTracking\Actions\ResolveOvertimePaymentDecision;
use App\Exports\CompanyReportExport;
use App\Exports\EmployeeReportExport;
use App\Http\Requests\ReportFilterRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private GenerateEmployeeReport $employeeReport,
        private GenerateCompanyReport $companyReport,
        private ResolveOvertimePaymentDecision $resolveOvertimeDecision,
    ) {}

    /**
     * Página principal de reportes con filtros.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Index', [
            'employees' => Employee::with('user')->get()->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->user->name,
            ]),
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore departments prop when re-enabling.
            // 'departments' => Department::all()->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]),
        ]);
    }

    /**
     * Reporte individual de un empleado.
     */
    public function employee(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);

        $employeeId = (int) $validated['employee_id'];
        $payOvertime = $this->resolveOvertimeDecision->execute(
            $this->employeeCompanyId($employeeId),
            $employeeId,
            $startDate,
            $endDate,
            $this->requestPayOvertime($request),
        );

        $report = $this->buildEmployeeReport(
            $request,
            $startDate,
            $endDate,
            $payOvertime,
            includeDailyBreakdown: true,
            includeBreaksByType: false,
        );

        return Inertia::render('Reports/Employee', [
            'report' => $report,
            'filters' => [
                'date_range' => $validated['date_range'],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'employee_id' => $employeeId,
                'pay_overtime' => $payOvertime,
            ],
            'employees' => Employee::with('user')->get()->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->user->name,
            ]),
        ]);
    }

    /**
     * Reporte general de la empresa.
     */
    public function company(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);

        $companyId = $request->user()->company_id;
        abort_if($companyId === null, 403);

        $payOvertime = $this->resolveOvertimeDecision->execute(
            $companyId,
            null,
            $startDate,
            $endDate,
            $this->requestPayOvertime($request),
        );

        $report = $this->buildCompanyReport($request, $startDate, $endDate, $payOvertime);

        return Inertia::render('Reports/Company', [
            'report' => $report,
            'filters' => [
                'date_range' => $validated['date_range'],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore department_id filter when re-enabling.
                // 'department_id' => $validated['department_id'] ?? null,
                'pay_overtime' => $payOvertime,
            ],
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore departments prop when re-enabling.
            // 'departments' => Department::all()->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]),
        ]);
    }

    /**
     * Exportar reporte de empleado a Excel.
     */
    public function exportEmployeeExcel(ReportFilterRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);
        $payOvertime = $this->persistEmployeeDecision($request, $startDate, $endDate);
        $report = $this->buildEmployeeReport($request, $startDate, $endDate, $payOvertime);
        $name = 'reporte-'.str($report['employee']['name'])->slug().'.xlsx';

        return Excel::download(new EmployeeReportExport($report), $name);
    }

    /**
     * Exportar reporte de empleado a PDF.
     */
    public function exportEmployeePdf(ReportFilterRequest $request): \Illuminate\Http\Response
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);
        $payOvertime = $this->persistEmployeeDecision($request, $startDate, $endDate);
        $report = $this->buildEmployeeReport($request, $startDate, $endDate, $payOvertime);
        $name = 'reporte-'.str($report['employee']['name'])->slug().'.pdf';

        return Pdf::loadView('exports.employee-report', compact('report'))
            ->setPaper('letter', 'landscape')
            ->download($name);
    }

    /**
     * Exportar reporte de empresa a Excel.
     */
    public function exportCompanyExcel(ReportFilterRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);
        $payOvertime = $this->persistCompanyDecision($request, $startDate, $endDate);
        $report = $this->buildCompanyReport($request, $startDate, $endDate, $payOvertime);

        return Excel::download(new CompanyReportExport($report), 'reporte-empresa.xlsx');
    }

    /**
     * Exportar reporte de empresa a PDF.
     */
    public function exportCompanyPdf(ReportFilterRequest $request): \Illuminate\Http\Response
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);
        $payOvertime = $this->persistCompanyDecision($request, $startDate, $endDate);
        $report = $this->buildCompanyReport($request, $startDate, $endDate, $payOvertime);

        return Pdf::loadView('exports.company-report', compact('report'))
            ->setPaper('letter', 'landscape')
            ->download('reporte-empresa.pdf');
    }

    /**
     * Genera los datos del reporte de empleado (reutilizado por vista y exports).
     */
    private function buildEmployeeReport(ReportFilterRequest $request, CarbonInterface $startDate, CarbonInterface $endDate, bool $payOvertime = true, bool $includeDailyBreakdown = true, bool $includeBreaksByType = true): array
    {
        $validated = $request->validated();

        return $this->employeeReport->execute(
            (int) $validated['employee_id'],
            $startDate,
            $endDate,
            $payOvertime,
            $includeDailyBreakdown,
            $includeBreaksByType,
        );
    }

    /**
     * Genera los datos del reporte de empresa (reutilizado por vista y exports).
     * Super-admin no tiene company_id; no puede acceder al reporte de empresa.
     */
    private function buildCompanyReport(ReportFilterRequest $request, CarbonInterface $startDate, CarbonInterface $endDate, bool $payOvertime = true): array
    {
        $companyId = $request->user()->company_id;

        abort_if($companyId === null, 403);

        $validated = $request->validated();

        return $this->companyReport->execute(
            $companyId,
            $startDate,
            $endDate,
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore department_id filter when re-enabling.
            // isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            null,
            $payOvertime,
        );
    }

    /**
     * Lee el override explícito de pago de horas extra desde el request (null si no viene).
     */
    private function requestPayOvertime(ReportFilterRequest $request): ?bool
    {
        return $request->has('pay_overtime') ? $request->boolean('pay_overtime') : null;
    }

    /**
     * company_id del empleado (soporta super-admin sin company_id propio).
     */
    private function employeeCompanyId(int $employeeId): int
    {
        return (int) Employee::withoutGlobalScopes()
            ->whereKey($employeeId)
            ->value('company_id');
    }

    /**
     * Resuelve y persiste (upsert) la decisión de un empleado al exportar; retorna el flag efectivo.
     */
    private function persistEmployeeDecision(ReportFilterRequest $request, CarbonInterface $startDate, CarbonInterface $endDate): bool
    {
        $employeeId = (int) $request->validated()['employee_id'];
        $companyId = $this->employeeCompanyId($employeeId);

        $payOvertime = $this->resolveOvertimeDecision->execute(
            $companyId,
            $employeeId,
            $startDate,
            $endDate,
            $this->requestPayOvertime($request),
        );

        $this->upsertDecision($companyId, $employeeId, $startDate, $endDate, $payOvertime, $request->user()?->id);

        return $payOvertime;
    }

    /**
     * Resuelve y persiste (upsert) la decisión del reporte de empresa al exportar; retorna el flag efectivo.
     */
    private function persistCompanyDecision(ReportFilterRequest $request, CarbonInterface $startDate, CarbonInterface $endDate): bool
    {
        $companyId = $request->user()->company_id;

        abort_if($companyId === null, 403);

        $payOvertime = $this->resolveOvertimeDecision->execute(
            $companyId,
            null,
            $startDate,
            $endDate,
            $this->requestPayOvertime($request),
        );

        $this->upsertDecision($companyId, null, $startDate, $endDate, $payOvertime, $request->user()?->id);

        return $payOvertime;
    }

    /**
     * Upsert manual de la decisión. Maneja employee_id null (reporte de empresa) con whereNull,
     * ya que updateOrCreate no resuelve correctamente la igualdad contra NULL.
     */
    private function upsertDecision(int $companyId, ?int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate, bool $payOvertime, ?int $userId): void
    {
        $existing = OvertimePaymentDecision::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->when(
                $employeeId === null,
                fn ($query) => $query->whereNull('employee_id'),
                fn ($query) => $query->where('employee_id', $employeeId),
            )
            ->first();

        $payload = [
            'pay_overtime' => $payOvertime,
            'exported_by' => $userId,
            'exported_at' => now(),
        ];

        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        OvertimePaymentDecision::withoutGlobalScopes()->create(array_merge($payload, [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]));
    }

    /**
     * Resuelve el rango de fechas según el preset seleccionado.
     * Quincena colombiana: 1-15 o 16-fin de mes.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(array $validated): array
    {
        $now = now();

        return match ($validated['date_range']) {
            'day' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(Carbon::MONDAY), $now->endOfWeek(Carbon::SUNDAY)],
            'biweekly' => $now->day <= 15
                ? [$now->startOfMonth(), $now->startOfMonth()->addDays(14)->endOfDay()]
                : [$now->startOfMonth()->addDays(15), $now->endOfMonth()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            'custom' => [
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
            ],
        };
    }
}
