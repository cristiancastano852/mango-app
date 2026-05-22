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
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="t('settings.working_days')"
                    :description="t('settings.company_settings')"
                />

                <template v-if="hasCompany">
                    <Form
                        v-bind="CompanySettingsController.update.form()"
                        :options="{ preserveScroll: true }"
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-3">
                            <Label>{{ t('settings.working_days') }}</Label>
                            <div class="flex flex-wrap gap-4">
                                <div v-for="day in dayOptions" :key="day.value" class="flex items-center gap-2">
                                    <Checkbox
                                        :id="`day-${day.value}`"
                                        name="working_days[]"
                                        :value="day.value.toString()"
                                        :default-checked="workingDays.includes(day.value)"
                                    />
                                    <Label :for="`day-${day.value}`" class="text-sm">{{ day.label }}</Label>
                                </div>
                            </div>
                            <InputError :message="errors.working_days" />
                        </div>

                        <!-- TODO: Schedules feature temporarily disabled — restore default_schedule_id selector when resuming -->

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing">
                                {{ processing ? t('common.saving') : t('common.save') }}
                            </Button>
                            <span v-if="recentlySuccessful" class="text-sm text-green-600">{{ t('common.saved') }}</span>
                        </div>
                    </Form>
                </template>

                <p v-else class="text-muted-foreground">
                    {{ t('company.no_company') }}
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
