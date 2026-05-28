<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { Fingerprint } from 'lucide-vue-next';
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
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6 space-y-4">
            <!-- Document number banner -->
            <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/60 dark:bg-amber-950/30">
                <Fingerprint class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="space-y-0.5">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        El número de documento es clave para el registro de tiempo
                    </p>
                    <p class="text-sm text-amber-700 dark:text-amber-400/80">
                        El empleado usará su cédula para marcar entradas y salidas en el kiosco. Verifica que el número sea correcto antes de guardar.
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
