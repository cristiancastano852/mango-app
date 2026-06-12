<script setup lang="ts">
import {
    CalendarOff,
    CalendarRange,
    ChevronDown,
    Clock,
    Coffee,
    LogIn,
    LogOut,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DailyWorkDayDetail from '@/components/DailyWorkDayDetail.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDecimalHours, formatTime12h } from '@/lib/utils';
import type { DailyWorkDay } from '@/types';

const props = defineProps<{
    days: DailyWorkDay[];
    period: { start: string; end: string };
    fillMissingDays?: boolean;
}>();

const { t, locale } = useI18n();

type Row = { date: string; day: DailyWorkDay | null };

// Fecha local sin sorpresas de timezone: 'YYYY-MM-DD' → Date local.
function toLocalDate(iso: string): Date {
    const [year, month, day] = iso.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function toIsoDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

const rows = computed<Row[]>(() => {
    if (!props.fillMissingDays) {
        return props.days.map((day) => ({ date: day.date, day }));
    }

    const byDate = new Map(props.days.map((day) => [day.date, day]));
    const today = toIsoDate(new Date());
    const end = props.period.end < today ? props.period.end : today;
    const result: Row[] = [];

    for (
        let cursor = toLocalDate(props.period.start);
        toIsoDate(cursor) <= end;
        cursor.setDate(cursor.getDate() + 1)
    ) {
        const date = toIsoDate(cursor);
        result.push({ date, day: byDate.get(date) ?? null });
    }

    return result;
});

const dayFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }),
);

function formatDayLabel(date: string): string {
    const label = dayFormatter.value.format(toLocalDate(date));
    return label.charAt(0).toUpperCase() + label.slice(1);
}

function isSundayOrHoliday(day: DailyWorkDay): boolean {
    return (
        (day.sunday_holiday_hours ?? 0) +
            (day.night_sunday_hours ?? 0) +
            (day.overtime_day_sunday_hours ?? 0) +
            (day.overtime_night_sunday_hours ?? 0) >
        0
    );
}

function hasOvertime(day: DailyWorkDay): boolean {
    return (
        (day.overtime_day_hours ?? 0) +
            (day.overtime_night_hours ?? 0) +
            (day.overtime_day_sunday_hours ?? 0) +
            (day.overtime_night_sunday_hours ?? 0) >
        0
    );
}

const expanded = ref(new Set<string>());

function toggle(date: string) {
    if (expanded.value.has(date)) {
        expanded.value.delete(date);
    } else {
        expanded.value.add(date);
    }
    expanded.value = new Set(expanded.value);
}

const workedDays = computed(() =>
    props.days.filter((day) => day.status !== 'in_progress'),
);

