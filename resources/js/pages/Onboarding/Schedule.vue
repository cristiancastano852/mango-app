<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import OnboardingProgress from '@/components/OnboardingProgress.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/actions/App/Http/Controllers/Onboarding/OnboardingScheduleController';

const days = [
    { value: 1, label: 'Lun' },
    { value: 2, label: 'Mar' },
    { value: 3, label: 'Mié' },
    { value: 4, label: 'Jue' },
    { value: 5, label: 'Vie' },
    { value: 6, label: 'Sáb' },
    { value: 0, label: 'Dom' },
];
</script>

<template>
    <Head title="Horario de trabajo — MangoApp" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="text-3xl">🥭</span>
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Horario de trabajo</h1>
                <p class="mt-1 text-sm text-gray-500">Paso 2 de 3 — Define el horario por defecto</p>
            </div>

            <OnboardingProgress :currentStep="2" />

            <div class="mt-8 rounded-2xl bg-white p-8 shadow-sm">
                <Form
                    v-bind="update.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-5"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nombre del horario</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            placeholder="Jornada Normal"
                            value="Jornada Normal"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="start_time">Hora de entrada</Label>
                            <Input
                                id="start_time"
                                name="start_time"
                                type="time"
                                value="08:00"
                            />
                            <InputError :message="errors.start_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="end_time">Hora de salida</Label>
                            <Input
                                id="end_time"
                                name="end_time"
                                type="time"
                                value="17:00"
                            />
                            <InputError :message="errors.end_time" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Días laborales</Label>
                        <div class="flex gap-2">
                            <Label
                                v-for="day in days"
                                :key="day.value"
                                class="flex flex-col items-center gap-1"
                            >
                                <Checkbox
                                    :name="`days_of_week[]`"
                                    :value="day.value"
                                    :default-checked="day.value >= 1 && day.value <= 5"
                                />
                                <span class="text-xs">{{ day.label }}</span>
                            </Label>
                        </div>
                        <InputError :message="errors['days_of_week']" />
                    </div>

                    <div class="flex gap-3">
                        <Button
                            type="submit"
                            name="skip"
                            value="1"
                            variant="outline"
                            class="flex-1"
                            :disabled="processing"
                        >
                            Omitir este paso
                        </Button>
                        <Button type="submit" class="flex-1" :disabled="processing">
                            <Spinner v-if="processing" />
                            Continuar →
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</template>
