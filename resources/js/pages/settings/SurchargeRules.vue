<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SurchargeRuleController from '@/actions/App/Http/Controllers/Settings/SurchargeRuleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
    max_weekly_hours: number;
    night_start_time: string;
    night_end_time: string;
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
