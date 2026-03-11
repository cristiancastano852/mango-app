<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Clock, DollarSign, Download, Moon, Sun, TrendingUp, Zap } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { exportEmployeeExcel, exportEmployeePdf } from '@/actions/App/Http/Controllers/ReportController';
import DateRangeFilter from './partials/DateRangeFilter.vue';

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
    overtime_hours: number;
    sunday_holiday_hours: number;
};

type CostDetail = {
    type: string;
    hours: number;
    rate: number;
    surcharge: number;
    subtotal: number;
};

type Report = {
    employee: {
        id: number;
        name: string;
        department: string | null;
        position: string | null;
        hourly_rate: number;
    };
    totals: {
        days_worked: number;
        gross_hours: number;
        break_hours: number;
        net_hours: number;
        regular_hours: number;
        overtime_hours: number;
        night_hours: number;
        sunday_holiday_hours: number;
    };
    breaks_by_type: BreakByType[];
    daily_breakdown: DailyBreakdown[];
    cost_summary: {
        regular: number;
        night: number;
        overtime: number;
        sunday_holiday: number;
        total: number;
        details: CostDetail[];
    };
    period: { start: string; end: string };
};

const props = defineProps<{
    report: Report;
    filters: {
        date_range: string;
        start_date: string;
        end_date: string;
        employee_id: number;
    };
    employees: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('reports.breadcrumb'), href: '/reports' },
    { title: t('reports.employee_report'), href: '#' },
];

const dateFilter = ref({
    date_range: props.filters.date_range as 'day' | 'week' | 'biweekly' | 'month' | 'custom',
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
    }, { preserveState: true });
}

function exportQueryParams(): string {
    const params = new URLSearchParams({
        date_range: props.filters.date_range,
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
        employee_id: String(props.filters.employee_id),
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
    if (props.report.totals.days_worked === 0) return 0;
    return (props.report.totals.net_hours / props.report.totals.days_worked).toFixed(1);
});

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(value);
}

