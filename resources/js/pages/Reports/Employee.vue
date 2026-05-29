<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    Clock,
    DollarSign,
    Download,
    Minus,
    Moon,
    Plus,
    Sun,
    Trash2,
    TrendingUp,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    exportEmployeeExcel,
    exportEmployeePdf,
} from '@/actions/App/Http/Controllers/ReportController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDecimalHours } from '@/lib/utils';
import {
    store as storeDeduction,
    destroy as destroyDeduction,
} from '@/routes/payroll-deductions';
import type { BreadcrumbItem } from '@/types';
import DateRangeFilter from './partials/DateRangeFilter.vue';
import OvertimePaymentToggle from './partials/OvertimePaymentToggle.vue';

type BreakByType = {
    name: string;
    is_paid: boolean;
    icon: string;
    color: string;
    total_minutes: number;
    count: number;
};

type DailyBreakdown = {
    date: string;
    gross_hours: number;
    net_hours: number;
    regular_hours: number;
    night_hours: number;
    sunday_holiday_hours: number;
    night_sunday_hours: number;
    overtime_day_hours: number;
    overtime_night_hours: number;
    overtime_day_sunday_hours: number;
    overtime_night_sunday_hours: number;
};

type CostDetail = {
    type: string;
    hours: number;
    rate: number;
    surcharge: number;
    subtotal: number;
    compensated: boolean;
};

type Report = {
    employee: {
        id: number;
        name: string;
        department: string | null;
        position: string | null;
        hourly_rate: number;
        salary_type: string;
        monthly_base_salary: number | null;
    };
    totals: {
        days_worked: number;
        gross_hours: number;
        break_hours: number;
        net_hours: number;
        regular_hours: number;
        night_hours: number;
        sunday_holiday_hours: number;
        night_sunday_hours: number;
        overtime_day_hours: number;
        overtime_night_hours: number;
        overtime_day_sunday_hours: number;
        overtime_night_sunday_hours: number;
    };
    breaks_by_type: BreakByType[];
    daily_breakdown: DailyBreakdown[];
    cost_summary: {
        regular: number;
        night: number;
        sunday_holiday: number;
        night_sunday: number;
        overtime_day: number;
        overtime_night: number;
        overtime_day_sunday: number;
        overtime_night_sunday: number;
        base: number;
        total: number;
        salary_type: string;
        pay_overtime: boolean;
        details: CostDetail[];
    };
    deductions: {
        days: number;
        amount: number;
        capped: boolean;
        items: Array<{
            id: number;
            effective_date: string;
            days: number;
            reason: string;
            notes: string | null;
        }>;
    };
    period: { start: string; end: string };
};

const DEDUCTION_REASONS = [
    'FaltaInjustificada',
    'LicenciaNoRemunerada',
    'PermisoNoRemunerado',
    'Otro',
] as const;

const props = defineProps<{
    report: Report;
    filters: {
        date_range: string;
        start_date: string;
        end_date: string;
        employee_id: number;
        pay_overtime: boolean;
    };
    employees: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();

const payOvertime = ref(props.filters.pay_overtime);

const isMonthly = computed(
    () => props.report.cost_summary.salary_type === 'monthly',
);

// Hay algo que mostrar si trabajó días o si tiene salario base (mensual con 0 turnos igual cobra base).
const hasReportData = computed(
    () =>
        props.report.totals.days_worked > 0 ||
        (props.report.cost_summary.base ?? 0) > 0,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('reports.breadcrumb'), href: '/reports' },
    { title: t('reports.employee_report'), href: '#' },
];

