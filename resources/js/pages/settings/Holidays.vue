<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import HolidayController from '@/actions/App/Http/Controllers/Settings/HolidayController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index } from '@/routes/holidays';
import type { BreadcrumbItem } from '@/types';

type Holiday = {
    id: number;
    name: string;
    date: string;
    is_recurring: boolean;
};

defineProps<{ holidays: Holiday[] }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Festivos',
        href: index(),
    },
];

const editingId = ref<number | null>(null);
const editingHoliday = ref<Partial<Holiday>>({});

function startEdit(holiday: Holiday) {
    editingId.value = holiday.id;
    editingHoliday.value = { ...holiday };
}

function cancelEdit() {
    editingId.value = null;
    editingHoliday.value = {};
}

function deleteHoliday(holiday: Holiday) {
    router.delete(HolidayController.destroy.url(holiday.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Festivos" />

        <h1 class="sr-only">Festivos</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Festivos"
                    description="Calendario de días festivos para el cálculo de recargos. Los festivos recurrentes se aplican cada año."
                />

                <div class="overflow-hidden rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Nombre</th>
                                <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                <th class="px-4 py-3 text-left font-medium">Recurrente</th>
                                <th class="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="holiday in holidays" :key="holiday.id">
                                <tr v-if="editingId !== holiday.id" class="hover:bg-muted/30">
                                    <td class="px-4 py-3">{{ holiday.name }}</td>
                                    <td class="px-4 py-3">{{ holiday.date }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                holiday.is_recurring
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            {{ holiday.is_recurring ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="startEdit(holiday)"
                                            >
                                                Editar
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                                @click="deleteHoliday(holiday)"
                                            >
                                                Eliminar
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else>
                                    <td colspan="4" class="px-4 py-3">
                                        <Form
                                            v-bind="HolidayController.update.form(holiday.id)"
                                            :options="{ preserveScroll: true }"
                                            class="flex flex-wrap items-end gap-3"
                                            v-slot="{ errors, processing }"
                                            @success="cancelEdit"
                                        >
                                            <div class="grid gap-1">
                                                <Label :for="`edit-name-${holiday.id}`">Nombre</Label>
                                                <Input
                                                    :id="`edit-name-${holiday.id}`"
                                                    name="name"
                                                    :default-value="holiday.name"
                                                    class="w-48"
                                                />
                                                <InputError :message="errors.name" />
                                            </div>
                                            <div class="grid gap-1">
                                                <Label :for="`edit-date-${holiday.id}`">Fecha</Label>
                                                <Input
                                                    :id="`edit-date-${holiday.id}`"
                                                    name="date"
                                                    type="date"
                                                    :default-value="holiday.date"
                                                    class="w-40"
                                                />
                                                <InputError :message="errors.date" />
                                            </div>
                                            <div class="grid gap-1">
                                                <Label :for="`edit-recurring-${holiday.id}`">Recurrente</Label>
                                                <select
                                                    :id="`edit-recurring-${holiday.id}`"
                                                    name="is_recurring"
                                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                                                >
                                                    <option value="1" :selected="holiday.is_recurring">Sí</option>
                                                    <option value="0" :selected="!holiday.is_recurring">No</option>
                                                </select>
                                                <InputError :message="errors.is_recurring" />
                                            </div>
                                            <div class="flex gap-2">
                                                <Button type="submit" size="sm" :disabled="processing">Guardar</Button>
                                                <Button type="button" variant="ghost" size="sm" @click="cancelEdit">Cancelar</Button>
                                            </div>
                                        </Form>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="holidays.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                    No hay festivos registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4">
                    <Heading variant="small" title="Agregar festivo" />

                    <Form
                        v-bind="HolidayController.store.form()"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        class="flex flex-wrap items-end gap-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-1">
                            <Label for="name">Nombre</Label>
                            <Input id="name" name="name" placeholder="Ej. Día del Trabajo" class="w-48" />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="date">Fecha</Label>
                            <Input id="date" name="date" type="date" class="w-40" />
                            <InputError :message="errors.date" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="is_recurring">Recurrente</Label>
                            <select
                                id="is_recurring"
                                name="is_recurring"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                            <InputError :message="errors.is_recurring" />
                        </div>
                        <Button type="submit" :disabled="processing">Agregar</Button>
                    </Form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
