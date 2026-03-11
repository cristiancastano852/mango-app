<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DateRange = 'day' | 'week' | 'biweekly' | 'month' | 'custom';

const props = defineProps<{
    modelValue: {
        date_range: DateRange;
        start_date?: string;
        end_date?: string;
    };
}>();

const emit = defineEmits<{
    'update:modelValue': [value: typeof props.modelValue];
}>();

const { t } = useI18n();

const presets: DateRange[] = ['day', 'week', 'biweekly', 'month', 'custom'];

const selectedRange = ref<DateRange>(props.modelValue.date_range);
const customStart = ref(props.modelValue.start_date || '');
const customEnd = ref(props.modelValue.end_date || '');

const isCustom = computed(() => selectedRange.value === 'custom');

function selectPreset(preset: DateRange) {
    selectedRange.value = preset;
    if (preset !== 'custom') {
        emit('update:modelValue', { date_range: preset });
    }
}

watch([customStart, customEnd], () => {
    if (isCustom.value && customStart.value && customEnd.value) {
        emit('update:modelValue', {
            date_range: 'custom',
            start_date: customStart.value,
            end_date: customEnd.value,
        });
    }
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <Label class="text-sm font-medium">{{ t('reports.date_range') }}</Label>
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="preset in presets"
                :key="preset"
                size="sm"
                :variant="selectedRange === preset ? 'default' : 'outline'"
                @click="selectPreset(preset)"
            >
                {{ t(`reports.presets.${preset}`) }}
            </Button>
        </div>
        <div v-if="isCustom" class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
                <Label class="text-muted-foreground mb-1 text-xs">{{ t('reports.start_date') }}</Label>
                <Input v-model="customStart" type="date" />
            </div>
            <div class="flex-1">
                <Label class="text-muted-foreground mb-1 text-xs">{{ t('reports.end_date') }}</Label>
                <Input v-model="customEnd" type="date" />
            </div>
        </div>
    </div>
</template>
