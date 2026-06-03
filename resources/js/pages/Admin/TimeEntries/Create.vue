<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    index as timeEntriesIndex,
    store as storeEntry,
} from '@/actions/App/Http/Controllers/Admin/TimeEntryController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type SimpleEmployee = { id: number; name: string };

type Props = {
    employees: SimpleEmployee[];
};

defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('time_entries.breadcrumb'), href: timeEntriesIndex() },
    { title: t('time_entries.create.breadcrumb'), href: '#' },
];

const form = useForm({
    employee_id: '',
    date: '',
    clock_in: '',
    clock_out: '',
});

function submit() {
    form.transform((data) => ({
        ...data,
        date: data.clock_in ? data.clock_in.slice(0, 10) : data.date,
    })).post(storeEntry().url);
}
</script>

<template>
    <Head :title="t('time_entries.create.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('time_entries.create.title') }}</CardTitle>
                    <CardDescription>{{ t('time_entries.create.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="flex flex-col gap-5" @submit.prevent="submit">
                        <!-- Employee -->
                        <div class="flex flex-col gap-1.5">
                            <Label>{{ t('time_entries.create.employee') }}</Label>
                            <Select v-model="form.employee_id">
                                <SelectTrigger>
                                    <SelectValue :placeholder="t('time_entries.create.select_employee')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        :value="String(emp.id)"
                                    >
                                        {{ emp.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.employee_id" class="text-destructive text-sm">
                                {{ form.errors.employee_id }}
                            </p>
                        </div>

                        <!-- Clock In -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="clock_in">{{ t('time_entries.create.clock_in') }}</Label>
                            <Input id="clock_in" v-model="form.clock_in" type="datetime-local" />
                            <p v-if="form.errors.clock_in" class="text-destructive text-sm">
                                {{ form.errors.clock_in }}
                            </p>
                        </div>

                        <!-- Clock Out -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="clock_out">{{ t('time_entries.create.clock_out') }}</Label>
                            <Input id="clock_out" v-model="form.clock_out" type="datetime-local" />
                            <p v-if="form.errors.clock_out" class="text-destructive text-sm">
                                {{ form.errors.clock_out }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3">
                            <Button as-child variant="outline">
                                <Link :href="timeEntriesIndex().url">{{ t('time_entries.create.cancel') }}</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('time_entries.create.saving') : t('time_entries.create.save') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
