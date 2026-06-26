<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    CalendarRange,
    Clock,
    DollarSign,
    FileSpreadsheet,
    FileText,
    Moon,
    RefreshCw,
    Sun,
    TrendingUp,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    exportEmployeeExcel,
    exportEmployeePdf,
    recalculateEmployee,
} from '@/actions/App/Http/Controllers/ReportController';
import DailyWorkTable from '@/components/DailyWorkTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDecimalHours } from '@/lib/utils';
import type { BreadcrumbItem, DailyWorkDay } from '@/types';
import DateRangeFilter from './partials/DateRangeFilter.vue';
import OvertimePaymentToggle from './partials/OvertimePaymentToggle.vue';
import ReportAdjustments from './partials/ReportAdjustments.vue';

type CostDetail = {
    type: string;
    hours: number;
    rate: number;
    surcharge: number;
    subtotal: number;
    compensated: boolean;
};

type Adjustment = {
    id: number;
    date: string;
    type: 'Bonus' | 'Deduction';
    amount: number;
    concept: string;
    note: string | null;
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
        dominical_hours: number;
        night_dominical_hours: number;
        holiday_hours: number;
        night_holiday_hours: number;
        overtime_day_hours: number;
        overtime_night_hours: number;
        overtime_day_dominical_hours: number;
        overtime_night_dominical_hours: number;
        overtime_day_holiday_hours: number;
        overtime_night_holiday_hours: number;
        dominical_worked_days: number;
    };
    cost_summary: {
        regular: number;
        night: number;
        dominical: number;
        night_dominical: number;
        holiday: number;
        night_holiday: number;
        overtime_day: number;
        overtime_night: number;
        overtime_day_dominical: number;
        overtime_night_dominical: number;
        overtime_day_holiday: number;
        overtime_night_holiday: number;
        base: number;
        transport_allowance: number;
        total: number;
        social_security_base: number;
        health_rate: number;
        health_deduction: number;
        pension_rate: number;
        pension_deduction: number;
        net_pay: number;
        bonus_total: number;
        deduction_total: number;
        final_pay: number;
        salary_type: string;
        pay_overtime: boolean;
        pay_dominical: boolean;
        pay_night_dominical: boolean;
        pay_night_holiday: boolean;
        pay_overtime_dominical: boolean;
        pay_overtime_holiday: boolean;
        pay_overtime_night: boolean;
        dominical_mode: string;
        normal_day_value: number;
        dominical_worked_days: number;
        dominical_paid_days: number;
        holiday_mode: string;
        holiday_worked_days: number;
        details: CostDetail[];
    };
    daily_breakdown: DailyWorkDay[];
    adjustments: Adjustment[];
    period: { start: string; end: string };
};

const props = defineProps<{
    report: Report;
    filters: {
        date_range: string;
        start_date: string;
        end_date: string;
        employee_id: number;
        pay_overtime: boolean;
        dominical_payable_count: number | null;
    };
    employees: Array<{ id: number; name: string }>;
}>();

const { t, locale } = useI18n();

const payOvertime = ref(props.filters.pay_overtime);
const dominicalPayableCount = ref<number | null>(props.filters.dominical_payable_count);
const isDominicalByDay = computed(() => props.report.cost_summary.dominical_mode === 'day');

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

const presetLabel = computed(() =>
    t(`reports.presets.${props.filters.date_range}`),
);

function formatPeriodDate(iso: string): string {
    const [year, month, day] = iso.split('-').map(Number);
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(year, month - 1, day));
}

const formattedPeriod = computed(() => {
    const start = formatPeriodDate(props.report.period.start);
    const end = formatPeriodDate(props.report.period.end);
    const year = props.report.period.end.split('-')[0];
    return `${start} → ${end} ${year}`;
});

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
            ...(dominicalPayableCount.value !== null ? { dominical_payable_count: dominicalPayableCount.value } : {}),
        },
        { preserveScroll: true },
    );
}

