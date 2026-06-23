<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { Eye, EyeOff } from 'lucide-vue-next';
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore computed when re-enabling.
// import { computed, ref } from 'vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore department/position Select usage when re-enabling.
// TODO: Schedules feature temporarily disabled — restore Schedule import when resuming
// LOCATIONS FEATURE DISABLED — restore Location import when re-enabling.
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore Department, Position imports when re-enabling.
// import type { Department, Position } from '@/types';

type Props = {
    form: {
        name: string;
        email: string;
        phone: string;
        document_number: string;
        password?: string;
        // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these fields when re-enabling.
        // department_id: string;
        // position_id: string;
        hire_date: string;
        hourly_rate: string;
        salary_type: string;
        monthly_base_salary?: string;
        receives_transport_allowance?: boolean;
        dominical_payment_mode?: string;
        dominical_day_value?: string;
        // TODO: Schedules feature temporarily disabled — restore schedule_id when resuming
        // schedule_id: string;
        // LOCATIONS FEATURE DISABLED — restore location_id when re-enabling.
        // location_id: string;
        is_active?: boolean;
        errors: Record<string, string>;
        processing: boolean;
    };
    // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these props when re-enabling.
    // departments: Department[];
    // positions: Position[];
    // TODO: Schedules feature temporarily disabled — restore schedules prop when resuming
    // schedules: Schedule[];
    // LOCATIONS FEATURE DISABLED — restore locations prop when re-enabling.
    // locations: Location[];
    showStatus?: boolean;
    showPassword?: boolean;
};

withDefaults(defineProps<Props>(), {
    showStatus: false,
    showPassword: false,
});

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useI18n();

const showPasswordText = ref(false);

// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore filteredPositions computed when re-enabling.
// const filteredPositions = computed(() =>
//     props.form.department_id
//         ? props.positions.filter((p) => p.department_id === Number(props.form.department_id))
//         : props.positions,
// );
</script>

<template>
    <form class="grid gap-6" @submit.prevent="emit('submit')">
        <!-- Personal Info -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="name">{{ t('employees.form.full_name') }}</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="space-y-2">
                <Label for="email">{{ t('employees.form.email') }}</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <InputError :message="form.errors.email" />
            </div>
            <div class="space-y-2">
                <Label for="document_number">{{ t('employees.form.document_number') }} *</Label>
                <Input id="document_number" v-model="form.document_number" placeholder="Ej: 1234567890" required />
                <InputError :message="form.errors.document_number" />
            </div>
            <div class="space-y-2">
                <Label for="phone">{{ t('employees.form.phone') }}</Label>
                <Input id="phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>
            <div v-if="showPassword" class="space-y-2 sm:col-span-2">
                <Label for="password">{{ t('employees.form.password') }}</Label>
                <div class="relative sm:max-w-[50%]">
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="showPasswordText ? 'text' : 'password'"
                        :placeholder="t('employees.form.password_placeholder')"
                        class="pr-10"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="absolute right-0 top-0 h-full px-3 text-muted-foreground hover:text-foreground"
                        :aria-label="showPasswordText ? t('employees.form.hide_password') : t('employees.form.show_password')"
                        @click="showPasswordText = !showPasswordText"
                    >
                        <EyeOff v-if="showPasswordText" class="size-4" />
                        <Eye v-else class="size-4" />
                    </Button>
                </div>
                <InputError :message="form.errors.password" />
            </div>
        </div>

        <!-- DEPARTMENTS & POSITIONS FEATURE DISABLED — restore this section when re-enabling. -->
        <!-- <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
                <Label>{{ t('employees.form.department') }}</Label>
                <Select v-model="form.department_id">
                    <SelectTrigger><SelectValue :placeholder="t('common.select')" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.department_id" />
            </div>
            <div class="space-y-2">
                <Label>{{ t('employees.form.position') }}</Label>
                <Select v-model="form.position_id">
                    <SelectTrigger><SelectValue :placeholder="t('common.select')" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="pos in filteredPositions" :key="pos.id" :value="String(pos.id)">{{ pos.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.position_id" />
            </div>
            LOCATIONS FEATURE DISABLED — restore location select when re-enabling.
            TODO: Schedules feature temporarily disabled — restore schedule selector here when resuming.
        </div> -->

        <!-- Contract -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
                <Label for="hire_date">{{ t('employees.form.hire_date') }}</Label>
                <Input id="hire_date" v-model="form.hire_date" type="date" />
                <InputError :message="form.errors.hire_date" />
            </div>
            <div class="space-y-2">
                <Label for="salary_type">{{ t('employees.form.salary_type') }}</Label>
                <Select v-model="form.salary_type">
                    <SelectTrigger id="salary_type">
                        <SelectValue :placeholder="t('common.select')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="monthly">{{ t('employees.form.salary_monthly') }}</SelectItem>
                        <SelectItem value="hourly">{{ t('employees.form.salary_hourly') }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.salary_type" />
            </div>
            <div v-if="form.salary_type === 'monthly'" class="space-y-2">
                <Label for="monthly_base_salary">{{ t('employees.form.monthly_base_salary') }}</Label>
                <Input id="monthly_base_salary" v-model="form.monthly_base_salary" type="number" step="0.01" min="0" />
                <InputError :message="form.errors.monthly_base_salary" />
            </div>
            <div v-if="form.salary_type === 'monthly'" class="flex items-start gap-3">
                <Checkbox
                    id="receives_transport_allowance"
                    v-model="form.receives_transport_allowance"
                    class="mt-0.5"
                />
                <div class="space-y-1">
                    <Label for="receives_transport_allowance">{{ t('employees.form.receives_transport_allowance') }}</Label>
                    <p class="text-muted-foreground text-xs">{{ t('employees.form.receives_transport_allowance_hint') }}</p>
                    <InputError :message="form.errors.receives_transport_allowance" />
                </div>
            </div>
            <div class="space-y-2">
                <Label for="hourly_rate">{{ t('employees.form.hourly_rate') }}</Label>
                <Input id="hourly_rate" v-model="form.hourly_rate" type="number" step="0.01" min="0" />
                <p class="text-muted-foreground text-xs">{{ t('employees.form.hourly_rate_hint') }}</p>
                <InputError :message="form.errors.hourly_rate" />
            </div>
            <div class="space-y-2">
                <Label for="dominical_payment_mode">{{ t('employees.form.dominical_payment_mode') }}</Label>
                <Select v-model="form.dominical_payment_mode">
                    <SelectTrigger id="dominical_payment_mode">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="hour">{{ t('employees.form.dominical_mode_hour') }}</SelectItem>
                        <SelectItem value="day">{{ t('employees.form.dominical_mode_day') }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.dominical_payment_mode" />
            </div>
            <div v-if="form.dominical_payment_mode === 'day'" class="space-y-2">
                <Label for="dominical_day_value">{{ t('employees.form.dominical_day_value') }}</Label>
                <Input id="dominical_day_value" v-model="form.dominical_day_value" type="number" step="0.01" min="0" />
                <InputError :message="form.errors.dominical_day_value" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" @click="$inertia.visit('/employees')">
                {{ t('employees.form.cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? t('employees.form.saving') : t('employees.form.save') }}
            </Button>
        </div>
    </form>
</template>
