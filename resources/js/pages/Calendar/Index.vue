<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index as calendarIndex } from '@/routes/calendar';
import type { BreadcrumbItem } from '@/types';

type DayEntry = {
    id: number;
    employee_id: number;
    employee_name: string;
    clock_in: string | null;
    clock_out: string | null;
    net_hours: number;
    status: string;
};

type SimpleEmployee = {
    id: number;
    name: string;
};

type Props = {
    month: string;
    startDate: string;
    endDate: string;
    entriesByDate: Record<string, DayEntry[]>;
    employees: SimpleEmployee[];
    filters: { employee_id?: string };
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('calendar.breadcrumb'), href: calendarIndex() },
];

const selectedEmployee = ref(props.filters.employee_id ?? 'all');

const monthLabel = computed(() => {
    const [year, month] = props.month.split('-');
    return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
});

const calendarDays = computed(() => {
    const start = new Date(props.startDate + 'T00:00:00');
    const end = new Date(props.endDate + 'T00:00:00');
    const days: Array<{ date: string; dayNum: number; isCurrentMonth: boolean }> = [];

    // Pad start to Sunday
    const firstDow = start.getDay();
    for (let i = 0; i < firstDow; i++) {
        days.push({ date: '', dayNum: 0, isCurrentMonth: false });
    }

    const d = new Date(start);
    while (d <= end) {
        const iso = d.toISOString().slice(0, 10);
        days.push({ date: iso, dayNum: d.getDate(), isCurrentMonth: true });
        d.setDate(d.getDate() + 1);
    }

    return days;
});

function navigate(direction: -1 | 1) {
    const [year, month] = props.month.split('-').map(Number);
    const d = new Date(year, month - 1 + direction, 1);
    const newMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    router.get(calendarIndex.url(), {
        month: newMonth,
        employee_id: selectedEmployee.value === 'all' ? undefined : selectedEmployee.value,
    }, { preserveState: true });
}

function onEmployeeChange(value: string) {
    selectedEmployee.value = value;
    router.get(calendarIndex.url(), {
        month: props.month,
        employee_id: value === 'all' ? undefined : value,
    }, { preserveState: true });
}

const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
</script>

<template>
    <Head :title="t('calendar.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="icon" @click="navigate(-1)">
                        <ChevronLeft class="size-4" />
                    </Button>
                    <h1 class="text-lg font-semibold capitalize">{{ monthLabel }}</h1>
                    <Button variant="outline" size="icon" @click="navigate(1)">
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
                <Select :model-value="selectedEmployee" @update:model-value="onEmployeeChange">
                    <SelectTrigger class="w-full sm:w-[200px]">
                        <SelectValue :placeholder="t('calendar.filter_employee')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('calendar.all_employees') }}</SelectItem>
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

            <!-- Calendar grid -->
            <div class="rounded-lg border overflow-hidden">
                <!-- Day headers -->
                <div class="grid grid-cols-7 border-b bg-muted/40">
                    <div
                        v-for="day in dayHeaders"
                        :key="day"
                        class="px-2 py-2 text-center text-xs font-medium text-muted-foreground"
                    >
                        {{ day }}
                    </div>
                </div>

                <!-- Day cells -->
                <div class="grid grid-cols-7">
                    <div
                        v-for="(cell, idx) in calendarDays"
                        :key="idx"
                        class="min-h-[90px] border-b border-r p-1.5 last:border-r-0"
                        :class="{
                            'bg-muted/20': !cell.isCurrentMonth,
                            'bg-primary/5': cell.date === new Date().toISOString().slice(0, 10),
                        }"
                    >
                        <div v-if="cell.isCurrentMonth">
                            <span
                                class="text-xs font-medium"
                                :class="{
                                    'bg-primary text-primary-foreground rounded-full w-5 h-5 inline-flex items-center justify-center':
                                        cell.date === new Date().toISOString().slice(0, 10),
                                }"
                            >
                                {{ cell.dayNum }}
                            </span>
                            <div class="mt-1 flex flex-col gap-0.5">
                                <div
                                    v-for="entry in entriesByDate[cell.date] ?? []"
                                    :key="entry.id"
                                    class="truncate rounded px-1 py-0.5 text-xs"
                                    :class="
                                        entry.net_hours > 0
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    :title="entry.employee_name"
                                >
                                    <span v-if="selectedEmployee === 'all'" class="font-medium">
                                        {{ entry.employee_name.split(' ')[0] }}
                                    </span>
                                    {{ entry.net_hours > 0 ? t('calendar.hours', { h: entry.net_hours }) : '' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
