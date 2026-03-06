<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import EmployeeForm from './partials/EmployeeForm.vue';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import type { BreadcrumbItem, Department, Employee, Position, Schedule, Location } from '@/types';

type Props = {
    employee: Employee;
    departments: Department[];
    positions: Position[];
    schedules: Schedule[];
    locations: Location[];
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
    is_active: props.employee.user.is_active,
    department_id: props.employee.department_id ? String(props.employee.department_id) : '',
    position_id: props.employee.position_id ? String(props.employee.position_id) : '',
    employee_code: props.employee.employee_code ?? '',
    hire_date: props.employee.hire_date ?? '',
    hourly_rate: props.employee.hourly_rate ?? '',
    salary_type: props.employee.salary_type ?? 'hourly',
    schedule_id: props.employee.schedule_id ? String(props.employee.schedule_id) : '',
    location_id: props.employee.location_id ? String(props.employee.location_id) : '',
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
                        :departments="departments"
                        :positions="positions"
                        :schedules="schedules"
                        :locations="locations"
                        show-status
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
