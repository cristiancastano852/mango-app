<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Coffee, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    index as timeEntriesIndex,
    update as updateTimeEntry,
} from '@/actions/App/Http/Controllers/Admin/TimeEntryController';
import {
    destroy as destroyBreak,
    store as storeBreak,
    update as updateBreak,
} from '@/actions/App/Http/Controllers/Admin/TimeEntryBreakController';
import { Badge } from '@/components/ui/badge';
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

type BreakRow = {
    id: number;
    break_type_id: number;
    break_type_name: string | null;
    is_paid: boolean;
    started_at: string | null;
    ended_at: string | null;
    duration_minutes: number | null;
};

type TimeEntryFull = {
    id: number;
    date: string;
    clock_in: string | null;
    clock_out: string | null;
    net_hours: string;
    status: string;
    edit_reason: string | null;
    employee: { id: number; user: { name: string } };
    breaks: BreakRow[];
};

type BreakType = { id: number; name: string; is_paid: boolean };

type Props = {
    entry: TimeEntryFull;
    breakTypes: BreakType[];
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('time_entries.breadcrumb'), href: timeEntriesIndex() },
    { title: t('time_entries.edit.breadcrumb'), href: '#' },
];

const form = useForm({
    clock_in: props.entry.clock_in ?? '',
    clock_out: props.entry.clock_out ?? '',
    edit_reason: props.entry.edit_reason ?? '',
});

function submit() {
    form.put(updateTimeEntry(props.entry.id).url);
}

// ---- Breaks management ----
type EditableBreak = {
    id: number;
    break_type_id: string;
    started_at: string;
    ended_at: string;
};

const editableBreaks = reactive<EditableBreak[]>([]);

function syncBreaks() {
    editableBreaks.splice(0, editableBreaks.length);
    for (const b of props.entry.breaks) {
        editableBreaks.push({
            id: b.id,
            break_type_id: String(b.break_type_id),
            started_at: b.started_at ?? '',
            ended_at: b.ended_at ?? '',
        });
    }
}
syncBreaks();
watch(() => props.entry.breaks, syncBreaks, { deep: true });

const savingBreakId = ref<number | null>(null);
const breakErrors = ref<Record<number, string[]>>({});

function saveBreak(b: EditableBreak) {
    savingBreakId.value = b.id;
    router.put(
        updateBreak([props.entry.id, b.id]).url,
        {
            break_type_id: b.break_type_id,
            started_at: b.started_at,
            ended_at: b.ended_at,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                delete breakErrors.value[b.id];
            },
            onError: (errors) => {
                breakErrors.value[b.id] = Object.values(errors) as string[];
            },
            onFinish: () => (savingBreakId.value = null),
        },
    );
}

function removeBreak(b: EditableBreak) {
    router.delete(destroyBreak([props.entry.id, b.id]).url, {
        preserveScroll: true,
        onError: (errors) => {
            breakErrors.value[b.id] = Object.values(errors) as string[];
        },
    });
}

const showAddBreak = ref(false);
const addForm = useForm({
    break_type_id: '',
    started_at: '',
    ended_at: '',
});

function addBreak() {
    addForm.post(storeBreak(props.entry.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddBreak.value = false;
        },
    });
}

function isPaidType(typeId: string) {
    return props.breakTypes.find((bt) => String(bt.id) === typeId)?.is_paid ?? false;
}
</script>

