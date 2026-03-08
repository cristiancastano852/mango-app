<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as timeEntriesIndex, update as updateTimeEntry } from '@/actions/App/Http/Controllers/Admin/TimeEntryController';
import type { BreadcrumbItem } from '@/types';

type TimeEntryFull = {
    id: number;
    date: string;
    clock_in: string | null;
    clock_out: string | null;
    net_hours: string;
    regular_hours: string;
    overtime_hours: string;
    night_hours: string;
    sunday_holiday_hours: string;
    status: string;
    edit_reason: string | null;
    employee: {
        id: number;
        user: { name: string };
    };
};

type Props = {
    entry: TimeEntryFull;
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('time_entries.breadcrumb'), href: timeEntriesIndex() },
    { title: t('time_entries.edit.breadcrumb'), href: '#' },
];

function toLocalDatetime(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const form = useForm({
    clock_in: toLocalDatetime(props.entry.clock_in),
    clock_out: toLocalDatetime(props.entry.clock_out),
    edit_reason: props.entry.edit_reason ?? '',
});

function submit() {
    form.put(updateTimeEntry(props.entry.id).url);
}
</script>

<template>
    <Head :title="t('time_entries.edit.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('time_entries.edit.title') }}</CardTitle>
                    <CardDescription>{{ t('time_entries.edit.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="flex flex-col gap-5" @submit.prevent="submit">
                        <!-- Employee + Date info -->
                        <div class="bg-muted/40 rounded-lg p-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">{{ t('time_entries.edit.employee') }}</span>
                                <span class="font-medium">{{ entry.employee.user.name }}</span>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span class="text-muted-foreground">{{ t('time_entries.edit.original_date') }}</span>
                                <span class="font-medium">{{ entry.date }}</span>
                            </div>
                        </div>

                        <!-- Clock In -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="clock_in">{{ t('time_entries.edit.clock_in') }}</Label>
                            <Input
                                id="clock_in"
                                v-model="form.clock_in"
                                type="datetime-local"
                            />
                            <p v-if="form.errors.clock_in" class="text-destructive text-sm">
                                {{ form.errors.clock_in }}
                            </p>
                        </div>

                        <!-- Clock Out -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="clock_out">{{ t('time_entries.edit.clock_out') }}</Label>
                            <Input
                                id="clock_out"
                                v-model="form.clock_out"
                                type="datetime-local"
                            />
                            <p v-if="form.errors.clock_out" class="text-destructive text-sm">
                                {{ form.errors.clock_out }}
                            </p>
                        </div>

                        <!-- Edit Reason -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_reason">{{ t('time_entries.edit.edit_reason') }}</Label>
                            <textarea
                                id="edit_reason"
                                v-model="form.edit_reason"
                                rows="3"
                                :placeholder="t('time_entries.edit.reason_placeholder')"
                                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <p v-if="form.errors.edit_reason" class="text-destructive text-sm">
                                {{ form.errors.edit_reason }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3">
                            <Button as="a" :href="timeEntriesIndex().url" variant="outline">
                                {{ t('time_entries.edit.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('time_entries.edit.saving') : t('time_entries.edit.save') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
