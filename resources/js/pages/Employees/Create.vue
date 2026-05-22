<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
// LOCATIONS FEATURE DISABLED — restore Location import when re-enabling.
import type { BreadcrumbItem, Department, Position } from '@/types';
import EmployeeForm from './partials/EmployeeForm.vue';

type Props = {
    departments: Department[];
    positions: Position[];
    // LOCATIONS FEATURE DISABLED — restore locations prop when re-enabling.
    // locations: Location[];
};

defineProps<Props>();
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
    document_number: '',
    password: '',
    department_id: '',
    position_id: '',
    hire_date: '',
    hourly_rate: '',
    salary_type: 'hourly',
    // LOCATIONS FEATURE DISABLED — restore location_id when re-enabling.
    // location_id: '',
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
                        show-password
                        @submit="submit"
                    />
                    <!-- LOCATIONS FEATURE DISABLED — restore :locations="locations" prop when re-enabling. -->
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