<template>
    <Head :title="t('time_entries.edit.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
            <!-- Entry hours -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('time_entries.edit.title') }}</CardTitle>
                    <CardDescription>{{ t('time_entries.edit.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="flex flex-col gap-5" @submit.prevent="submit">
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

                        <div class="flex flex-col gap-1.5">
                            <Label for="clock_in">{{ t('time_entries.edit.clock_in') }}</Label>
                            <Input id="clock_in" v-model="form.clock_in" type="datetime-local" />
                            <p v-if="form.errors.clock_in" class="text-destructive text-sm">
                                {{ form.errors.clock_in }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label for="clock_out">{{ t('time_entries.edit.clock_out') }}</Label>
                            <Input id="clock_out" v-model="form.clock_out" type="datetime-local" />
                            <p v-if="form.errors.clock_out" class="text-destructive text-sm">
                                {{ form.errors.clock_out }}
                            </p>
                        </div>

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

                        <div class="flex justify-end gap-3">
                            <Button as-child variant="outline">
                                <Link :href="timeEntriesIndex().url">{{ t('time_entries.edit.cancel') }}</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('time_entries.edit.saving') : t('time_entries.edit.save') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Breaks -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Coffee class="text-muted-foreground size-4" />
                        <CardTitle>{{ t('time_entries.breaks.title') }}</CardTitle>
                    </div>
                    <CardDescription>{{ t('time_entries.breaks.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div
                        v-if="form.isDirty"
                        class="bg-amber-500/10 text-amber-700 dark:text-amber-400 flex items-start gap-2 rounded-lg border border-amber-500/30 p-3 text-sm"
                    >
                        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                        <span>{{ t('time_entries.breaks.unsaved_warning') }}</span>
                    </div>

                    <p
                        v-if="editableBreaks.length === 0 && !showAddBreak"
                        class="text-muted-foreground py-2 text-sm"
                    >
                        {{ t('time_entries.breaks.no_breaks') }}
                    </p>

                    <!-- Existing breaks -->
                    <div
                        v-for="b in editableBreaks"
                        :key="b.id"
                        class="flex flex-col gap-3 rounded-lg border p-3"
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex min-w-0 flex-col gap-1.5 sm:col-span-2">
                                <Label class="text-xs">{{ t('time_entries.breaks.type') }}</Label>
                                <Select v-model="b.break_type_id">
                                    <SelectTrigger class="w-full">
                                        <SelectValue :placeholder="t('time_entries.breaks.select_type')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="bt in breakTypes"
                                            :key="bt.id"
                                            :value="String(bt.id)"
                                        >
                                            {{ bt.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <Label class="text-xs">{{ t('time_entries.breaks.start') }}</Label>
                                <Input
                                    v-model="b.started_at"
                                    type="datetime-local"
                                    class="w-full"
                                    :min="entry.clock_in ?? undefined"
                                    :max="entry.clock_out ?? undefined"
                                />
                            </div>
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <Label class="text-xs">{{ t('time_entries.breaks.end') }}</Label>
                                <Input
                                    v-model="b.ended_at"
                                    type="datetime-local"
                                    class="w-full"
                                    :min="entry.clock_in ?? undefined"
                                    :max="entry.clock_out ?? undefined"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Badge variant="outline" class="mr-auto text-xs">
                                {{ isPaidType(b.break_type_id) ? t('time_entries.breaks.paid') : t('time_entries.breaks.unpaid') }}
                            </Badge>
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="savingBreakId === b.id || form.isDirty"
                                @click="saveBreak(b)"
                            >
                                {{ savingBreakId === b.id ? t('time_entries.breaks.saving') : t('time_entries.breaks.save') }}
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-muted-foreground hover:text-destructive"
                                @click="removeBreak(b)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <p
                            v-for="(err, i) in breakErrors[b.id]"
                            :key="i"
                            class="text-destructive text-sm"
                        >
                            {{ err }}
                        </p>
                    </div>

                    <!-- Add break -->
                    <div
                        v-if="showAddBreak"
                        class="flex flex-col gap-3 rounded-lg border border-dashed p-3"
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex min-w-0 flex-col gap-1.5 sm:col-span-2">
                                <Label class="text-xs">{{ t('time_entries.breaks.type') }}</Label>
                                <Select v-model="addForm.break_type_id">
                                    <SelectTrigger class="w-full">
                                        <SelectValue :placeholder="t('time_entries.breaks.select_type')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="bt in breakTypes"
                                            :key="bt.id"
                                            :value="String(bt.id)"
                                        >
                                            {{ bt.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <Label class="text-xs">{{ t('time_entries.breaks.start') }}</Label>
                                <Input
                                    v-model="addForm.started_at"
                                    type="datetime-local"
                                    class="w-full"
                                    :min="entry.clock_in ?? undefined"
                                    :max="entry.clock_out ?? undefined"
                                />
                            </div>
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <Label class="text-xs">{{ t('time_entries.breaks.end') }}</Label>
                                <Input
                                    v-model="addForm.ended_at"
                                    type="datetime-local"
                                    class="w-full"
                                    :min="entry.clock_in ?? undefined"
                                    :max="entry.clock_out ?? undefined"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Button size="sm" :disabled="addForm.processing || form.isDirty" @click="addBreak">
                                {{ addForm.processing ? t('time_entries.breaks.saving') : t('time_entries.breaks.save') }}
                            </Button>
                            <Button size="sm" variant="ghost" @click="showAddBreak = false">
                                {{ t('time_entries.breaks.cancel') }}
                            </Button>
                        </div>
                    </div>

                    <p v-if="addForm.errors.started_at" class="text-destructive text-sm">
                        {{ addForm.errors.started_at }}
                    </p>
                    <p v-if="addForm.errors.ended_at" class="text-destructive text-sm">
                        {{ addForm.errors.ended_at }}
                    </p>
                    <p v-if="addForm.errors.break_type_id" class="text-destructive text-sm">
                        {{ addForm.errors.break_type_id }}
                    </p>

                    <div v-if="!showAddBreak">
                        <Button variant="outline" size="sm" :disabled="form.isDirty" @click="showAddBreak = true">
                            <Plus class="mr-2 size-4" />
                            {{ t('time_entries.breaks.add') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
