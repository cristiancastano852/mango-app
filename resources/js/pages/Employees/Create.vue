<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { Fingerprint } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
// LOCATIONS FEATURE DISABLED — restore Location import when re-enabling.
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore Department, Position imports when re-enabling.
import type { BreadcrumbItem } from '@/types';
import EmployeeForm from './partials/EmployeeForm.vue';

// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore departments/positions/locations props when re-enabling.
type Props = {
    defaultMonthlySalary: string | null;
    defaultHourlyRate: string | null;
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
    document_number: '',
    password: '',
    // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these fields when re-enabling.
    // department_id: '',
    // position_id: '',
    hire_date: '',
    hourly_rate: props.defaultHourlyRate ?? '',
    salary_type: 'monthly',
    monthly_base_salary: props.defaultMonthlySalary ?? '',
    receives_transport_allowance: true,
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
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6 space-y-4">
            <!-- Document number banner -->
            <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/60 dark:bg-amber-950/30">
                <Fingerprint class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="space-y-0.5">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        {{ t('employees.create.document_number_banner_title') }}
                    </p>
                    <p class="text-sm text-amber-700 dark:text-amber-400/80">
                        {{ t('employees.create.document_number_banner_body') }}
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('employees.create.title') }}</CardTitle>
                    <CardDescription>{{ t('employees.create.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <EmployeeForm
                        :form="form"
                        show-password
                        @submit="submit"
                    />
                    <!-- DEPARTMENTS & POSITIONS FEATURE DISABLED — restore :departments and :positions props when re-enabling. -->
                    <!-- LOCATIONS FEATURE DISABLED — restore :locations="locations" prop when re-enabling. -->
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
