<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CompanySettingsController from '@/actions/App/Http/Controllers/Settings/CompanySettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/company-settings';
import type { BreadcrumbItem } from '@/types';

// TODO: Schedules feature temporarily disabled — restore Schedule type + defaultScheduleId + schedules props when resuming

const { t } = useI18n();

defineProps<{
    workingDays: number[];
    hasCompany: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.company_settings'),
        href: edit(),
    },
];

const dayOptions = [
    { value: 1, label: t('days.monday') },
    { value: 2, label: t('days.tuesday') },
    { value: 3, label: t('days.wednesday') },
    { value: 4, label: t('days.thursday') },
    { value: 5, label: t('days.friday') },
    { value: 6, label: t('days.saturday') },
    { value: 0, label: t('days.sunday') },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.company_settings')" />

        <h1 class="sr-only">{{ t('settings.company_settings') }}</h1>

        <SettingsLayout>
            <!-- WORKING DAYS FEATURE DISABLED — belongs to Schedules module, not yet in use.
                 Restore the form below when re-enabling (working_days checkboxes, Form submit, hasCompany guard). -->
        </SettingsLayout>
    </AppLayout>
</template>
