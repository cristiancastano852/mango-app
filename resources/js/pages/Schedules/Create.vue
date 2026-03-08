<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import ScheduleForm from './partials/ScheduleForm.vue';
import { dashboard } from '@/routes';
import { index as schedulesIndex, store as storeSchedule } from '@/actions/App/Http/Controllers/SchedulesController';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('schedules.breadcrumb'), href: schedulesIndex() },
    { title: t('schedules.create.breadcrumb'), href: '#' },
];

const form = useForm({
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    break_duration: 60,
    days_of_week: [1, 2, 3, 4, 5] as number[],
});

function submit() {
    form.post(storeSchedule.url());
}
</script>

<template>
    <Head :title="t('schedules.create.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('schedules.create.title') }}</CardTitle>
                    <CardDescription>{{ t('schedules.create.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ScheduleForm :form="form" @submit="submit" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
