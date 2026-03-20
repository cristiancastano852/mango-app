<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/actions/App/Http/Controllers/CompanyRegistrationController';
import { login } from '@/routes';
</script>

<template>
    <AuthLayout
        title="Registra tu empresa"
        description="Crea tu cuenta y empieza a gestionar la asistencia de tu equipo."
    >
        <Head title="Registrar empresa — MangoApp" />

        <Form
            v-bind="store.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <!-- Honeypot anti-spam -->
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" />

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="company_name">Nombre de la empresa</Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        type="text"
                        required
                        autofocus
                        placeholder="Mi Empresa SAS"
                        autocomplete="organization"
                    />
                    <InputError :message="errors.company_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Tu nombre</Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        required
                        placeholder="Juan Pérez"
                        autocomplete="name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        placeholder="juan@miempresa.com"
                        autocomplete="email"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Crear empresa
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink :href="login()">Inicia sesión</TextLink>
            </div>
        </Form>
    </AuthLayout>
</template>
