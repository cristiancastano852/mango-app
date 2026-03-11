<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Clock, DollarSign, Download, TrendingUp, Users } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { exportCompanyExcel, exportCompanyPdf } from '@/actions/App/Http/Controllers/ReportController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import DateRangeFilter from './partials/DateRangeFilter.vue';

type EmployeeSummary = {
    employee_id: number;
    name: string;
    department: string | null;
    hourly_rate: number;
    days_worked: number;
    gross_hours: number;
    net_hours: number;
    regular_hours: number;
    night_hours: number;
    overtime_hours: number;
    sunday_holiday_hours: number;
    cost: number;
};

type DailyAttendance = {
    date: string;
    employees_present: number;
    total_net_hours: number;
};

type Report = {
    totals: {
        total_employees: number;
        total_days_worked: number;
        gross_hours: number;
        break_hours: number;
        net_hours: number;
        regular_hours: number;
        overtime_hours: number;
        night_hours: number;
        sunday_holiday_hours: number;
    };
    employees: EmployeeSummary[];
    daily_attendance: DailyAttendance[];
    cost_summary: {
        regular: number;
        night: number;
        overtime: number;
        sunday_holiday: number;
        total: number;
    };
    period: { start: string; end: string };
};

const props = defineProps<{
    report: Report;
    filters: {
        date_range: string;
        start_date: string;
        end_date: string;
        department_id: string | null;
    };
    departments: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('reports.breadcrumb'), href: '/reports' },
    { title: t('reports.company_report'), href: '#' },
];

const dateFilter = ref({
    date_range: props.filters.date_range as 'day' | 'week' | 'biweekly' | 'month' | 'custom',
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const selectedDepartment = ref(props.filters.department_id ? String(props.filters.department_id) : '');

function applyFilter() {
    router.get('/reports/company', {
        date_range: dateFilter.value.date_range,
        start_date: dateFilter.value.start_date,
        end_date: dateFilter.value.end_date,
        department_id: selectedDepartment.value || undefined,
    }, { preserveState: true });
}

function exportQueryParams(): string {
    const params = new URLSearchParams({
        date_range: props.filters.date_range,
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
    });
    if (props.filters.department_id) {
        params.set('department_id', String(props.filters.department_id));
    }
    return '?' + params.toString();
}

function downloadExcel() {
    window.location.href = exportCompanyExcel.url() + exportQueryParams();
}

function downloadPdf() {
    window.location.href = exportCompanyPdf.url() + exportQueryParams();
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(value);
}

// --- Charts ---
const attendanceChartEl = ref<HTMLElement | null>(null);
const costChartEl = ref<HTMLElement | null>(null);

onMounted(async () => {
    if (props.report.totals.total_employees === 0) return;

    const ApexCharts = (await import('apexcharts')).default;

    // Daily attendance trend
    if (attendanceChartEl.value && props.report.daily_attendance.length > 0) {
        new ApexCharts(attendanceChartEl.value, {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: t('reports.charts.attendance_trend'), data: props.report.daily_attendance.map(d => d.employees_present), type: 'bar' },
                { name: t('reports.kpi.net_hours'), data: props.report.daily_attendance.map(d => d.total_net_hours), type: 'line' },
            ],
            xaxis: { categories: props.report.daily_attendance.map(d => d.date), labels: { style: { fontSize: '11px' } } },
            yaxis: [
                { title: { text: t('reports.kpi.total_employees') }, labels: { formatter: (v: number) => `${Math.round(v)}` } },
                { opposite: true, title: { text: 'h' }, labels: { formatter: (v: number) => `${v}h` } },
            ],
            colors: ['#3b82f6', '#10b981'],
            plotOptions: { bar: { borderRadius: 3, columnWidth: '50%' } },
            stroke: { width: [0, 2], curve: 'smooth' },
            legend: { position: 'bottom', fontSize: '12px' },
            grid: { borderColor: '#e5e7eb40' },
        }).render();
    }

    // Cost distribution pie
    if (costChartEl.value) {
        const cs = props.report.cost_summary;
        const costValues = [cs.regular, cs.night, cs.overtime, cs.sunday_holiday].filter(v => v > 0);
        const costLabels = [
            t('reports.hours.regular'),
            t('reports.hours.night'),
            t('reports.hours.overtime'),
            t('reports.hours.sunday_holiday'),
        ].filter((_, i) => [cs.regular, cs.night, cs.overtime, cs.sunday_holiday][i] > 0);

        if (costValues.length > 0) {
            new ApexCharts(costChartEl.value, {
                chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
                series: costValues,
                labels: costLabels,
                colors: ['#3b82f6', '#6366f1', '#f59e0b', '#ef4444'],
                legend: { position: 'bottom', fontSize: '12px' },
                tooltip: { y: { formatter: (v: number) => formatCurrency(v) } },
                plotOptions: { pie: { donut: { size: '55%' } } },
            }).render();
        }
    }
});
</script>

