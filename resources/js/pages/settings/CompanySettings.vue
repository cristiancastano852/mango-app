<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