function setDominicalPayableCount(value: number) {
    dominicalPayableCount.value = value;
    router.get(
        '/reports/employee',
        {
            date_range: props.filters.date_range,
            start_date: props.filters.start_date,
            end_date: props.filters.end_date,
            employee_id: props.filters.employee_id,
            pay_overtime: payOvertime.value ? 1 : 0,
            dominical_payable_count: value,
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
    if (dominicalPayableCount.value !== null) {
        params.append('dominical_payable_count', String(dominicalPayableCount.value));
    }
    return '?' + params.toString();
}

function downloadExcel() {
    window.location.href = exportEmployeeExcel.url() + exportQueryParams();
}

function downloadPdf() {
    window.location.href = exportEmployeePdf.url() + exportQueryParams();
}

const recalculating = ref(false);

function recalculate() {
    if (
        !window.confirm(
            '¿Recalcular las horas de este empleado para el periodo mostrado con la configuración vigente? Se vuelven a clasificar los registros (franja nocturna, límites de jornada, día dominical, pausas). No modifica los fichajes.',
        )
    ) {
        return;
    }

    router.post(
        recalculateEmployee.url(),
        {
            date_range: props.filters.date_range,
            start_date: props.filters.start_date,
            end_date: props.filters.end_date,
            employee_id: props.filters.employee_id,
            pay_overtime: payOvertime.value ? 1 : 0,
            ...(dominicalPayableCount.value !== null
                ? { dominical_payable_count: dominicalPayableCount.value }
                : {}),
        },
        {
            preserveScroll: true,
            onStart: () => (recalculating.value = true),
            onFinish: () => (recalculating.value = false),
        },
    );
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

// Recargos premium que se ocultan cuando su toggle de empresa está desactivado.
// Las horas ya quedan reflejadas en las filas base (regular/nocturno/extra), así que
// no se pierde información. El festivo diurno (`holiday`) nunca se oculta (se paga por ley)
// y una fila con pago real (dominical hourly OFF: subtotal > 0) tampoco se oculta.
const premiumPayFlag: Record<string, boolean> = {
    dominical: props.report.cost_summary.pay_dominical,
    night_dominical: props.report.cost_summary.pay_night_dominical,
    night_holiday: props.report.cost_summary.pay_night_holiday,
    overtime_day_dominical: props.report.cost_summary.pay_overtime_dominical,
    overtime_night_dominical:
        props.report.cost_summary.pay_overtime_dominical &&
        props.report.cost_summary.pay_overtime_night,
    overtime_day_holiday: props.report.cost_summary.pay_overtime_holiday,
    overtime_night_holiday:
        props.report.cost_summary.pay_overtime_holiday &&
        props.report.cost_summary.pay_overtime_night,
    overtime_night: props.report.cost_summary.pay_overtime_night,
};

const visibleDetails = computed(() =>
    props.report.cost_summary.details.filter((detail) => {
        const flag = premiumPayFlag[detail.type];
        if (flag === undefined || flag) {
            return true;
        }
        // Toggle apagado: ocultar solo si la fila no representa pago real.
        return detail.subtotal !== 0 || detail.hours !== 0;
    }),
);

// Para dominical/festivo en modo por día se muestran los días pagados en lugar de las horas.
function detailHoursDisplay(detail: CostDetail): string {
    if (detail.type === 'dominical' && isDominicalByDay.value) {
        return t('reports.costs.days_count', props.report.cost_summary.dominical_paid_days);
    }
    if (
        detail.type === 'holiday' &&
        props.report.cost_summary.holiday_mode === 'day'
    ) {
        return t('reports.costs.days_count', props.report.cost_summary.holiday_worked_days);
    }
    return formatDecimalHours(detail.hours);
}

function hourTypeLabel(type: string): string {
    const map: Record<string, string> = {
        regular: t('reports.hours.regular'),
        night: t('reports.hours.night'),
        dominical: t('reports.hours.dominical'),
        night_dominical: t('reports.hours.night_dominical'),
        holiday: t('reports.hours.holiday'),
        night_holiday: t('reports.hours.night_holiday'),
        overtime_day: t('reports.hours.overtime_day'),
        overtime_night: t('reports.hours.overtime_night'),
        overtime_day_dominical: t('reports.hours.overtime_day_dominical'),
        overtime_night_dominical: t('reports.hours.overtime_night_dominical'),
        overtime_day_holiday: t('reports.hours.overtime_day_holiday'),
        overtime_night_holiday: t('reports.hours.overtime_night_holiday'),
    };
    return map[type] || type;
}
</script>

<template>
    <Head :title="t('reports.employee_report')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4">
                <!-- Identity -->
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

                <!-- Action bar: period + exports -->
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="inline-flex items-center gap-3 self-start rounded-lg border border-primary/30 bg-primary/5 px-4 py-2.5"
                    >
                        <CalendarRange class="size-5 shrink-0 text-primary" />
                        <div class="flex flex-col leading-tight">
                            <span
                                class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                >{{ presetLabel }}</span
                            >
                            <span class="text-base font-semibold">{{
                                formattedPeriod
                            }}</span>
                        </div>
                    </div>
                    <div v-if="hasReportData" class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            :disabled="recalculating"
                            class="border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100 hover:text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-900/40 dark:hover:text-sky-200"
                            @click="recalculate"
                        >
                            <RefreshCw
                                class="mr-1.5 size-4"
                                :class="{ 'animate-spin': recalculating }"
                            />
                            {{ recalculating ? 'Recalculando…' : 'Recalcular' }}
                        </Button>
                        <Button
                            variant="outline"
                            class="border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/40 dark:hover:text-emerald-200"
                            @click="downloadExcel"
                        >
                            <FileSpreadsheet class="mr-1.5 size-4" />
                            {{ t('reports.export_excel') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100 hover:text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/40 dark:hover:text-rose-200"
                            @click="downloadPdf"
                        >
                            <FileText class="mr-1.5 size-4" />
                            {{ t('reports.export_pdf') }}
                        </Button>
                    </div>
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
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="w-full sm:w-48">
                            <Select v-model="selectedEmployee">
                                <SelectTrigger>
                                    <SelectValue
                                        :placeholder="
                                            t('reports.select_employee')
                                        "
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
                        <Button class="w-full sm:w-auto" @click="applyFilter">{{
                            t('reports.filter')
                        }}</Button>
                    </div>
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
                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:justify-end">
                    <!-- Dominical payable count (only in by-day mode) -->
                    <div
                        v-if="isDominicalByDay && report.cost_summary.dominical_worked_days > 0"
                        class="flex items-center justify-between gap-3 rounded-lg border bg-card p-3 sm:min-w-[340px]"
                    >
                        <div class="flex flex-col">
                            <span class="text-sm font-medium">
                                {{ t('reports.dominical.payable_count_label') }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ t('reports.dominical.worked_hint', { n: report.cost_summary.dominical_worked_days }) }}
                            </span>
                        </div>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            class="w-20 rounded-md border bg-background px-2 py-1 text-right text-sm"
                            :value="report.cost_summary.dominical_paid_days"
                            @change="setDominicalPayableCount(Math.max(0, Math.trunc(Number(($event.target as HTMLInputElement).value)) || 0))"
                        />
                    </div>
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
                                        report.totals.dominical_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.dominical') }}
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
                                        report.totals.night_dominical_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.night_dominical') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-red-50 p-3 dark:bg-red-950/30"
                    >
                        <Calendar class="size-5 text-red-600" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.holiday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.holiday') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-purple-50 p-3 dark:bg-purple-950/30"
                    >
                        <Moon class="size-5 text-purple-600" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.night_holiday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.night_holiday') }}
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
                                        report.totals.overtime_day_dominical_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_day_dominical') }}
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
                                            .overtime_night_dominical_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_night_dominical') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-pink-50 p-3 dark:bg-pink-950/30"
                    >
                        <Zap class="size-5 text-pink-600" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals.overtime_day_holiday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_day_holiday') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-rose-50 p-3 dark:bg-rose-950/30"
                    >
                        <Zap class="size-5 text-rose-700" />
                        <div>
                            <div class="text-lg font-semibold">
                                {{
                                    formatDecimalHours(
                                        report.totals
                                            .overtime_night_holiday_hours,
                                    )
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t('reports.hours.overtime_night_holiday') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cost Summary (most important) -->
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
                                        class="border-b text-left text-base font-bold tracking-wide text-foreground uppercase"
                                    >
                                        <th class="py-3">
                                            {{ t('reports.costs.hour_type') }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{ t('reports.costs.hours') }}
                                        </th>
                                        <th
                                            class="hidden py-3 text-right sm:table-cell"
                                        >
                                            {{ t('reports.costs.surcharge') }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{ t('reports.costs.subtotal') }}
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
                                                    report.cost_summary.base,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            (report.cost_summary
                                                .transport_allowance ?? 0) > 0
                                        "
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
                                                        'reports.costs.transport_allowance',
                                                    )
                                                }}
                                            </div>
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
                                                        .transport_allowance,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="detail in visibleDetails"
                                        :key="detail.type"
                                    >
                                        <td class="py-2">
                                            {{ hourTypeLabel(detail.type) }}
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ detailHoursDisplay(detail) }}
                                        </td>
                                        <td
                                            class="hidden py-2 text-right sm:table-cell"
                                        >
                                            <span v-if="detail.surcharge > 0"
                                                >+{{ detail.surcharge }}%</span
                                            >
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                                >-</span
                                            >
                                        </td>
                                        <td class="py-2 text-right font-medium">
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
                                </tbody>
                                <tfoot>
                                    <tr class="border-t font-semibold">
                                        <td class="pt-2">
                                            {{ t('reports.costs.total_earned') }}
                                        </td>
                                        <td class="pt-2 text-right">
                                            {{
                                                formatDecimalHours(
                                                    report.totals.net_hours,
                                                )
                                            }}
                                        </td>
                                        <td class="hidden pt-2 sm:table-cell" />
                                        <td class="pt-2 text-right">
                                            {{
                                                formatCurrency(
                                                    report.cost_summary.total,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr class="text-muted-foreground">
                                        <td class="pt-2" colspan="3">
                                            {{
                                                t('reports.costs.health', {
                                                    rate: report.cost_summary
                                                        .health_rate,
                                                })
                                            }}
                                        </td>
                                        <td class="pt-2 text-right">
                                            -{{
                                                formatCurrency(
                                                    report.cost_summary
                                                        .health_deduction,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr class="text-muted-foreground">
                                        <td colspan="3">
                                            {{
                                                t('reports.costs.pension', {
                                                    rate: report.cost_summary
                                                        .pension_rate,
                                                })
                                            }}
                                        </td>
                                        <td class="text-right">
                                            -{{
                                                formatCurrency(
                                                    report.cost_summary
                                                        .pension_deduction,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr class="border-t font-semibold">
                                        <td class="pt-2" colspan="3">
                                            {{ t('reports.costs.net_pay') }}
                                        </td>
                                        <td class="pt-2 text-right">
                                            {{
                                                formatCurrency(
                                                    report.cost_summary.net_pay,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="adjustment in report.adjustments"
                                        :key="adjustment.id"
                                        class="text-muted-foreground"
                                    >
                                        <td colspan="3">
                                            {{
                                                adjustment.type === 'Bonus'
                                                    ? t('reports.costs.bonus')
                                                    : t(
                                                          'reports.costs.deduction',
                                                      )
                                            }}<template v-if="adjustment.concept"
                                                >: {{ adjustment.concept }}</template
                                            >
                                        </td>
                                        <td
                                            class="text-right"
                                            :class="
                                                adjustment.type === 'Bonus'
                                                    ? 'text-green-600'
                                                    : 'text-red-600'
                                            "
                                        >
                                            {{
                                                adjustment.type === 'Bonus'
                                                    ? '+'
                                                    : '-'
                                            }}{{ formatCurrency(adjustment.amount) }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="report.adjustments.length > 0"
                                        class="border-t text-base font-bold"
                                    >
                                        <td class="pt-2" colspan="3">
                                            {{ t('reports.costs.final_pay') }}
                                        </td>
                                        <td class="pt-2 text-right">
                                            {{
                                                formatCurrency(
                                                    report.cost_summary
                                                        .final_pay,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ajustes del período: bonos y deducciones -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{
                            t('reports.adjustments.title')
                        }}</CardTitle>
                        <CardDescription>{{
                            t('reports.adjustments.description')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ReportAdjustments
                            :employee-id="filters.employee_id"
                            :period-end="filters.end_date"
                            :adjustments="report.adjustments"
                        />
                    </CardContent>
                </Card>

                <!-- Detalle diario del período -->
                <DailyWorkTable
                    :days="report.daily_breakdown"
                    :period="report.period"
                    fill-missing-days
                />
            </template>
        </div>
    </AppLayout>
</template>