<template>
    <Head :title="t('reports.company_report')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="router.get('/reports')">
                        <ArrowLeft class="size-4" />
                    </Button>
                    <div>
                        <h1 class="text-xl font-bold">{{ t('reports.company_report') }}</h1>
                        <p class="text-muted-foreground text-sm">
                            {{ report.period.start }} &rarr; {{ report.period.end }}
                        </p>
                    </div>
                </div>
                <div v-if="report.totals.total_employees > 0" class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="downloadExcel">
                        <Download class="mr-1 size-3.5" />
                        {{ t('reports.export_excel') }}
                    </Button>
                    <Button variant="outline" size="sm" @click="downloadPdf">
                        <Download class="mr-1 size-3.5" />
                        {{ t('reports.export_pdf') }}
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="flex flex-col gap-4 pt-6 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <DateRangeFilter v-model="dateFilter" />
                    </div>
                    <div class="w-full sm:w-48">
                        <Select v-model="selectedDepartment">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('reports.all_departments')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">{{ t('reports.all_departments') }}</SelectItem>
                                <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                                    {{ dept.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button @click="applyFilter">{{ t('reports.filter') }}</Button>
                </CardContent>
            </Card>

            <!-- Empty state -->
            <div v-if="report.totals.total_employees === 0" class="text-muted-foreground py-16 text-center">
                <Calendar class="mx-auto mb-3 size-12 opacity-30" />
                <p class="text-lg font-medium">{{ t('reports.no_data') }}</p>
            </div>

            <template v-else>
                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.total_employees') }}</CardTitle>
                            <Users class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ report.totals.total_employees }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.net_hours') }}</CardTitle>
                            <Clock class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ report.totals.net_hours }}h</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.days_worked') }}</CardTitle>
                            <TrendingUp class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ report.totals.total_days_worked }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.total_cost') }}</CardTitle>
                            <DollarSign class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ formatCurrency(report.cost_summary.total) }}</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.charts.attendance_trend') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="attendanceChartEl" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.charts.cost_distribution') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="costChartEl" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Employee Ranking Table -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('reports.employee_ranking') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-muted-foreground border-b text-left text-xs">
                                        <th class="pb-2">#</th>
                                        <th class="pb-2">{{ t('reports.table.employee') }}</th>
                                        <th class="hidden pb-2 sm:table-cell">{{ t('reports.table.department') }}</th>
                                        <th class="pb-2 text-right">{{ t('reports.table.days') }}</th>
                                        <th class="pb-2 text-right">{{ t('reports.table.net_hours') }}</th>
                                        <th class="pb-2 text-right">{{ t('reports.table.cost') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(emp, idx) in report.employees" :key="emp.employee_id">
                                        <td class="text-muted-foreground py-2.5 text-xs">{{ idx + 1 }}</td>
                                        <td class="py-2.5 font-medium">
                                            {{ emp.name }}
                                        </td>
                                        <td class="text-muted-foreground hidden py-2.5 sm:table-cell">
                                            {{ emp.department || '-' }}
                                        </td>
                                        <td class="py-2.5 text-right">{{ emp.days_worked }}</td>
                                        <td class="py-2.5 text-right font-medium">{{ emp.net_hours }}h</td>
                                        <td class="py-2.5 text-right">{{ formatCurrency(emp.cost) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t font-semibold">
                                        <td class="pt-2" colspan="3">{{ t('reports.costs.total') }}</td>
                                        <td class="pt-2 text-right">{{ report.totals.total_days_worked }}</td>
                                        <td class="pt-2 text-right">{{ report.totals.net_hours }}h</td>
                                        <td class="pt-2 text-right">{{ formatCurrency(report.cost_summary.total) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
