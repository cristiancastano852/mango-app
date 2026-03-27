<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    edit as editEntry,
    index as timeEntriesIndex,
} from '@/actions/App/Http/Controllers/Admin/TimeEntryController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDecimalHours } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, PaginatedData } from '@/types';

type EntryRow = {
    id: number;
    date: string;
    clock_in: string | null;
    clock_out: string | null;
    net_hours: string;
    status: string;
    edit_reason: string | null;
    employee: { id: number; user: { name: string } };
    edited_by: { name: string } | null;
};

type SimpleEmployee = { id: number; name: string };

type Props = {
    entries: PaginatedData<EntryRow>;
    employees: SimpleEmployee[];
    filters: { employee_id?: string; date?: string };
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('time_entries.breadcrumb'), href: timeEntriesIndex() },
];

const employeeFilter = ref(props.filters.employee_id ?? 'all');
const dateFilter = ref(props.filters.date ?? '');

let dateTimeout: ReturnType<typeof setTimeout>;
watch(dateFilter, (val) => {
    clearTimeout(dateTimeout);
    dateTimeout = setTimeout(() => applyFilters({ date: val }), 400);
});

function applyFilters(override: Record<string, string> = {}) {
    router.get(
        timeEntriesIndex.url(),
        {
            employee_id: employeeFilter.value === 'all' ? undefined : employeeFilter.value,
            date: dateFilter.value || undefined,
            ...override,
        },
        { preserveState: true, replace: true },
    );
}

function onEmployeeChange(value: string) {
    employeeFilter.value = value;
    applyFilters({ employee_id: value === 'all' ? '' : value });
}

function statusVariant(status: string) {
    const map: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        pending: 'secondary',
        calculated: 'default',
        edited: 'outline',
    };
    return map[status] ?? 'secondary';
}
</script>

<template>
    <Head :title="t('time_entries.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <h1 class="text-2xl font-bold tracking-tight">{{ t('time_entries.title') }}</h1>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <Select :model-value="employeeFilter" @update:model-value="onEmployeeChange">
                    <SelectTrigger class="w-full sm:w-[200px]">
                        <SelectValue :placeholder="t('time_entries.filter_employee')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('common.all') }}</SelectItem>
                        <SelectItem
                            v-for="emp in employees"
                            :key="emp.id"
                            :value="String(emp.id)"
                        >
                            {{ emp.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Input
                    v-model="dateFilter"
                    type="date"
                    :placeholder="t('time_entries.filter_date')"
                    class="w-full sm:w-[180px]"
                />
            </div>

            <!-- Table -->
            <Card>
                <CardContent class="p-0">
                    <div class="divide-y">
                        <div
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="flex items-center gap-4 px-4 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ entry.employee.user.name }}</p>
                                <p class="text-muted-foreground text-sm">{{ entry.date }}</p>
                            </div>

                            <div class="text-muted-foreground hidden text-sm sm:block">
                                {{ entry.clock_in ?? '—' }}
                                <span v-if="entry.clock_out"> → {{ entry.clock_out }}</span>
                            </div>

                            <div class="hidden text-sm font-medium sm:block">
                                {{ formatDecimalHours(entry.net_hours) }}
                            </div>

                            <Badge :variant="statusVariant(entry.status)" class="shrink-0 text-xs">
                                {{ entry.status }}
                            </Badge>

                            <Button variant="ghost" size="icon" as-child>
                                <Link :href="editEntry(entry.id).url">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                        </div>

                        <div
                            v-if="entries.data.length === 0"
                            class="text-muted-foreground p-8 text-center text-sm"
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
                    :variant="page === entries.current_page ? 'default' : 'outline'"
                    size="sm"
                    @click="router.get(timeEntriesIndex.url(), { employee_id: employeeFilter === 'all' ? undefined : employeeFilter, date: dateFilter || undefined, page }, { preserveState: true })"
                >
                    {{ page }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
