<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BreakTypeController from '@/actions/App/Http/Controllers/Settings/BreakTypeController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index } from '@/routes/break-types';
import type { BreadcrumbItem } from '@/types';

type BreakType = {
    id: number;
    name: string;
    slug: string;
    is_paid: boolean;
    max_duration_minutes: number | null;
    max_per_day: number | null;
    is_default: boolean;
    is_active: boolean;
    icon: string | null;
    color: string | null;
};

const { t } = useI18n();

defineProps<{ breakTypes: BreakType[] }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.break_types'),
        href: index(),
    },
];

const editingId = ref<number | null>(null);

function startEdit(breakType: BreakType) {
    editingId.value = breakType.id;
}

function cancelEdit() {
    editingId.value = null;
}

function toggleActive(breakType: BreakType) {
    router.patch(BreakTypeController.toggleActive.url(breakType.id), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.break_types')" />

        <h1 class="sr-only">{{ t('settings.break_types') }}</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="t('settings.break_types')"
                    :description="t('break_type.description')"
                />

                <div class="overflow-hidden rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">{{ t('break_type.name') }}</th>
                                <th class="px-4 py-3 text-left font-medium">{{ t('break_type.is_paid') }}</th>
                                <th class="px-4 py-3 text-left font-medium">{{ t('break_type.max_duration') }}</th>
                                <th class="px-4 py-3 text-left font-medium">{{ t('break_type.max_per_day') }}</th>
                                <th class="px-4 py-3 text-left font-medium">{{ t('break_type.status') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ t('break_type.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="breakType in breakTypes" :key="breakType.id">
                                <tr v-if="editingId !== breakType.id" class="hover:bg-muted/30">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            {{ breakType.name }}
                                            <Badge v-if="breakType.is_default" variant="outline" class="text-xs">
                                                {{ t('break_type.default') }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :variant="breakType.is_paid ? 'default' : 'secondary'"
                                        >
                                            {{ breakType.is_paid ? t('break_type.paid') : t('break_type.unpaid') }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ breakType.max_duration_minutes ? `${breakType.max_duration_minutes} min` : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ breakType.max_per_day ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :variant="breakType.is_active ? 'default' : 'destructive'"
                                            class="cursor-pointer"
                                            @click="toggleActive(breakType)"
                                        >
                                            {{ breakType.is_active ? t('break_type.active') : t('break_type.inactive') }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="startEdit(breakType)"
                                            >
                                                {{ t('break_type.edit') }}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else>
                                    <td colspan="6" class="px-4 py-3">
                                        <Form
                                            v-bind="BreakTypeController.update.form(breakType.id)"
                                            :options="{ preserveScroll: true }"
                                            class="space-y-4"
                                            v-slot="{ errors, processing }"
                                            @success="cancelEdit"
                                        >
                                            <div class="flex flex-wrap items-end gap-3">
                                                <div class="grid gap-1">
                                                    <Label :for="`edit-name-${breakType.id}`">{{ t('break_type.name') }}</Label>
                                                    <Input
                                                        :id="`edit-name-${breakType.id}`"
                                                        name="name"
                                                        :default-value="breakType.name"
                                                        class="w-40"
                                                    />
                                                    <InputError :message="errors.name" />
                                                </div>
                                                <div class="grid gap-1">
                                                    <Label :for="`edit-duration-${breakType.id}`">{{ t('break_type.max_duration') }}</Label>
                                                    <Input
                                                        :id="`edit-duration-${breakType.id}`"
                                                        name="max_duration_minutes"
                                                        type="number"
                                                        :default-value="breakType.max_duration_minutes?.toString()"
                                                        class="w-24"
                                                        placeholder="min"
                                                    />
                                                    <InputError :message="errors.max_duration_minutes" />
                                                </div>
                                                <div class="grid gap-1">
                                                    <Label :for="`edit-max-per-day-${breakType.id}`">{{ t('break_type.max_per_day') }}</Label>
                                                    <Input
                                                        :id="`edit-max-per-day-${breakType.id}`"
                                                        name="max_per_day"
                                                        type="number"
                                                        :default-value="breakType.max_per_day?.toString()"
                                                        class="w-20"
                                                    />
                                                    <InputError :message="errors.max_per_day" />
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-4">
                                                <div class="flex items-center gap-2">
                                                    <input type="hidden" name="is_paid" value="0" />
                                                    <Checkbox
                                                        :id="`edit-paid-${breakType.id}`"
                                                        name="is_paid"
                                                        value="1"
                                                        :default-checked="breakType.is_paid"
                                                    />
                                                    <Label :for="`edit-paid-${breakType.id}`">{{ t('break_type.paid') }}</Label>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <Checkbox
                                                        :id="`edit-default-${breakType.id}`"
                                                        name="is_default"
                                                        value="1"
                                                        :default-checked="breakType.is_default"
                                                    />
                                                    <Label :for="`edit-default-${breakType.id}`">{{ t('break_type.default') }}</Label>
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <Button type="submit" size="sm" :disabled="processing">{{ t('break_type.save') }}</Button>
                                                <Button type="button" variant="ghost" size="sm" @click="cancelEdit">{{ t('break_type.cancel') }}</Button>
                                            </div>
                                        </Form>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="breakTypes.length === 0">
                                <td colspan="6" class="text-muted-foreground px-4 py-6 text-center">
                                    {{ t('break_type.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4">
                    <Heading variant="small" :title="t('break_type.create')" />

                    <Form
                        v-bind="BreakTypeController.store.form()"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="grid gap-1">
                                <Label for="name">{{ t('break_type.name') }}</Label>
                                <Input id="name" name="name" :placeholder="t('break_type.name_placeholder')" class="w-40" />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid gap-1">
                                <Label for="max_duration_minutes">{{ t('break_type.max_duration') }}</Label>
                                <Input id="max_duration_minutes" name="max_duration_minutes" type="number" class="w-24" placeholder="min" />
                                <InputError :message="errors.max_duration_minutes" />
                            </div>
                            <div class="grid gap-1">
                                <Label for="max_per_day">{{ t('break_type.max_per_day') }}</Label>
                                <Input id="max_per_day" name="max_per_day" type="number" class="w-20" />
                                <InputError :message="errors.max_per_day" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="is_paid" value="0" />
                                <Checkbox id="is_paid" name="is_paid" value="1" />
                                <Label for="is_paid">{{ t('break_type.paid') }}</Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <Checkbox id="is_default" name="is_default" value="1" />
                                <Label for="is_default">{{ t('break_type.default') }}</Label>
                            </div>
                        </div>
                        <Button type="submit" :disabled="processing">{{ t('break_type.add') }}</Button>
                    </Form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
