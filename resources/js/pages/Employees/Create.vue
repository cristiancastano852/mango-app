<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import EmployeeForm from './partials/EmployeeForm.vue';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import type { BreadcrumbItem, Department, Position, Schedule, Location } from '@/types';

type Props = {
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
    { title: t('employees.create.breadcrumb'), href: { url: '/employees/create', method: 'get' } },
];

const form = useForm({
    name: '',
    email: '',
    phone: '',
    department_id: '',
    position_id: '',
    employee_code: '',
    hire_date: '',
    hourly_rate: '',
    salary_type: 'hourly',
    schedule_id: '',
    location_id: '',
});

function submit() {
    form.post('/employees');
}
</script>

<template>
    <Head :title="t('employees.create.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('employees.create.title') }}</CardTitle>
                    <CardDescription>{{ t('employees.create.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <EmployeeForm
                        :form="form"
                        :departments="departments"
                        :positions="positions"
                        :schedules="schedules"
                        :locations="locations"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
