<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import type { InertiaForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { index as schedulesIndex } from '@/actions/App/Http/Controllers/SchedulesController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ScheduleFormData = {
    name: string;
    start_time: string;
    end_time: string;
    break_duration: number | string;
    days_of_week: number[];
};

type Props = {
    form: InertiaForm<ScheduleFormData>;
};

defineProps<Props>();
const emit = defineEmits<{ submit: [] }>();
const { t } = useI18n();

const days = [
    { value: 1, label: t('schedules.days.1') },
    { value: 2, label: t('schedules.days.2') },
    { value: 3, label: t('schedules.days.3') },
    { value: 4, label: t('schedules.days.4') },
    { value: 5, label: t('schedules.days.5') },
    { value: 6, label: t('schedules.days.6') },
    { value: 0, label: t('schedules.days.0') },
];

function toggleDay(form: InertiaForm<ScheduleFormData>, day: number) {
    const idx = form.days_of_week.indexOf(day);
    if (idx >= 0) {
        form.days_of_week.splice(idx, 1);
    } else {
        form.days_of_week.push(day);
    }
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="emit('submit')">
        <!-- Name -->
        <div class="flex flex-col gap-1.5">
            <Label for="name">{{ t('schedules.form.name') }}</Label>
            <Input id="name" v-model="form.name" type="text" />
            <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
        </div>

        <!-- Start / End time -->
        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <Label for="start_time">{{ t('schedules.form.start_time') }}</Label>
                <Input id="start_time" v-model="form.start_time" type="time" />
                <p v-if="form.errors.start_time" class="text-destructive text-sm">{{ form.errors.start_time }}</p>
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="end_time">{{ t('schedules.form.end_time') }}</Label>
                <Input id="end_time" v-model="form.end_time" type="time" />
                <p v-if="form.errors.end_time" class="text-destructive text-sm">{{ form.errors.end_time }}</p>
            </div>
        </div>

        <!-- Break duration -->
        <div class="flex flex-col gap-1.5">
            <Label for="break_duration">{{ t('schedules.form.break_duration') }}</Label>
            <Input id="break_duration" v-model="form.break_duration" type="number" min="0" step="5" />
            <p v-if="form.errors.break_duration" class="text-destructive text-sm">{{ form.errors.break_duration }}</p>
        </div>

        <!-- Days of week -->
        <div class="flex flex-col gap-2">
            <Label>{{ t('schedules.form.days_of_week') }}</Label>
            <div class="flex flex-wrap gap-4">
                <label
                    v-for="day in days"
                    :key="day.value"
                    class="flex cursor-pointer items-center gap-2"
                >
                    <Checkbox
                        :checked="form.days_of_week.includes(day.value)"
                        @update:checked="toggleDay(form, day.value)"
                    />
                    <span class="text-sm">{{ day.label }}</span>
                </label>
            </div>
            <p v-if="form.errors.days_of_week" class="text-destructive text-sm">{{ form.errors.days_of_week }}</p>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
            <Button as="a" :href="schedulesIndex().url" variant="outline">
                {{ t('schedules.form.cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? t('schedules.form.saving') : t('schedules.form.save') }}
            </Button>
        </div>
    </form>
</template>