const dateFilter = ref({
    date_range: props.filters.date_range as
        | 'day'
        | 'week'
        | 'biweekly'
        | 'month'
        | 'custom',
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const selectedEmployee = ref(String(props.filters.employee_id));

function applyFilter() {
    router.get('/reports/employee', {
        date_range: dateFilter.value.date_range,
        start_date: dateFilter.value.start_date,
        end_date: dateFilter.value.end_date,
        employee_id: selectedEmployee.value,
        pay_overtime: payOvertime.value ? 1 : 0,
    });
}

function setPayOvertime(value: boolean) {
    payOvertime.value = value;
    router.get(
        '/reports/employee',
        {
            date_range: props.filters.date_range,
            start_date: props.filters.start_date,
            end_date: props.filters.end_date,
            employee_id: props.filters.employee_id,
            pay_overtime: value ? 1 : 0,
        },
        { preserveScroll: true },
    );
}

function exportQueryParams(): string {
    const params = new URLSearchParams({
        date_range: props.filters.date_range,
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
        employee_id: String(props.filters.employee_id),
        pay_overtime: payOvertime.value ? '1' : '0',
    });
    return '?' + params.toString();
}

function downloadExcel() {
    window.location.href = exportEmployeeExcel.url() + exportQueryParams();
}

function downloadPdf() {
    window.location.href = exportEmployeePdf.url() + exportQueryParams();
}

const avgPerDay = computed(() => {
    if (props.report.totals.days_worked === 0) return formatDecimalHours(0);
    return formatDecimalHours(
        props.report.totals.net_hours / props.report.totals.days_worked,
    );
});

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    }).format(value);
}

// --- Descuentos por novedad (solo empleados monthly) ---
const showDeductionForm = ref(false);

const deductionForm = useForm({
    employee_id: props.filters.employee_id,
    effective_date: props.filters.start_date,
    days: 1,
    reason: 'FaltaInjustificada' as string,
    notes: '',
});

function submitDeduction() {
    deductionForm.submit(storeDeduction(), {
        preserveScroll: true,
        onSuccess: () => {
            deductionForm.reset('days', 'notes');
            showDeductionForm.value = false;
        },
    });
}

function deleteDeduction(id: number) {
    router.delete(destroyDeduction.url(id), { preserveScroll: true });
}

function reasonLabel(reason: string): string {
    return t(`reports.deductions.reasons.${reason}`);
}

function hourTypeLabel(type: string): string {
    const map: Record<string, string> = {
        regular: t('reports.hours.regular'),
        night: t('reports.hours.night'),
        sunday_holiday: t('reports.hours.sunday_holiday'),
        night_sunday: t('reports.hours.night_sunday'),
        overtime_day: t('reports.hours.overtime_day'),
        overtime_night: t('reports.hours.overtime_night'),
        overtime_day_sunday: t('reports.hours.overtime_day_sunday'),
        overtime_night_sunday: t('reports.hours.overtime_night_sunday'),
    };
    return map[type] || type;
}

// --- Charts ---
const hoursChartEl = ref<HTMLElement | null>(null);
const trendChartEl = ref<HTMLElement | null>(null);
const breaksChartEl = ref<HTMLElement | null>(null);

