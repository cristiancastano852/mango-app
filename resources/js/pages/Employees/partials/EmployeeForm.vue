<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Eye, EyeOff } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Department, Position, Schedule, Location } from '@/types';

type Props = {
    form: {
        name: string;
        email: string;
        phone: string;
        password?: string;
        department_id: string;
        position_id: string;
        employee_code: string;
        hire_date: string;
        hourly_rate: string;
        salary_type: string;
        schedule_id: string;
        location_id: string;
        is_active?: boolean;
        errors: Record<string, string>;
        processing: boolean;
    };
    departments: Department[];
    positions: Position[];
    schedules: Schedule[];
    locations: Location[];
    showStatus?: boolean;
    showPassword?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showStatus: false,
    showPassword: false,
});

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useI18n();

const showPasswordText = ref(false);

const filteredPositions = computed(() =>
    props.form.department_id
        ? props.positions.filter((p) => p.department_id === Number(props.form.department_id))
        : props.positions,
);
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
                <Label for="phone">{{ t('employees.form.phone') }}</Label>
                <Input id="phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>
            <div class="space-y-2">
                <Label for="employee_code">{{ t('employees.form.employee_code') }}</Label>
                <Input id="employee_code" v-model="form.employee_code" placeholder="EMP-001" />
                <InputError :message="form.errors.employee_code" />
            </div>
            <div v-if="showPassword" class="space-y-2">
                <Label for="password">{{ t('employees.form.password') }}</Label>
                <div class="relative">
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

        <!-- Organization -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>{{ t('employees.form.department') }}</Label>
                <Select v-model="form.department_id">
                    <SelectTrigger>
                        <SelectValue :placeholder="t('common.select')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.department_id" />
            </div>
            <div class="space-y-2">
                <Label>{{ t('employees.form.position') }}</Label>
                <Select v-model="form.position_id">
                    <SelectTrigger>
                        <SelectValue :placeholder="t('common.select')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="pos in filteredPositions" :key="pos.id" :value="String(pos.id)">
                            {{ pos.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.position_id" />
            </div>
        </div>

        <!-- Schedule & Location -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>{{ t('employees.form.schedule') }}</Label>
                <Select v-model="form.schedule_id">
                    <SelectTrigger>
                        <SelectValue :placeholder="t('common.select')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sch in schedules" :key="sch.id" :value="String(sch.id)">
                            {{ sch.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.schedule_id" />
            </div>
            <div class="space-y-2">
                <Label>{{ t('employees.form.location') }}</Label>
                <Select v-model="form.location_id">
                    <SelectTrigger>
                        <SelectValue :placeholder="t('common.select')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="loc in locations" :key="loc.id" :value="String(loc.id)">
                            {{ loc.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.location_id" />
            </div>
        </div>

        <!-- Salary -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
                <Label for="hire_date">{{ t('employees.form.hire_date') }}</Label>
                <Input id="hire_date" v-model="form.hire_date" type="date" />
                <InputError :message="form.errors.hire_date" />
            </div>
            <div class="space-y-2">
                <Label>{{ t('employees.form.salary_type') }}</Label>
                <Select v-model="form.salary_type">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="hourly">{{ t('employees.form.salary_hourly') }}</SelectItem>
                        <SelectItem value="monthly">{{ t('employees.form.salary_monthly') }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.salary_type" />
            </div>
            <div class="space-y-2">
                <Label for="hourly_rate">{{ t('employees.form.hourly_rate') }}</Label>
                <Input id="hourly_rate" v-model="form.hourly_rate" type="number" step="0.01" min="0" />
                <InputError :message="form.errors.hourly_rate" />
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
