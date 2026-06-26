<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    store,
    destroy,
} from '@/actions/App/Http/Controllers/EmployeeAdjustmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Adjustment = {
    id: number;
    date: string;
    type: 'Bonus' | 'Deduction';
    amount: number | string;
    concept: string;
    note: string | null;
};

const props = defineProps<{
    employeeId: number;
    // Fecha de fin del periodo del reporte: el ajuste se registra dentro del periodo visible.
    periodEnd: string;
    adjustments: Adjustment[];
}>();

const { t } = useI18n();
const showForm = ref(false);

const form = useForm({
    date: props.periodEnd,
    type: 'Bonus',
    amount: '',
    concept: '',
    note: '',
});

function submit(): void {
    // La fecha siempre cae dentro del periodo visible del reporte.
    form.transform((data) => ({ ...data, date: props.periodEnd }));
    form.post(store(props.employeeId).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount', 'concept', 'note');
            form.clearErrors();
            showForm.value = false;
        },
    });
}

function remove(adjustment: Adjustment): void {
    router.delete(
        destroy({ employee: props.employeeId, adjustment: adjustment.id }).url,
        { preserveScroll: true },
    );
}

function formatCurrency(value: number | string): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    }).format(Number(value));
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <ul
            v-if="adjustments.length > 0"
            class="flex flex-col divide-y rounded-md border"
        >
            <li
                v-for="adjustment in adjustments"
                :key="adjustment.id"
                class="flex items-center justify-between gap-3 px-3 py-2"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">
                        {{
                            adjustment.concept ||
                            (adjustment.type === 'Bonus'
                                ? t('reports.adjustments.bonus')
                                : t('reports.adjustments.deduction'))
                        }}
                    </p>
                    <p v-if="adjustment.concept" class="text-xs text-muted-foreground">
                        {{
                            adjustment.type === 'Bonus'
                                ? t('reports.adjustments.bonus')
                                : t('reports.adjustments.deduction')
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="text-sm font-semibold"
                        :class="
                            adjustment.type === 'Bonus'
                                ? 'text-green-600'
                                : 'text-red-600'
                        "
                    >
                        {{ adjustment.type === 'Bonus' ? '+' : '-'
                        }}{{ formatCurrency(adjustment.amount) }}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-red-600"
                        @click="remove(adjustment)"
                    >
                        {{ t('common.delete') }}
                    </Button>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{ t('reports.adjustments.empty') }}
        </p>

        <Button
            v-if="!showForm"
            type="button"
            variant="outline"
            size="sm"
            class="self-start"
            @click="showForm = true"
        >
            {{ t('reports.adjustments.add') }}
        </Button>

        <form
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-1.5">
                <Label for="adjustment_type">{{
                    t('reports.adjustments.type')
                }}</Label>
                <Select v-model="form.type">
                    <SelectTrigger id="adjustment_type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="Bonus">{{
                            t('reports.adjustments.bonus')
                        }}</SelectItem>
                        <SelectItem value="Deduction">{{
                            t('reports.adjustments.deduction')
                        }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.type" />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="adjustment_amount">{{
                    t('reports.adjustments.amount')
                }}</Label>
                <Input
                    id="adjustment_amount"
                    v-model="form.amount"
                    type="number"
                    min="0"
                    step="0.01"
                />
                <InputError :message="form.errors.amount" />
            </div>
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <Label for="adjustment_concept">{{
                    t('reports.adjustments.concept')
                }}</Label>
                <Input
                    id="adjustment_concept"
                    v-model="form.concept"
                    type="text"
                />
                <InputError :message="form.errors.concept" />
            </div>
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <Label for="adjustment_note">{{
                    t('reports.adjustments.note')
                }}</Label>
                <Input id="adjustment_note" v-model="form.note" type="text" />
                <InputError :message="form.errors.note" />
            </div>
            <div class="flex items-center gap-2 sm:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Button type="button" variant="ghost" @click="showForm = false">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </form>
    </div>
</template>
