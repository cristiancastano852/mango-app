<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
// LOCATIONS FEATURE DISABLED — restore Location import when re-enabling.
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore Department, Position imports when re-enabling.
import type { BreadcrumbItem, Employee } from '@/types';
import EmployeeForm from './partials/EmployeeForm.vue';

type Props = {
    employee: Employee;
    // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these props when re-enabling.
    // departments: Department[];
    // positions: Position[];
    // LOCATIONS FEATURE DISABLED — restore locations prop when re-enabling.
    // locations: Location[];
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: { url: '/dashboard', method: 'get' } },
    { title: t('employees.breadcrumb'), href: employeesIndex() },
    { title: t('employees.edit_page.breadcrumb'), href: { url: `/employees/${props.employee.id}/edit`, method: 'get' } },
];

const form = useForm({
    name: props.employee.user.name,
    email: props.employee.user.email,
    phone: props.employee.user.phone ?? '',
    document_number: props.employee.document_number ?? '',
    is_active: props.employee.user.is_active,
    // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these fields when re-enabling.
    // department_id: props.employee.department_id ? String(props.employee.department_id) : '',
    // position_id: props.employee.position_id ? String(props.employee.position_id) : '',
    hire_date: props.employee.hire_date ?? '',
    hourly_rate: props.employee.hourly_rate ?? '',
    salary_type: props.employee.salary_type ?? 'hourly',
    monthly_base_salary: props.employee.monthly_base_salary ?? '',
    receives_transport_allowance: props.employee.receives_transport_allowance ?? true,
    dominical_payment_mode: props.employee.dominical_payment_mode ?? 'hour',
    normal_day_value: props.employee.normal_day_value ?? '',
    // LOCATIONS FEATURE DISABLED — restore location_id when re-enabling.
    // location_id: props.employee.location_id ? String(props.employee.location_id) : '',
});

function submit() {
    form.put(`/employees/${props.employee.id}`);
}
</script>

<template>
    <Head :title="t('employees.edit_page.head_title', { name: employee.user.name })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('employees.edit_page.title') }}</CardTitle>
                    <CardDescription>{{ t('employees.edit_page.description', { name: employee.user.name }) }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <EmployeeForm
                        :form="form"
                        show-status
                        @submit="submit"
                    />
                    <!-- DEPARTMENTS & POSITIONS FEATURE DISABLED — restore :departments and :positions props when re-enabling. -->
                    <!-- LOCATIONS FEATURE DISABLED — restore :locations="locations" prop when re-enabling. -->
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
