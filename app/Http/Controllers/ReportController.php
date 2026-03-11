<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Actions\GenerateCompanyReport;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Exports\CompanyReportExport;
use App\Exports\EmployeeReportExport;
use App\Http\Requests\ReportFilterRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
            'departments' => Department::all()->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ]),
        ]);
    }

    /**
     * Reporte individual de un empleado.
     */
    public function employee(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);

        $report = $this->buildEmployeeReport($request);

        return Inertia::render('Reports/Employee', [
            'report' => $report,
            'filters' => [
                'date_range' => $validated['date_range'],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'employee_id' => (int) $validated['employee_id'],
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

        $report = $this->buildCompanyReport($request);

        return Inertia::render('Reports/Company', [
            'report' => $report,
            'filters' => [
                'date_range' => $validated['date_range'],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'department_id' => $validated['department_id'] ?? null,
            ],
            'departments' => Department::all()->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ]),
        ]);
    }

    /**
     * Exportar reporte de empleado a Excel.
     */
    public function exportEmployeeExcel(ReportFilterRequest $request): BinaryFileResponse
    {
        $report = $this->buildEmployeeReport($request);
        $name = 'reporte-'.str($report['employee']['name'])->slug().'.xlsx';

        return Excel::download(new EmployeeReportExport($report), $name);
    }

    /**
     * Exportar reporte de empleado a PDF.
     */
    public function exportEmployeePdf(ReportFilterRequest $request): \Illuminate\Http\Response
    {
        $report = $this->buildEmployeeReport($request);
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
        $report = $this->buildCompanyReport($request);

        return Excel::download(new CompanyReportExport($report), 'reporte-empresa.xlsx');
    }

    /**
     * Exportar reporte de empresa a PDF.
     */
    public function exportCompanyPdf(ReportFilterRequest $request): \Illuminate\Http\Response
    {
        $report = $this->buildCompanyReport($request);

        return Pdf::loadView('exports.company-report', compact('report'))
            ->setPaper('letter', 'landscape')
            ->download('reporte-empresa.pdf');
    }

    /**
     * Genera los datos del reporte de empleado (reutilizado por vista y exports).
     */
    private function buildEmployeeReport(ReportFilterRequest $request): array
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);

        return $this->employeeReport->execute(
            (int) $validated['employee_id'],
            $startDate,
            $endDate,
        );
    }

    /**
     * Genera los datos del reporte de empresa (reutilizado por vista y exports).
     */
    private function buildCompanyReport(ReportFilterRequest $request): array
    {
        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDateRange($validated);

        return $this->companyReport->execute(
            $request->user()->company_id,
            $startDate,
            $endDate,
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );
    }

    /**
     * Resuelve el rango de fechas según el preset seleccionado.
     * Quincena colombiana: 1-15 o 16-fin de mes.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(array $validated): array
    {
        return match ($validated['date_range']) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY)],
            'biweekly' => now()->day <= 15
                ? [now()->startOfMonth(), now()->startOfMonth()->addDays(14)->endOfDay()]
                : [now()->startOfMonth()->addDays(15), now()->endOfMonth()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
            ],
        };
    }
}
