<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SurchargeRuleController from '@/actions/App/Http/Controllers/Settings/SurchargeRuleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/surcharge-rules';
import type { BreadcrumbItem } from '@/types';

type SurchargeRule = {
    id: number;
    night_surcharge: string;
    overtime_day: string;
    overtime_night: string;
    sunday_holiday: string;
    overtime_day_sunday: string;
    overtime_night_sunday: string;
    night_sunday: string;
    pay_overtime_by_default: boolean;
    max_weekly_hours: number;
    max_daily_hours: number;
    night_start_time: string;
    night_end_time: string;
    default_monthly_salary: string;
    default_hourly_rate: string;
    transport_allowance: string;
};

defineProps<{ rule: SurchargeRule }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Recargos',
        href: edit(),
    },
];

const fields: { name: keyof SurchargeRule; label: string; isInt?: boolean }[] = [
    { name: 'night_surcharge', label: 'Recargo nocturno (%)' },
    { name: 'overtime_day', label: 'Hora extra diurna (%)' },
    { name: 'overtime_night', label: 'Hora extra nocturna (%)' },
    { name: 'sunday_holiday', label: 'Dominical / Festivo (%)' },
    { name: 'overtime_day_sunday', label: 'Hora extra diurna dominical (%)' },
    { name: 'overtime_night_sunday', label: 'Hora extra nocturna dominical (%)' },
    { name: 'night_sunday', label: 'Nocturna dominical (%)' },
    { name: 'max_weekly_hours', label: 'Máx. horas semanales', isInt: true },
    { name: 'max_daily_hours', label: 'Máx. horas diarias ordinarias', isInt: true },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Recargos salariales" />

        <h1 class="sr-only">Recargos salariales</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Recargos salariales"
                    description="Porcentajes de recargo según legislación colombiana. Modifica los valores según las condiciones de tu empresa."
                />

                <p class="text-sm text-muted-foreground">
                    Valores por defecto según legislación colombiana 2026.
                </p>

                <Form
                    v-bind="SurchargeRuleController.update.form()"
                    :options="{ preserveScroll: true }"
                    class="space-y-4"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="space-y-4 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                        <div class="space-y-0.5">
                            <Label class="text-base">Salario base por defecto</Label>
                            <p class="max-w-prose text-sm text-muted-foreground">
                                Valores con los que se precargan los empleados nuevos con salario mensual.
                                Por defecto, el salario mínimo legal vigente y su valor hora (salario ÷ 220).
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="default_monthly_salary">Salario base mensual ($)</Label>
                                <Input
                                    id="default_monthly_salary"
                                    name="default_monthly_salary"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :default-value="rule.default_monthly_salary"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="errors.default_monthly_salary" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="default_hourly_rate">Valor hora por defecto ($)</Label>
                                <Input
                                    id="default_hourly_rate"
                                    name="default_hourly_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :default-value="rule.default_hourly_rate"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="errors.default_hourly_rate" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="transport_allowance">Auxilio de transporte ($)</Label>
                                <Input
                                    id="transport_allowance"
                                    name="transport_allowance"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :default-value="rule.transport_allowance"
                                    class="mt-1 block w-full"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Se suma al pago de los empleados con salario mensual que lo reciben, prorrateado por periodo.
                                </p>
                                <InputError :message="errors.transport_allowance" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="field in fields"
                        :key="field.name"
                        class="grid gap-2"
                    >
                        <Label :for="field.name">{{ field.label }}</Label>
                        <Input
                            :id="field.name"
                            :name="field.name"
                            :type="field.isInt ? 'number' : 'number'"
                            :step="field.isInt ? '1' : '0.01'"
                            :min="field.isInt ? '1' : '0'"
                            :default-value="rule[field.name]"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="errors[field.name]" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="night_start_time">Inicio horario nocturno</Label>
                        <Input
                            id="night_start_time"
                            name="night_start_time"
                            type="time"
                            :default-value="rule.night_start_time"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="errors.night_start_time" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="night_end_time">Fin horario nocturno</Label>
                        <Input
                            id="night_end_time"
                            name="night_end_time"
                            type="time"
                            :default-value="rule.night_end_time"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="errors.night_end_time" />
                    </div>

                    <div class="flex items-start justify-between gap-4 rounded-lg border p-4">
                        <div class="space-y-1">
                            <Label for="pay_overtime_by_default" class="text-base">Pagar horas extra por defecto</Label>
                            <p class="max-w-prose text-sm text-muted-foreground">
                                Si lo desactivas, las horas extra se seguirán mostrando en los reportes pero se
                                pagarán en $0 (compensadas con tiempo libre o descanso) por defecto. Podrás
                                cambiar esta decisión en cada desprendible al momento de exportarlo.
                            </p>
                        </div>
                        <input type="hidden" name="pay_overtime_by_default" value="0" />
                        <Checkbox
                            id="pay_overtime_by_default"
                            name="pay_overtime_by_default"
                            value="1"
                            :default-checked="rule.pay_overtime_by_default"
                        />
                    </div>
                    <InputError :message="errors.pay_overtime_by_default" />

                    <div class="flex items-center gap-4 pt-2">
                        <Button :disabled="processing">Guardar</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Guardado.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
