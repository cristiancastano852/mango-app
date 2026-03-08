<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { index as schedulesIndex, update as updateSchedule } from '@/actions/App/Http/Controllers/SchedulesController';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, Schedule } from '@/types';
import ScheduleForm from './partials/ScheduleForm.vue';

type ScheduleWithEmployees = Schedule & {
    employees: Array<{ id: number; user: { name: string } }>;
};

type Props = {
    schedule: ScheduleWithEmployees;
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('schedules.breadcrumb'), href: schedulesIndex() },
    { title: t('schedules.edit.breadcrumb'), href: '#' },
];

const form = useForm({
    name: props.schedule.name,
    start_time: props.schedule.start_time,
    end_time: props.schedule.end_time,
    break_duration: props.schedule.break_duration,
    days_of_week: [...props.schedule.days_of_week] as number[],
});

function submit() {
    form.put(updateSchedule(props.schedule.id).url);
}
</script>

<template>
    <Head :title="t('schedules.edit.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('schedules.edit.title') }}</CardTitle>
                    <CardDescription>{{ t('schedules.edit.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ScheduleForm :form="form" @submit="submit" />
                </CardContent>
            </Card>

            <!-- Employees assigned -->
            <Card v-if="schedule.employees.length > 0">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('schedules.employees_section') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="flex flex-wrap gap-2">
                        <li
                            v-for="emp in schedule.employees"
                            :key="emp.id"
                            class="bg-muted rounded-full px-3 py-1 text-sm"
                        >
                            {{ emp.user.name }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
