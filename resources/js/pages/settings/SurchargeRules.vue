<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    max_weekly_minutes: number;
    max_daily_minutes: number;
    overtime_accrual_mode: string;
    night_start_time: string;
    night_end_time: string;
    default_monthly_salary: string;
    default_hourly_rate: string;
    transport_allowance: string;
    dominical_weekday: number;
    pay_dominical_by_default: boolean;
    pay_night_dominical: boolean;
    pay_night_holiday: boolean;
    pay_overtime_dominical: boolean;
    pay_overtime_holiday: boolean;
    pay_overtime_night: boolean;
    default_dominical_payment_mode: string;
    default_normal_day_value: string;
    default_holiday_payment_mode: string;
};

const props = defineProps<{ rule: SurchargeRule }>();

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
];

// Los límites se guardan en minutos pero se capturan como Horas + Minutos.
const dailyHours = ref(Math.floor(props.rule.max_daily_minutes / 60));
const dailyMinutes = ref(props.rule.max_daily_minutes % 60);
const weeklyHours = ref(Math.floor(props.rule.max_weekly_minutes / 60));
const weeklyMinutes = ref(props.rule.max_weekly_minutes % 60);

const maxDailyMinutes = computed(
    () => (Number(dailyHours.value) || 0) * 60 + (Number(dailyMinutes.value) || 0),
);
const maxWeeklyMinutes = computed(
    () => (Number(weeklyHours.value) || 0) * 60 + (Number(weeklyMinutes.value) || 0),
);
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
                                <Label for="default_normal_day_value">Valor del día normal ($)</Label>
                                <Input
                                    id="default_normal_day_value"
                                    name="default_normal_day_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :default-value="rule.default_normal_day_value"
                                    class="mt-1 block w-full"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Base del recargo dominical "por día": recargo = este valor × el recargo dominical (%).
                                </p>
                                <InputError :message="errors.default_normal_day_value" />
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

                    <div class="space-y-4 rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label class="text-base">Límites de horas ordinarias</Label>
                            <p class="max-w-prose text-sm text-muted-foreground">
                                Jornada antes de clasificar el resto como horas extra. Admite minutos (ej. 7 h 20 min).
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label>Máx. diario ordinario</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="dailyHours"
                                    type="number"
                                    min="0"
                                    max="24"
                                    class="w-20"
                                    aria-label="Horas diarias"
                                />
                                <span class="text-sm text-muted-foreground">h</span>
                                <Input
                                    v-model="dailyMinutes"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="w-20"
                                    aria-label="Minutos diarios"
                                />
                                <span class="text-sm text-muted-foreground">min</span>
                            </div>
                            <input type="hidden" name="max_daily_minutes" :value="maxDailyMinutes" />
                            <InputError :message="errors.max_daily_minutes" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Máx. semanal ordinario</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="weeklyHours"
                                    type="number"
                                    min="0"
                                    max="168"
                                    class="w-20"
                                    aria-label="Horas semanales"
                                />
                                <span class="text-sm text-muted-foreground">h</span>
                                <Input
                                    v-model="weeklyMinutes"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="w-20"
                                    aria-label="Minutos semanales"
                                />
                                <span class="text-sm text-muted-foreground">min</span>
                            </div>
                            <input type="hidden" name="max_weekly_minutes" :value="maxWeeklyMinutes" />
                            <InputError :message="errors.max_weekly_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="overtime_accrual_mode">Acumulación de horas extra</Label>
                        <select
                            id="overtime_accrual_mode"
                            name="overtime_accrual_mode"
                            class="block w-full rounded-md border bg-background px-3 py-2 text-sm"
                            :value="rule.overtime_accrual_mode"
                        >
                            <option value="daily">Diaria (supera el límite diario o semanal)</option>
                            <option value="weekly">Semanal (solo al superar el límite semanal)</option>
                        </select>
                        <p class="max-w-prose text-sm text-muted-foreground">
                            En modo semanal el límite diario se ignora: solo se pagan extras cuando la semana
                            (lunes a domingo) supera el límite semanal. El recargo extra de cada semana se
                            liquida en la quincena que contiene su domingo; una semana en curso al cierre se
                            paga en el siguiente periodo.
                        </p>
                        <InputError :message="errors.overtime_accrual_mode" />
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
                            :default-value="rule.pay_overtime_by_default"
                        />
                    </div>
                    <InputError :message="errors.pay_overtime_by_default" />

                    <div class="space-y-4 rounded-lg border p-4">
                        <h3 class="text-base font-medium">Dominicales</h3>

                        <div class="grid gap-2">
                            <Label for="dominical_weekday">Día dominical</Label>
                            <select
                                id="dominical_weekday"
                                name="dominical_weekday"
                                class="block w-full rounded-md border bg-background px-3 py-2 text-sm"
                                :value="rule.dominical_weekday"
                            >
                                <option :value="0">Domingo</option>
                                <option :value="1">Lunes</option>
                                <option :value="2">Martes</option>
                                <option :value="3">Miércoles</option>
                                <option :value="4">Jueves</option>
                                <option :value="5">Viernes</option>
                                <option :value="6">Sábado</option>
                            </select>
                            <p class="text-sm text-muted-foreground">
                                Día de la semana que recibe el recargo dominical. Por defecto, domingo.
                            </p>
                            <InputError :message="errors.dominical_weekday" />
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="pay_dominical_by_default" class="text-base">Pagar dominicales por defecto</Label>
                                <p class="max-w-prose text-sm text-muted-foreground">
                                    Si lo desactivas, los dominicales se tratan como un día normal (sin recargo
                                    dominical). Los festivos siempre se pagan.
                                </p>
                            </div>
                            <input type="hidden" name="pay_dominical_by_default" value="0" />
                            <Checkbox
                                id="pay_dominical_by_default"
                                name="pay_dominical_by_default"
                                value="1"
                                :default-value="rule.pay_dominical_by_default"
                            />
                        </div>
                        <InputError :message="errors.pay_dominical_by_default" />

                        <div class="grid gap-2">
                            <Label for="default_dominical_payment_mode">Modo de pago dominical (por defecto)</Label>
                            <select
                                id="default_dominical_payment_mode"
                                name="default_dominical_payment_mode"
                                class="block w-full rounded-md border bg-background px-3 py-2 text-sm"
                                :value="rule.default_dominical_payment_mode"
                            >
                                <option value="hour">Por hora</option>
                                <option value="day">Por día (sobre el valor del día normal)</option>
                            </select>
                            <p class="text-sm text-muted-foreground">
                                En "por día", el recargo de cada dominical pagado = el <strong>valor del día normal</strong>
                                (arriba, en "Salario base por defecto") × el recargo dominical (%). La base del día se
                                paga aparte (salario/horas).
                            </p>
                            <InputError :message="errors.default_dominical_payment_mode" />
                        </div>
                    </div>

                    <div class="space-y-4 rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <h3 class="text-base font-medium">Festivos</h3>
                            <p class="max-w-prose text-sm text-muted-foreground">
                                Los festivos siempre se pagan (no son editables por reporte). Solo se configura
                                cómo: por hora o por día.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="default_holiday_payment_mode">Modo de pago festivo (por defecto)</Label>
                            <select
                                id="default_holiday_payment_mode"
                                name="default_holiday_payment_mode"
                                class="block w-full rounded-md border bg-background px-3 py-2 text-sm"
                                :value="rule.default_holiday_payment_mode"
                            >
                                <option value="hour">Por hora</option>
                                <option value="day">Por día (sobre el valor del día normal)</option>
                            </select>
                            <p class="text-sm text-muted-foreground">
                                En "por día", el recargo de cada festivo trabajado = el <strong>valor del día normal</strong>
                                × el recargo dominical (%). La base del día se paga aparte (salario/horas).
                            </p>
                            <InputError :message="errors.default_holiday_payment_mode" />
                        </div>
                    </div>

                    <div class="space-y-4 rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <h3 class="text-base font-medium">Recargos premium (nocturno/extra en dominical y festivo)</h3>
                            <p class="max-w-prose text-sm text-muted-foreground">
                                Algunas empresas no pagan estos recargos como premium. Si desactivas un switch, esas
                                horas se pagan con su recargo <strong>base</strong> (nocturno normal o extra normal), no
                                con el recargo dominical/festivo. Distinto de "Pagar horas extra", que cuando se apaga
                                <strong>no paga</strong> las extras (las compensa con tiempo a $0).
                            </p>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="pay_night_dominical" class="text-base">Pagar nocturno dominical</Label>
                                <p class="max-w-prose text-sm text-muted-foreground">
                                    Si lo desactivas, las horas nocturnas en día dominical se pagan como nocturno normal.
                                </p>
                            </div>
                            <input type="hidden" name="pay_night_dominical" value="0" />
                            <Checkbox
                                id="pay_night_dominical"
                                name="pay_night_dominical"
                                value="1"
                                :default-value="rule.pay_night_dominical"
                            />
                        </div>
                        <InputError :message="errors.pay_night_dominical" />

                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="pay_night_holiday" class="text-base">Pagar nocturno festivo</Label>
                                <p class="max-w-prose text-sm text-muted-foreground">
                                    Si lo desactivas, las horas nocturnas en festivo se pagan como nocturno normal.
                                </p>
                            </div>
                            <input type="hidden" name="pay_night_holiday" value="0" />
                            <Checkbox
                                id="pay_night_holiday"
                                name="pay_night_holiday"
                                value="1"
                                :default-value="rule.pay_night_holiday"
                            />
                        </div>
                        <InputError :message="errors.pay_night_holiday" />

                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="pay_overtime_dominical" class="text-base">Pagar extra dominical</Label>
                                <p class="max-w-prose text-sm text-muted-foreground">
                                    Si lo desactivas, las horas extra en día dominical se pagan como horas extra normales.
                                </p>
                            </div>
                            <input type="hidden" name="pay_overtime_dominical" value="0" />
                            <Checkbox
                                id="pay_overtime_dominical"
                                name="pay_overtime_dominical"
                                value="1"
                                :default-value="rule.pay_overtime_dominical"
                            />
                        </div>
                        <InputError :message="errors.pay_overtime_dominical" />

                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="pay_overtime_holiday" class="text-base">Pagar extra festiva</Label>
                                <p class="max-w-prose text-sm text-muted-foreground">
                                    Si lo desactivas, las horas extra en festivo se pagan como horas extra normales.
                                </p>
                            </div>
                            <input type="hidden" name="pay_overtime_holiday" value="0" />
                            <Checkbox
                                id="pay_overtime_holiday"
                                name="pay_overtime_holiday"
                                value="1"
                                :default-value="rule.pay_overtime_holiday"
                            />
                        </div>
                        <InputError :message="errors.pay_overtime_holiday" />

                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="pay_overtime_night" class="text-base">Pagar extra nocturna</Label>
                                <p class="max-w-prose text-sm text-muted-foreground">
                                    Si lo desactivas, las horas extra nocturnas se pagan como horas extra diurnas (sin el recargo nocturno).
                                </p>
                            </div>
                            <input type="hidden" name="pay_overtime_night" value="0" />
                            <Checkbox
                                id="pay_overtime_night"
                                name="pay_overtime_night"
                                value="1"
                                :default-value="rule.pay_overtime_night"
                            />
                        </div>
                        <InputError :message="errors.pay_overtime_night" />
                    </div>

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
