<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import OnboardingProgress from '@/components/OnboardingProgress.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/actions/App/Http/Controllers/Onboarding/OnboardingCompanyController';

defineProps<{
    company: {
        name: string;
        country: string;
        timezone: string;
        logo: string | null;
    };
}>();

const timezones = [
    { value: 'America/Bogota', label: 'Bogotá (COT)' },
    { value: 'America/Mexico_City', label: 'Ciudad de México (CST)' },
    { value: 'America/Lima', label: 'Lima (PET)' },
    { value: 'America/Santiago', label: 'Santiago (CLT)' },
    { value: 'America/Buenos_Aires', label: 'Buenos Aires (ART)' },
    { value: 'America/Caracas', label: 'Caracas (VET)' },
    { value: 'America/New_York', label: 'Nueva York (EST)' },
    { value: 'Europe/Madrid', label: 'Madrid (CET)' },
];

const countries = [
    { value: 'CO', label: 'Colombia' },
    { value: 'MX', label: 'México' },
    { value: 'PE', label: 'Perú' },
    { value: 'CL', label: 'Chile' },
    { value: 'AR', label: 'Argentina' },
    { value: 'VE', label: 'Venezuela' },
    { value: 'EC', label: 'Ecuador' },
    { value: 'US', label: 'Estados Unidos' },
];
</script>

<template>
    <Head title="Configurar empresa — MangoApp" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="text-3xl">🥭</span>
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Configura tu empresa</h1>
                <p class="mt-1 text-sm text-gray-500">Paso 1 de 3 — Información básica</p>
            </div>

            <OnboardingProgress :currentStep="1" />

            <div class="mt-8 rounded-2xl bg-white p-8 shadow-sm">
                <Form
                    v-bind="update.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-5"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nombre de la empresa</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            required
                            :value="company.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">Zona horaria</Label>
                        <Select name="timezone" :default-value="company.timezone">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona zona horaria" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                    {{ tz.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.timezone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="country">País</Label>
                        <Select name="country" :default-value="company.country">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona país" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in countries" :key="c.value" :value="c.value">
                                    {{ c.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.country" />
                    </div>

                    <Button type="submit" class="mt-2 w-full" :disabled="processing">
                        <Spinner v-if="processing" />
                        Continuar →
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
