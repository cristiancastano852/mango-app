<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import DateRangeFilter from './partials/DateRangeFilter.vue';

defineProps<{
    employees: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('reports.breadcrumb'), href: '/reports' },
];

const dateFilter = ref({ date_range: 'month' as const });
const selectedEmployee = ref('');
const selectedDepartment = ref('');

function goToEmployeeReport() {
    if (!selectedEmployee.value) return;
    router.get('/reports/employee', {
        date_range: dateFilter.value.date_range,
        start_date: dateFilter.value.start_date,
        end_date: dateFilter.value.end_date,
        employee_id: selectedEmployee.value,
    });
}

function goToCompanyReport() {
    router.get('/reports/company', {
        date_range: dateFilter.value.date_range,
        start_date: dateFilter.value.start_date,
        end_date: dateFilter.value.end_date,
        department_id: selectedDepartment.value || undefined,
    });
}
</script>

<template>
    <Head :title="t('reports.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-bold">{{ t('reports.title') }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">{{ t('reports.description') }}</p>
            </div>

            <!-- Date Range -->
            <Card>
                <CardContent class="pt-6">
                    <DateRangeFilter v-model="dateFilter" />
                </CardContent>
            </Card>

            <!-- Report Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Employee Report Card -->
                <Card class="flex flex-col">
                    <CardHeader class="flex flex-row items-center gap-3 pb-2">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <FileText class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('reports.employee_report') }}</CardTitle>
                            <p class="text-muted-foreground text-xs">{{ t('reports.employee_report_desc') }}</p>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-3">
                        <div>
                            <Label class="text-muted-foreground mb-1 text-xs">{{ t('reports.select_employee') }}</Label>
                            <Select v-model="selectedEmployee">
                                <SelectTrigger>
                                    <SelectValue :placeholder="t('reports.select_employee')" />
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
                        <Button
                            class="mt-auto"
                            :disabled="!selectedEmployee"
                            @click="goToEmployeeReport"
                        >
                            {{ t('reports.generate') }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Company Report Card -->
                <Card class="flex flex-col">
                    <CardHeader class="flex flex-row items-center gap-3 pb-2">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                            <Users class="size-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('reports.company_report') }}</CardTitle>
                            <p class="text-muted-foreground text-xs">{{ t('reports.company_report_desc') }}</p>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-3">
                        <div>
                            <Label class="text-muted-foreground mb-1 text-xs">{{ t('employees.department') }}</Label>
                            <Select v-model="selectedDepartment">
                                <SelectTrigger>
                                    <SelectValue :placeholder="t('reports.all_departments')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">{{ t('reports.all_departments') }}</SelectItem>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button class="mt-auto" @click="goToCompanyReport">
                            {{ t('reports.generate') }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