onMounted(async () => {
    if (props.report.totals.days_worked === 0) return;

    const ApexCharts = (await import('apexcharts')).default;

    // Hours breakdown stacked bar
    if (hoursChartEl.value && props.report.daily_breakdown.length > 0) {
        const categories = props.report.daily_breakdown.map((d) => d.date);
        new ApexCharts(hoursChartEl.value, {
            chart: {
                type: 'bar',
                stacked: true,
                height: 280,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            series: [
                {
                    name: t('reports.hours.regular'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.regular_hours,
                    ),
                    color: '#3b82f6',
                },
                {
                    name: t('reports.hours.night'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.night_hours,
                    ),
                    color: '#6366f1',
                },
                {
                    name: t('reports.hours.sunday_holiday'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.sunday_holiday_hours,
                    ),
                    color: '#ef4444',
                },
                {
                    name: t('reports.hours.night_sunday'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.night_sunday_hours,
                    ),
                    color: '#a855f7',
                },
                {
                    name: t('reports.hours.overtime_day'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.overtime_day_hours,
                    ),
                    color: '#f59e0b',
                },
                {
                    name: t('reports.hours.overtime_night'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.overtime_night_hours,
                    ),
                    color: '#f97316',
                },
                {
                    name: t('reports.hours.overtime_day_sunday'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.overtime_day_sunday_hours,
                    ),
                    color: '#ec4899',
                },
                {
                    name: t('reports.hours.overtime_night_sunday'),
                    data: props.report.daily_breakdown.map(
                        (d) => d.overtime_night_sunday_hours,
                    ),
                    color: '#dc2626',
                },
            ],
            xaxis: { categories, labels: { style: { fontSize: '11px' } } },
            yaxis: {
                title: { text: 'h' },
                labels: { formatter: (v: number) => `${v}h` },
            },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { y: { formatter: (v: number) => `${v}h` } },
            grid: { borderColor: '#e5e7eb40' },
        }).render();
    }

    // Net hours trend line
    if (trendChartEl.value && props.report.daily_breakdown.length > 0) {
        new ApexCharts(trendChartEl.value, {
            chart: {
                type: 'area',
                height: 220,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            series: [
                {
                    name: t('reports.kpi.net_hours'),
                    data: props.report.daily_breakdown.map((d) => d.net_hours),
                },
            ],
            xaxis: {
                categories: props.report.daily_breakdown.map((d) => d.date),
                labels: { style: { fontSize: '11px' } },
            },
            yaxis: { labels: { formatter: (v: number) => `${v}h` } },
            colors: ['#10b981'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05,
                    stops: [0, 100],
                },
            },
            stroke: { width: 2, curve: 'smooth' },
            tooltip: { y: { formatter: (v: number) => `${v}h` } },
            grid: { borderColor: '#e5e7eb40' },
        }).render();
    }

    // Breaks donut
    if (breaksChartEl.value && props.report.breaks_by_type.length > 0) {
        new ApexCharts(breaksChartEl.value, {
            chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
            series: props.report.breaks_by_type.map((b) => b.total_minutes),
            labels: props.report.breaks_by_type.map((b) => b.name),
            colors: props.report.breaks_by_type.map(
                (b) => b.color || '#94a3b8',
            ),
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { y: { formatter: (v: number) => `${v} min` } },
            plotOptions: { pie: { donut: { size: '55%' } } },
        }).render();
    }
});
</script>

<template>
    <Head :title="t('reports.employee_report')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="router.get('/reports')"
                    >
                        <ArrowLeft class="size-4" />
                    </Button>
                    <div>
                        <h1 class="text-xl font-bold">
                            {{ report.employee.name }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ report.employee.department || '' }}
                            <span
                                v-if="
                                    report.employee.department &&
                                    report.employee.position
                                "
                            >
                                &middot;
                            </span>
                            {{ report.employee.position || '' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="mr-2 text-sm text-muted-foreground">
                        {{ report.period.start }} &rarr; {{ report.period.end }}
                    </span>
                    <Button
                        v-if="hasReportData"
                        variant="outline"
                        size="sm"
                        @click="downloadExcel"
                    >
                        <Download class="mr-1 size-3.5" />
                        {{ t('reports.export_excel') }}
                    </Button>
                    <Button
                        v-if="hasReportData"
                        variant="outline"
                        size="sm"
                        @click="downloadPdf"
                    >
                        <Download class="mr-1 size-3.5" />
                        {{ t('reports.export_pdf') }}
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent
                    class="flex flex-col gap-4 pt-6 sm:flex-row sm:items-end"
                >
                    <div class="flex-1">
                        <DateRangeFilter v-model="dateFilter" />
                    </div>
                    <div class="w-full sm:w-48">
                        <Select v-model="selectedEmployee">
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="t('reports.select_employee')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="String(emp.id)"
                                >
                                    {{ emp.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button @click="applyFilter">{{
                        t('reports.filter')
                    }}</Button>
                </CardContent>
            </Card>

            <!-- Empty state -->
            <div
                v-if="!hasReportData"
                class="py-16 text-center text-muted-foreground"
            >
                <Calendar class="mx-auto mb-3 size-12 opacity-30" />
                <p class="text-lg font-medium">{{ t('reports.no_data') }}</p>
            </div>

            <template v-else>
                <!-- Overtime payment toggle -->
                <div class="flex justify-end">
                    <OvertimePaymentToggle
                        :model-value="payOvertime"
                        class="w-full sm:w-auto sm:min-w-[340px]"
                        @update:model-value="setPayOvertime"
                    />
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between pb-2"
                        >
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.kpi.days_worked')
                            }}</CardTitle>
                            <Calendar class="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">
                                {{ report.totals.days_worked }}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between pb-2"
                        >
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.kpi.net_hours')
                            }}</CardTitle>
                            <Clock class="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">
                                {{
                                    formatDecimalHours(report.totals.net_hours)
                                }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('reports.kpi.avg_per_day') }}:
                                {{ avgPerDay }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between pb-2"
                        >
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.kpi.gross_hours')
                            }}</CardTitle>
                            <TrendingUp class="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">
                                {{
                                    formatDecimalHours(
                                        report.totals.gross_hours,
                                    )
                                }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('reports.kpi.break_hours') }}:
                                {{
                                    formatDecimalHours(
                                        report.totals.break_hours,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between pb-2"
                        >
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.kpi.total_cost')
                            }}</CardTitle>
                            <DollarSign class="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">
                                {{ formatCurrency(report.cost_summary.total) }}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Hour type mini cards (8 types) -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div
                        class="flex items-center gap-3 rounded-lg bg-blue-50 p-3 dark:bg-blue-950/30"
                    >
                        <Sun class="size-5 text-blue-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.regular_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.regular') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-indigo-50 p-3 dark:bg-indigo-950/30"
                    >
                        <Moon class="size-5 text-indigo-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.night_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.night') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-red-50 p-3 dark:bg-red-950/30"
                    >
                        <Calendar class="size-5 text-red-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.sunday_holiday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.sunday_holiday') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-purple-50 p-3 dark:bg-purple-950/30"
                    >
                        <Moon class="size-5 text-purple-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.night_sunday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.night_sunday') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30"
                    >
                        <Zap class="size-5 text-amber-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.overtime_day_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_day') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-orange-50 p-3 dark:bg-orange-950/30"
                    >
                        <Zap class="size-5 text-orange-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.overtime_night_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_night') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-pink-50 p-3 dark:bg-pink-950/30"
                    >
                        <Zap class="size-5 text-pink-500" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.overtime_day_sunday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_day_sunday') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-rose-50 p-3 dark:bg-rose-950/30"
                    >
                        <Zap class="size-5 text-rose-600" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals
                                            .overtime_night_sunday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_night_sunday') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div
                    v-if="report.totals.days_worked > 0"
                    class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                >
                    <!-- Hours Breakdown Chart -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.charts.hours_breakdown')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="hoursChartEl" />
                        </CardContent>
                    </Card>

                    <!-- Net Hours Trend -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.charts.daily_trend')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="trendChartEl" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Breaks + Cost Row -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Breaks Donut -->
                    <Card v-if="report.breaks_by_type.length > 0">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.breaks.title')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="breaksChartEl" />
                            <div class="mt-3 divide-y">
                                <div
                                    v-for="b in report.breaks_by_type"
                                    :key="b.name"
                                    class="flex items-center justify-between py-2 text-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ b.icon }}</span>
                                        <span>{{ b.name }}</span>
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{
                                                b.is_paid
                                                    ? t('reports.breaks.paid')
                                                    : t('reports.breaks.unpaid')
                                            }}
                                        </Badge>
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ b.total_minutes }} min ({{
                                            b.count
                                        }}x)
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Cost Table -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{
                                t('reports.costs.title')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="border-b text-left text-xs text-muted-foreground"
                                        >
                                            <th class="pb-2">
                                                {{
                                                    t('reports.costs.hour_type')
                                                }}
                                            </th>
                                            <th class="pb-2 text-right">
                                                {{ t('reports.costs.hours') }}
                                            </th>
                                            <th
                                                class="hidden pb-2 text-right sm:table-cell"
                                            >
                                                {{
                                                    t('reports.costs.surcharge')
                                                }}
                                            </th>
                                            <th class="pb-2 text-right">
                                                {{
                                                    t('reports.costs.subtotal')
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr
                                            v-if="isMonthly"
                                            class="bg-emerald-50/60 dark:bg-emerald-950/20"
                                        >
                                            <td class="py-2 font-medium">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <DollarSign
                                                        class="size-3.5 text-emerald-600 dark:text-emerald-400"
                                                    />
                                                    {{
                                                        t(
                                                            'reports.costs.base_salary',
                                                        )
                                                    }}
                                                </div>
                                                <span
                                                    class="text-xs font-normal text-muted-foreground"
                                                    >{{
                                                        t(
                                                            'reports.costs.base_salary_hint',
                                                        )
                                                    }}</span
                                                >
                                            </td>
                                            <td
                                                class="py-2 text-right text-muted-foreground"
                                            >
                                                —
                                            </td>
                                            <td
                                                class="hidden py-2 text-right text-muted-foreground sm:table-cell"
                                            >
                                                —
                                            </td>
                                            <td
                                                class="py-2 text-right font-medium text-emerald-700 dark:text-emerald-400"
                                            >
                                                {{
                                                    formatCurrency(
                                                        report.cost_summary
                                                            .base,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="detail in report.cost_summary
                                                .details"
                                            :key="detail.type"
                                        >
                                            <td class="py-2">
                                                {{ hourTypeLabel(detail.type) }}
                                            </td>
                                            <td class="py-2 text-right">
                                                {{
                                                    formatDecimalHours(
                                                        detail.hours,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="hidden py-2 text-right sm:table-cell"
                                            >
                                                <span
                                                    v-if="detail.surcharge > 0"
                                                    >+{{
                                                        detail.surcharge
                                                    }}%</span
                                                >
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td
                                                class="py-2 text-right font-medium"
                                            >
                                                <div
                                                    class="flex items-center justify-end gap-2"
                                                >
                                                    <Badge
                                                        v-if="
                                                            detail.compensated &&
                                                            detail.hours > 0
                                                        "
                                                        variant="secondary"
                                                        class="text-[10px] font-normal"
                                                    >
                                                        {{
                                                            t(
                                                                'reports.overtime_payment.compensated_badge',
                                                            )
                                                        }}
                                                    </Badge>
                                                    <span
                                                        :class="
                                                            detail.compensated
                                                                ? 'text-muted-foreground'
                                                                : ''
                                                        "
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                detail.subtotal,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                isMonthly &&
                                                report.deductions.amount > 0
                                            "
                                            class="bg-rose-50/60 dark:bg-rose-950/20"
                                        >
                                            <td class="py-2 font-medium">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <Minus
                                                        class="size-3.5 text-rose-600 dark:text-rose-400"
                                                    />
                                                    {{
                                                        t(
                                                            'reports.deductions.line_label',
                                                        )
                                                    }}
                                                </div>
                                                <span
                                                    class="text-xs font-normal text-muted-foreground"
                                                >
                                                    {{
                                                        t(
                                                            'reports.deductions.line_hint',
                                                            {
                                                                days: report
                                                                    .deductions
                                                                    .days,
                                                            },
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="py-2 text-right text-muted-foreground"
                                            >
                                                —
                                            </td>
                                            <td
                                                class="hidden py-2 text-right text-muted-foreground sm:table-cell"
                                            >
                                                —
                                            </td>
                                            <td
                                                class="py-2 text-right font-medium text-rose-700 dark:text-rose-400"
                                            >
                                                −{{
                                                    formatCurrency(
                                                        report.deductions
                                                            .amount,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t font-semibold">
                                            <td class="pt-2">
                                                {{ t('reports.costs.total') }}
                                            </td>
                                            <td class="pt-2 text-right">
                                                {{
                                                    formatDecimalHours(
                                                        report.totals.net_hours,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="hidden pt-2 sm:table-cell"
                                            />
                                            <td class="pt-2 text-right">
                                                {{
                                                    formatCurrency(
                                                        report.cost_summary
                                                            .total,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Novedades / descuentos del salario base (solo monthly) -->
                    <Card v-if="isMonthly">
                        <CardHeader
                            class="flex flex-row items-center justify-between pb-2"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Minus class="size-4 text-muted-foreground" />
                                {{ t('reports.deductions.title') }}
                            </CardTitle>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="showDeductionForm = !showDeductionForm"
                            >
                                <Plus class="size-4" />
                                {{ t('reports.deductions.add') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <form
                                v-if="showDeductionForm"
                                class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4"
                                @submit.prevent="submitDeduction"
                            >
                                <div class="space-y-1">
                                    <Label for="ded-date">{{
                                        t('reports.deductions.date')
                                    }}</Label>
                                    <Input
                                        id="ded-date"
                                        v-model="deductionForm.effective_date"
                                        type="date"
                                        :min="filters.start_date"
                                        :max="filters.end_date"
                                        required
                                    />
                                    <p
                                        v-if="
                                            deductionForm.errors.effective_date
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            deductionForm.errors.effective_date
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <Label for="ded-days">{{
                                        t('reports.deductions.days')
                                    }}</Label>
                                    <Input
                                        id="ded-days"
                                        v-model="deductionForm.days"
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        required
                                    />
                                    <p
                                        v-if="deductionForm.errors.days"
                                        class="text-xs text-destructive"
                                    >
                                        {{ deductionForm.errors.days }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <Label>{{
                                        t('reports.deductions.reason')
                                    }}</Label>
                                    <Select v-model="deductionForm.reason">
                                        <SelectTrigger
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="r in DEDUCTION_REASONS"
                                                :key="r"
                                                :value="r"
                                                >{{
                                                    reasonLabel(r)
                                                }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1">
                                    <Label for="ded-notes">{{
                                        t('reports.deductions.notes')
                                    }}</Label>
                                    <Input
                                        id="ded-notes"
                                        v-model="deductionForm.notes"
                                        :placeholder="
                                            t(
                                                'reports.deductions.notes_placeholder',
                                            )
                                        "
                                    />
                                </div>
                                <div
                                    class="flex items-center gap-2 sm:col-span-2 lg:col-span-4"
                                >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="deductionForm.processing"
                                        >{{
                                            t('reports.deductions.save')
                                        }}</Button
                                    >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="showDeductionForm = false"
                                    >
                                        {{ t('reports.deductions.cancel') }}
                                    </Button>
                                    <p
                                        v-if="deductionForm.errors.employee_id"
                                        class="text-xs text-destructive"
                                    >
                                        {{ deductionForm.errors.employee_id }}
                                    </p>
                                </div>
                            </form>

                            <p
                                v-if="report.deductions.capped"
                                class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"
                            >
                                {{ t('reports.deductions.capped_warning') }}
                            </p>

                            <div
                                v-if="report.deductions.items.length > 0"
                                class="divide-y rounded-lg border"
                            >
                                <div
                                    v-for="item in report.deductions.items"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                >
                                    <div>
                                        <span class="font-medium">{{
                                            reasonLabel(item.reason)
                                        }}</span>
                                        <span class="text-muted-foreground">
                                            · {{ item.days }}
                                            {{
                                                t(
                                                    'reports.deductions.days_unit',
                                                )
                                            }}
                                            · {{ item.effective_date }}
                                        </span>
                                        <p
                                            v-if="item.notes"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.notes }}
                                        </p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive"
                                        @click="deleteDeduction(item.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ t('reports.deductions.empty') }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
