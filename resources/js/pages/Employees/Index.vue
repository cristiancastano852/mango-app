<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, MoreHorizontal, Pencil, Trash2, Eye } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem, Department, Employee, PaginatedData } from '@/types';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';

type Props = {
    employees: PaginatedData<Employee>;
    departments: Department[];
    filters: {
        search?: string;
        department?: string;
        status?: string;
    };
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: { url: '/dashboard', method: 'get' } },
    { title: t('employees.breadcrumb'), href: employeesIndex() },
];

const search = ref(props.filters.search ?? '');
const department = ref(props.filters.department ?? '');
const status = ref(props.filters.status ?? '');

let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters({ search: value }), 300);
});

function applyFilters(override: Record<string, string> = {}) {
    router.get(employeesIndex.url(), {
        search: search.value || undefined,
        department: department.value || undefined,
        status: status.value || undefined,
        ...override,
    }, {
        preserveState: true,
        replace: true,
    });
}

function onDepartmentChange(value: string) {
    department.value = value === 'all' ? '' : value;
    applyFilters({ department: department.value });
}

function onStatusChange(value: string) {
    status.value = value === 'all' ? '' : value;
    applyFilters({ status: status.value });
}

function deleteEmployee(employee: Employee) {
    if (confirm(t('employees.confirm_delete', { name: employee.user.name }))) {
        router.delete(`/employees/${employee.id}`);
    }
}
</script>

<template>
    <Head :title="t('employees.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('employees.title') }}</h1>
                    <p class="text-muted-foreground text-sm">
                        {{ t('employees.registered_count', { count: employees.total }) }}
                    </p>
                </div>
                <Button as-child>
                    <Link href="/employees/create">
                        <Plus class="mr-2 size-4" />
                        {{ t('employees.new_employee') }}
                    </Link>
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
                    <Input
                        v-model="search"
                        :placeholder="t('employees.search_placeholder')"
                        class="pl-9"
                    />
                </div>
                <Select :model-value="department || 'all'" @update:model-value="onDepartmentChange">
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue :placeholder="t('employees.department')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('common.all') }}</SelectItem>
                        <SelectItem
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="String(dept.id)"
                        >
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select :model-value="status || 'all'" @update:model-value="onStatusChange">
                    <SelectTrigger class="w-full sm:w-[140px]">
                        <SelectValue :placeholder="t('employees.status')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('common.all') }}</SelectItem>
                        <SelectItem value="active">{{ t('common.active') }}</SelectItem>
                        <SelectItem value="inactive">{{ t('common.inactive') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Employee List -->
            <div class="grid gap-3">
                <Card
                    v-for="employee in employees.data"
                    :key="employee.id"
                    class="transition-colors hover:bg-muted/50"
                >
                    <CardContent class="flex items-center gap-4 p-4">
                        <!-- Avatar -->
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold">
                            {{ employee.user.name.charAt(0).toUpperCase() }}
                        </div>

                        <!-- Info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-medium">{{ employee.user.name }}</p>
                                <Badge v-if="!employee.user.is_active" variant="secondary" class="text-xs">
                                    {{ t('common.inactive_badge') }}
                                </Badge>
                            </div>
                            <p class="text-muted-foreground truncate text-sm">{{ employee.user.email }}</p>
                        </div>

                        <!-- Department & Position (hidden on mobile) -->
                        <div class="hidden min-w-0 flex-1 md:block">
                            <p class="truncate text-sm font-medium">{{ employee.department?.name ?? '—' }}</p>
                            <p class="text-muted-foreground truncate text-sm">{{ employee.position?.name ?? '—' }}</p>
                        </div>

                        <!-- Schedule (hidden on mobile) -->
                        <div class="hidden min-w-0 lg:block">
                            <p class="truncate text-sm">{{ employee.schedule?.name ?? '—' }}</p>
                        </div>

                        <!-- Code -->
                        <div class="hidden sm:block">
                            <Badge variant="outline" class="font-mono text-xs">
                                {{ employee.employee_code ?? '—' }}
                            </Badge>
                        </div>

                        <!-- Actions -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="icon" class="shrink-0">
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem as-child>
                                    <Link :href="`/employees/${employee.id}`" class="flex items-center">
                                        <Eye class="mr-2 size-4" />
                                        {{ t('employees.view') }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link :href="`/employees/${employee.id}/edit`" class="flex items-center">
                                        <Pencil class="mr-2 size-4" />
                                        {{ t('employees.edit') }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    class="text-destructive"
                                    @click="deleteEmployee(employee)"
                                >
                                    <Trash2 class="mr-2 size-4" />
                                    {{ t('employees.delete') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </CardContent>
                </Card>

                <!-- Empty state -->
                <div
                    v-if="employees.data.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center"
                >
                    <p class="text-muted-foreground text-sm">{{ t('employees.not_found') }}</p>
                    <Button as-child variant="link" class="mt-2">
                        <Link href="/employees/create">{{ t('employees.create_first') }}</Link>
                    </Button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="employees.last_page > 1" class="flex justify-center gap-2">
                <Button
                    v-for="page in employees.last_page"
                    :key="page"
                    :variant="page === employees.current_page ? 'default' : 'outline'"
                    size="sm"
                    @click="router.get(employeesIndex.url(), { search: search || undefined, department: department || undefined, status: status || undefined, page }, { preserveState: true })"
                >
                    {{ page }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
