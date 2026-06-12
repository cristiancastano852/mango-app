<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarPlus,
    ChevronDown,
    Clock,
    Coffee,
    LogIn,
    LogOut,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    create as createEntry,
    destroy as destroyEntry,
    edit as editEntry,
    index as timeEntriesIndex,
} from '@/actions/App/Http/Controllers/Admin/TimeEntryController';
import DailyWorkDayDetail from '@/components/DailyWorkDayDetail.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatDecimalHours, formatTime12h } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, DailyBreak, PaginatedData } from '@/types';

type EntryRow = {
    id: number;
    date: string;
    clock_in: string | null;
    clock_out: string | null;
    gross_hours: string;
    break_hours: string;
    net_hours: string;
    status: string;
    edit_reason: string | null;
    employee: { id: number; user: { name: string } };
    edited_by: { name: string } | null;
    breaks: DailyBreak[];
};

type SimpleEmployee = { id: number; name: string };

type Props = {
    entries: PaginatedData<EntryRow>;
    employees: SimpleEmployee[];
    filters: { employee_id?: string; date_from?: string; date_to?: string };
};

const props = defineProps<Props>();
const { t, locale } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('time_entries.breadcrumb'), href: timeEntriesIndex() },
];

const employeeFilter = ref(props.filters.employee_id ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let dateTimeout: ReturnType<typeof setTimeout>;
watch([dateFrom, dateTo], () => {
    clearTimeout(dateTimeout);
    dateTimeout = setTimeout(() => applyFilters(), 400);
});

function applyFilters() {
    router.get(
        timeEntriesIndex.url(),
        {
            employee_id:
                employeeFilter.value === 'all'
                    ? undefined
                    : employeeFilter.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function onEmployeeChange(value: string) {
    employeeFilter.value = value;
    applyFilters();
}

function statusVariant(status: string) {
    const map: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        pending: 'secondary',
        calculated: 'default',
        edited: 'outline',
    };
    return map[status] ?? 'secondary';
}

function statusLabel(status: string) {
    return t(`time_entries.status.${status}`, status);
}

const dayFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }),
);

function formatDayLabel(date: string): string {
    const [year, month, day] = date.slice(0, 10).split('-').map(Number);
    const label = dayFormatter.value.format(new Date(year, month - 1, day));
    return label.charAt(0).toUpperCase() + label.slice(1);
}

const expanded = ref(new Set<number>());

function toggleExpanded(id: number) {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
    expanded.value = new Set(expanded.value);
}

const deleteForm = useForm({});
const entryToDelete = ref<EntryRow | null>(null);

function confirmDelete(entry: EntryRow) {
    entryToDelete.value = entry;
}

function performDelete() {
    if (!entryToDelete.value) {
        return;
    }
    deleteForm.delete(destroyEntry(entryToDelete.value.id).url, {
        preserveScroll: true,
        onFinish: () => {
            entryToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('time_entries.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ t('time_entries.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('time_entries.subtitle') }}
                    </p>
                </div>
                <Button as-child>
                    <Link :href="createEntry().url">
                        <CalendarPlus class="mr-2 size-4" />
                        {{ t('time_entries.new_entry') }}
                    </Link>
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs">{{
                        t('time_entries.filter_employee')
                    }}</Label>
                    <Select
                        :model-value="employeeFilter"
                        @update:model-value="onEmployeeChange"
                    >
                        <SelectTrigger class="w-full sm:w-[200px]">
                            <SelectValue
                                :placeholder="t('time_entries.filter_employee')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{
                                t('common.all')
                            }}</SelectItem>
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
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs">{{
                        t('time_entries.filter_date_from')
                    }}</Label>
                    <Input
                        v-model="dateFrom"
                        type="date"
                        class="w-full sm:w-[170px]"
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs">{{
                        t('time_entries.filter_date_to')
                    }}</Label>
                    <Input
                        v-model="dateTo"
                        type="date"
                        class="w-full sm:w-[170px]"
                    />
                </div>
            </div>

            <!-- Table -->
            <Card>
                <CardContent class="p-0">
                    <div class="divide-y">
                        <div v-for="entry in entries.data" :key="entry.id">
                            <div class="flex items-center gap-3 px-4 py-3">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0"
                                    :aria-expanded="expanded.has(entry.id)"
                                    @click="toggleExpanded(entry.id)"
                                >
                                    <ChevronDown
                                        class="size-4 text-muted-foreground transition-transform"
                                        :class="{
                                            'rotate-180': expanded.has(
                                                entry.id,
                                            ),
                                        }"
                                    />
                                </Button>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">
                                        {{ entry.employee.user.name }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ formatDayLabel(entry.date) }}
                                    </p>
                                </div>

                                <div
                                    class="hidden items-center gap-1.5 text-sm text-muted-foreground tabular-nums sm:flex"
                                >
                                    <LogIn class="size-3.5 text-emerald-500" />
                                    {{ formatTime12h(entry.clock_in) }}
                                    <template v-if="entry.clock_out">
                                        <span class="text-muted-foreground/50"
                                            >→</span
                                        >
                                        <LogOut
                                            class="size-3.5 text-rose-400"
                                        />
                                        {{ formatTime12h(entry.clock_out) }}
                                    </template>
                                    <Badge
                                        v-else
                                        class="ml-1 animate-pulse bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300"
                                    >
                                        {{ t('daily_work.in_progress') }}
                                    </Badge>
                                </div>

                                <div
                                    class="hidden items-center gap-1.5 text-sm font-semibold text-emerald-600 tabular-nums sm:flex dark:text-emerald-400"
                                >
                                    <Clock class="size-3.5" />
                                    {{
                                        entry.clock_out
                                            ? formatDecimalHours(
                                                  entry.net_hours,
                                              )
                                            : '—'
                                    }}
                                </div>

                                <div
                                    class="hidden items-center gap-1.5 text-sm font-medium text-amber-600 tabular-nums md:flex dark:text-amber-400"
                                >
                                    <Coffee class="size-3.5" />
                                    {{ formatDecimalHours(entry.break_hours) }}
                                </div>

                                <Badge
                                    :variant="statusVariant(entry.status)"
                                    class="shrink-0 text-xs"
                                >
                                    {{ statusLabel(entry.status) }}
                                </Badge>

                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="editEntry(entry.id).url">
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="confirmDelete(entry)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <DailyWorkDayDetail
                                v-if="expanded.has(entry.id)"
                                :breaks="entry.breaks"
                            />
                        </div>

                        <div
                            v-if="entries.data.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            {{ t('time_entries.no_entries') }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="entries.last_page > 1" class="flex justify-center gap-2">
                <Button
                    v-for="page in entries.last_page"
                    :key="page"
                    :variant="
                        page === entries.current_page ? 'default' : 'outline'
                    "
                    size="sm"
                    @click="
                        router.get(
                            timeEntriesIndex.url(),
                            {
                                employee_id:
                                    employeeFilter === 'all'
                                        ? undefined
                                        : employeeFilter,
                                date_from: dateFrom || undefined,
                                date_to: dateTo || undefined,
                                page,
                            },
                            { preserveState: true },
                        )
                    "
                >
                    {{ page }}
                </Button>
            </div>
        </div>

        <!-- Delete confirmation -->
        <Dialog
            :open="entryToDelete !== null"
            @update:open="
                (v) => {
                    if (!v) entryToDelete = null;
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        t('time_entries.delete_confirm_title')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('time_entries.delete_confirm_message')
                    }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="entryToDelete = null">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="performDelete"
                    >
                        {{ t('time_entries.delete_action') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
