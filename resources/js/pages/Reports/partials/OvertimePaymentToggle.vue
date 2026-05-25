<script setup lang="ts">
import { Clock, DollarSign } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

const { t } = useI18n();

function toggle() {
    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2"
        :class="modelValue ? 'border-border' : 'border-amber-300 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/20'"
    >
        <div class="flex items-center gap-2">
            <component
                :is="modelValue ? DollarSign : Clock"
                class="size-4 shrink-0"
                :class="modelValue ? 'text-emerald-600' : 'text-amber-600'"
            />
            <div class="leading-tight">
                <div class="text-sm font-medium">{{ t('reports.overtime_payment.label') }}</div>
                <div class="text-muted-foreground text-xs">
                    {{ modelValue ? t('reports.overtime_payment.paid_hint') : t('reports.overtime_payment.compensated_hint') }}
                </div>
            </div>
        </div>

        <button
            type="button"
            role="switch"
            :aria-checked="modelValue"
            :aria-label="t('reports.overtime_payment.label')"
            class="focus-visible:ring-ring relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
            :class="modelValue ? 'bg-emerald-600' : 'bg-input'"
            @click="toggle"
        >
            <span
                class="bg-background inline-block size-4 transform rounded-full shadow transition-transform"
                :class="modelValue ? 'translate-x-4' : 'translate-x-0.5'"
            />
        </button>
    </div>
</template>
