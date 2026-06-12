<script setup lang="ts">
import { Calendar, Coffee, Moon, Sun, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { formatDecimalHours, formatMinutes, formatTime12h } from '@/lib/utils';
import type { DailyBreak, DailyWorkDay } from '@/types';

const props = defineProps<{
    breaks: DailyBreak[];
    day?: Pick<
        DailyWorkDay,
        | 'regular_hours'
        | 'night_hours'
        | 'sunday_holiday_hours'
        | 'night_sunday_hours'
        | 'overtime_day_hours'
        | 'overtime_night_hours'
        | 'overtime_day_sunday_hours'
        | 'overtime_night_sunday_hours'
    >;
}>();

const { t } = useI18n();

const hourTypes = [
    {
        key: 'regular_hours',
        label: 'reports.hours.regular',
        icon: Sun,
        classes:
            'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
    },
    {
        key: 'night_hours',
        label: 'reports.hours.night',
        icon: Moon,
        classes:
            'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300',
    },
    {
        key: 'sunday_holiday_hours',
        label: 'reports.hours.sunday_holiday',
        icon: Calendar,
        classes: 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
    },
    {
        key: 'night_sunday_hours',
        label: 'reports.hours.night_sunday',
        icon: Moon,
        classes:
            'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300',
    },
    {
        key: 'overtime_day_hours',
        label: 'reports.hours.overtime_day',
        icon: Zap,
        classes:
            'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    },
    {
        key: 'overtime_night_hours',
        label: 'reports.hours.overtime_night',
        icon: Zap,
        classes:
            'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300',
    },
    {
        key: 'overtime_day_sunday_hours',
        label: 'reports.hours.overtime_day_sunday',
        icon: Zap,
        classes:
            'bg-pink-50 text-pink-700 dark:bg-pink-950/40 dark:text-pink-300',
    },
    {
        key: 'overtime_night_sunday_hours',
        label: 'reports.hours.overtime_night_sunday',
        icon: Zap,
        classes:
            'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
    },
] as const;

const activeHourTypes = computed(() =>
    hourTypes.filter(
        (type) => ((props.day?.[type.key] as number | null) ?? 0) > 0,
    ),
);

function breakTint(color: string | null): Record<string, string> {
    return color
        ? { backgroundColor: `${color}1f`, borderColor: `${color}66` }
        : {};
}
</script>

<template>
    <div class="flex flex-col gap-4 bg-muted/40 px-4 py-4 sm:px-14">
        <!-- Pausas del día -->
        <div>
            <p
                class="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                <Coffee class="size-3.5 text-amber-500" />
                {{ t('daily_work.breaks_title') }}
            </p>
            <p v-if="breaks.length === 0" class="text-sm text-muted-foreground">
                {{ t('daily_work.no_breaks') }}
            </p>
            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="(brk, index) in breaks"
                    :key="index"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full border text-sm"
                        :style="breakTint(brk.color)"
                    >
                        {{ brk.icon ?? '☕' }}
                    </span>
                    <span class="font-medium">{{ brk.name ?? '—' }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ formatTime12h(brk.started_at) }}
                        <template v-if="!brk.in_progress">
                            → {{ formatTime12h(brk.ended_at) }}</template
                        >
                    </span>
                    <Badge
                        v-if="brk.in_progress"
                        class="bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300"
                    >
                        {{ t('daily_work.break_in_progress') }}
                    </Badge>
                    <span
                        v-else
                        class="font-medium text-amber-600 tabular-nums dark:text-amber-400"
                    >
                        {{ formatMinutes(brk.duration_minutes) }}
                    </span>
                    <Badge
                        variant="outline"
                        class="text-[10px] font-normal text-muted-foreground"
                    >
                        {{
                            brk.is_paid
                                ? t('daily_work.break_paid')
                                : t('daily_work.break_unpaid')
                        }}
                    </Badge>
                </li>
            </ul>
        </div>

        <!-- Tipos de hora del día -->
        <div v-if="activeHourTypes.length > 0">
            <p
                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ t('daily_work.hour_types_title') }}
            </p>
            <div class="flex flex-wrap gap-1.5">
                <span
                    v-for="type in activeHourTypes"
                    :key="type.key"
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="type.classes"
                >
                    <component :is="type.icon" class="size-3" />
                    {{ t(type.label) }}
                    <span class="tabular-nums">{{
                        formatDecimalHours(day?.[type.key])
                    }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