function hourTypeLabel(type: string): string {
    const map: Record<string, string> = {
        regular: t('reports.hours.regular'),
        night: t('reports.hours.night'),
        overtime: t('reports.hours.overtime'),
        sunday_holiday: t('reports.hours.sunday_holiday'),
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
        const categories = props.report.daily_breakdown.map(d => d.date);
        new ApexCharts(hoursChartEl.value, {
            chart: { type: 'bar', stacked: true, height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: t('reports.hours.regular'), data: props.report.daily_breakdown.map(d => d.regular_hours), color: '#3b82f6' },
                { name: t('reports.hours.night'), data: props.report.daily_breakdown.map(d => d.night_hours), color: '#6366f1' },
                { name: t('reports.hours.overtime'), data: props.report.daily_breakdown.map(d => d.overtime_hours), color: '#f59e0b' },
                { name: t('reports.hours.sunday_holiday'), data: props.report.daily_breakdown.map(d => d.sunday_holiday_hours), color: '#ef4444' },
            ],
            xaxis: { categories, labels: { style: { fontSize: '11px' } } },
            yaxis: { title: { text: 'h' }, labels: { formatter: (v: number) => `${v}h` } },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { y: { formatter: (v: number) => `${v}h` } },
            grid: { borderColor: '#e5e7eb40' },
        }).render();
    }

    // Net hours trend line
    if (trendChartEl.value && props.report.daily_breakdown.length > 0) {
        new ApexCharts(trendChartEl.value, {
            chart: { type: 'area', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: t('reports.kpi.net_hours'), data: props.report.daily_breakdown.map(d => d.net_hours) }],
            xaxis: { categories: props.report.daily_breakdown.map(d => d.date), labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: (v: number) => `${v}h` } },
            colors: ['#10b981'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
            stroke: { width: 2, curve: 'smooth' },
            tooltip: { y: { formatter: (v: number) => `${v}h` } },
            grid: { borderColor: '#e5e7eb40' },
        }).render();
    }

    // Breaks donut
    if (breaksChartEl.value && props.report.breaks_by_type.length > 0) {
        new ApexCharts(breaksChartEl.value, {
            chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
            series: props.report.breaks_by_type.map(b => b.total_minutes),
            labels: props.report.breaks_by_type.map(b => b.name),
            colors: props.report.breaks_by_type.map(b => b.color || '#94a3b8'),
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
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="router.get('/reports')">
                        <ArrowLeft class="size-4" />
                    </Button>
                    <div>
                        <h1 class="text-xl font-bold">{{ report.employee.name }}</h1>
                        <p class="text-muted-foreground text-sm">
                            {{ report.employee.department || '' }}
                            <span v-if="report.employee.department && report.employee.position"> &middot; </span>
                            {{ report.employee.position || '' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground mr-2 text-sm">
                        {{ report.period.start }} &rarr; {{ report.period.end }}
                    </span>
                    <Button v-if="report.totals.days_worked > 0" variant="outline" size="sm" @click="downloadExcel">
                        <Download class="mr-1 size-3.5" />
                        {{ t('reports.export_excel') }}
                    </Button>
                    <Button v-if="report.totals.days_worked > 0" variant="outline" size="sm" @click="downloadPdf">
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
                        <Select v-model="selectedEmployee">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('reports.select_employee')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                                    {{ emp.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button @click="applyFilter">{{ t('reports.filter') }}</Button>
                </CardContent>
            </Card>

            <!-- Empty state -->
            <div v-if="report.totals.days_worked === 0" class="text-muted-foreground py-16 text-center">
                <Calendar class="mx-auto mb-3 size-12 opacity-30" />
                <p class="text-lg font-medium">{{ t('reports.no_data') }}</p>
            </div>

            <template v-else>
                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.days_worked') }}</CardTitle>
                            <Calendar class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ report.totals.days_worked }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.net_hours') }}</CardTitle>
                            <Clock class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ report.totals.net_hours }}h</div>
                            <p class="text-muted-foreground text-xs">{{ t('reports.kpi.avg_per_day') }}: {{ avgPerDay }}h</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.kpi.gross_hours') }}</CardTitle>
                            <TrendingUp class="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ report.totals.gross_hours }}h</div>
                            <p class="text-muted-foreground text-xs">{{ t('reports.kpi.break_hours') }}: {{ report.totals.break_hours }}h</p>
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

                <!-- Hour type mini cards -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="bg-blue-50 dark:bg-blue-950/30 flex items-center gap-3 rounded-lg p-3">
                        <Sun class="size-5 text-blue-500" />
                        <div>
                            <div class="text-lg font-semibold">{{ report.totals.regular_hours }}h</div>
                            <div class="text-muted-foreground text-xs">{{ t('reports.hours.regular') }}</div>
                        </div>
                    </div>
                    <div class="bg-indigo-50 dark:bg-indigo-950/30 flex items-center gap-3 rounded-lg p-3">
                        <Moon class="size-5 text-indigo-500" />
                        <div>
                            <div class="text-lg font-semibold">{{ report.totals.night_hours }}h</div>
                            <div class="text-muted-foreground text-xs">{{ t('reports.hours.night') }}</div>
                        </div>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-950/30 flex items-center gap-3 rounded-lg p-3">
                        <Zap class="size-5 text-amber-500" />
                        <div>
                            <div class="text-lg font-semibold">{{ report.totals.overtime_hours }}h</div>
                            <div class="text-muted-foreground text-xs">{{ t('reports.hours.overtime') }}</div>
                        </div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-950/30 flex items-center gap-3 rounded-lg p-3">
                        <Calendar class="size-5 text-red-500" />
                        <div>
                            <div class="text-lg font-semibold">{{ report.totals.sunday_holiday_hours }}h</div>
                            <div class="text-muted-foreground text-xs">{{ t('reports.hours.sunday_holiday') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Hours Breakdown Chart -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.charts.hours_breakdown') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="hoursChartEl" />
                        </CardContent>
                    </Card>

                    <!-- Net Hours Trend -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.charts.daily_trend') }}</CardTitle>
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
                            <CardTitle class="text-sm font-medium">{{ t('reports.breaks.title') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div ref="breaksChartEl" />
                            <div class="mt-3 divide-y">
                                <div v-for="b in report.breaks_by_type" :key="b.name" class="flex items-center justify-between py-2 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span>{{ b.icon }}</span>
                                        <span>{{ b.name }}</span>
                                        <Badge variant="outline" class="text-xs">
                                            {{ b.is_paid ? t('reports.breaks.paid') : t('reports.breaks.unpaid') }}
                                        </Badge>
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ b.total_minutes }} min ({{ b.count }}x)
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Cost Table -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{ t('reports.costs.title') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-muted-foreground border-b text-left text-xs">
                                            <th class="pb-2">{{ t('reports.costs.hour_type') }}</th>
                                            <th class="pb-2 text-right">{{ t('reports.costs.hours') }}</th>
                                            <th class="hidden pb-2 text-right sm:table-cell">{{ t('reports.costs.surcharge') }}</th>
                                            <th class="pb-2 text-right">{{ t('reports.costs.subtotal') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="detail in report.cost_summary.details" :key="detail.type">
                                            <td class="py-2">{{ hourTypeLabel(detail.type) }}</td>
                                            <td class="py-2 text-right">{{ detail.hours }}h</td>
                                            <td class="hidden py-2 text-right sm:table-cell">
                                                <span v-if="detail.surcharge > 0">+{{ detail.surcharge }}%</span>
                                                <span v-else class="text-muted-foreground">-</span>
                                            </td>
                                            <td class="py-2 text-right font-medium">{{ formatCurrency(detail.subtotal) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t font-semibold">
                                            <td class="pt-2">{{ t('reports.costs.total') }}</td>
                                            <td class="pt-2 text-right">{{ report.totals.net_hours }}h</td>
                                            <td class="hidden pt-2 sm:table-cell" />
                                            <td class="pt-2 text-right">{{ formatCurrency(report.cost_summary.total) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