const totals = computed(() => ({
    net: workedDays.value.reduce((sum, day) => sum + (day.net_hours ?? 0), 0),
    breaks: workedDays.value.reduce(
        (sum, day) => sum + (day.break_hours ?? 0),
        0,
    ),
}));
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="flex items-center gap-2 text-sm font-medium">
                <CalendarRange class="size-4 text-primary" />
                {{ t('daily_work.title') }}
            </CardTitle>
            <Badge variant="secondary" class="font-normal">
                {{ t('daily_work.days_worked', { count: workedDays.length }) }}
            </Badge>
        </CardHeader>
        <CardContent class="p-0">
            <!-- Encabezados (desktop) -->
            <div
                class="hidden grid-cols-[10rem_1fr_7rem_7rem_2rem] gap-2 border-b px-4 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase sm:grid"
            >
                <span>{{ t('daily_work.col_day') }}</span>
                <span>{{ t('daily_work.col_schedule') }}</span>
                <span class="text-right">{{ t('daily_work.col_worked') }}</span>
                <span class="text-right">{{ t('daily_work.col_breaks') }}</span>
                <span />
            </div>

            <div class="divide-y">
                <p
                    v-if="rows.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('daily_work.empty') }}
                </p>

                <template v-for="row in rows" :key="row.date">
                    <!-- Día sin registro -->
                    <div
                        v-if="!row.day"
                        class="grid grid-cols-2 items-center gap-2 px-4 py-2.5 opacity-60 sm:grid-cols-[10rem_1fr_7rem_7rem_2rem]"
                    >
                        <span class="text-sm text-muted-foreground">{{
                            formatDayLabel(row.date)
                        }}</span>
                        <span
                            class="flex items-center gap-1.5 text-sm text-muted-foreground italic"
                        >
                            <CalendarOff class="size-3.5" />
                            {{ t('daily_work.not_worked') }}
                        </span>
                    </div>

                    <!-- Día con registro -->
                    <div v-else>
                        <button
                            type="button"
                            class="grid w-full grid-cols-2 items-center gap-2 px-4 py-2.5 text-left transition-colors hover:bg-muted/50 sm:grid-cols-[10rem_1fr_7rem_7rem_2rem]"
                            :aria-expanded="expanded.has(row.date)"
                            @click="toggle(row.date)"
                        >
                            <span
                                class="flex items-center gap-1.5 text-sm font-medium"
                            >
                                {{ formatDayLabel(row.date) }}
                                <Badge
                                    v-if="isSundayOrHoliday(row.day)"
                                    class="bg-red-100 px-1.5 text-[10px] text-red-700 dark:bg-red-950/50 dark:text-red-300"
                                >
                                    {{ t('daily_work.holiday_badge') }}
                                </Badge>
                            </span>

                            <span
                                class="flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums"
                            >
                                <LogIn class="size-3.5 text-emerald-500" />
                                {{ formatTime12h(row.day.clock_in) }}
                                <template
                                    v-if="row.day.status === 'in_progress'"
                                >
                                    <Badge
                                        class="ml-1 animate-pulse bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300"
                                    >
                                        {{ t('daily_work.in_progress') }}
                                    </Badge>
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground/50"
                                        >→</span
                                    >
                                    <LogOut class="size-3.5 text-rose-400" />
                                    {{ formatTime12h(row.day.clock_out) }}
                                </template>
                            </span>

                            <span
                                class="flex items-center justify-start gap-1.5 text-sm font-semibold text-emerald-600 tabular-nums sm:justify-end dark:text-emerald-400"
                            >
                                <Clock class="size-3.5" />
                                <template
                                    v-if="row.day.status === 'in_progress'"
                                    >—</template
                                >
                                <template v-else>{{
                                    formatDecimalHours(row.day.net_hours)
                                }}</template>
                                <Zap
                                    v-if="hasOvertime(row.day)"
                                    class="size-3.5 text-amber-500"
                                />
                            </span>

                            <span
                                class="flex items-center justify-start gap-1.5 text-sm font-medium text-amber-600 tabular-nums sm:justify-end dark:text-amber-400"
                            >
                                <Coffee class="size-3.5" />
                                <template
                                    v-if="row.day.status === 'in_progress'"
                                    >—</template
                                >
                                <template v-else>{{
                                    formatDecimalHours(row.day.break_hours)
                                }}</template>
                            </span>

                            <ChevronDown
                                class="hidden size-4 justify-self-end text-muted-foreground transition-transform sm:block"
                                :class="{
                                    'rotate-180': expanded.has(row.date),
                                }"
                            />
                        </button>

                        <DailyWorkDayDetail
                            v-if="expanded.has(row.date)"
                            :breaks="row.day.breaks"
                            :day="row.day"
                        />
                    </div>
                </template>
            </div>

            <!-- Totales -->
            <div
                v-if="workedDays.length > 0"
                class="grid grid-cols-2 items-center gap-2 border-t bg-muted/30 px-4 py-3 text-sm font-semibold sm:grid-cols-[10rem_1fr_7rem_7rem_2rem]"
            >
                <span>{{ t('daily_work.total') }}</span>
                <span />
                <span
                    class="text-right text-emerald-600 tabular-nums dark:text-emerald-400"
                >
                    {{ formatDecimalHours(totals.net) }}
                </span>
                <span
                    class="text-right text-amber-600 tabular-nums dark:text-amber-400"
                >
                    {{ formatDecimalHours(totals.breaks) }}
                </span>
                <span />
            </div>
        </CardContent>
    </Card>
</template>
