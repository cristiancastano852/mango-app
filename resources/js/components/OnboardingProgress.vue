<script setup lang="ts">
defineProps<{
    currentStep: 1 | 2 | 3;
}>();

const steps = [
    { number: 1, label: 'Empresa' },
    { number: 2, label: 'Horario' },
    { number: 3, label: 'Pausas' },
];
</script>

<template>
    <div class="flex items-center justify-center gap-2">
        <template v-for="(step, index) in steps" :key="step.number">
            <div class="flex flex-col items-center">
                <div
                    :class="[
                        'flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold',
                        step.number < currentStep
                            ? 'bg-green-500 text-white'
                            : step.number === currentStep
                              ? 'bg-orange-500 text-white'
                              : 'bg-gray-200 text-gray-400',
                    ]"
                >
                    <span v-if="step.number < currentStep">✓</span>
                    <span v-else>{{ step.number }}</span>
                </div>
                <span
                    :class="[
                        'mt-1 text-xs',
                        step.number === currentStep ? 'font-semibold text-orange-500' : 'text-gray-400',
                    ]"
                >
                    {{ step.label }}
                </span>
            </div>
            <div
                v-if="index < steps.length - 1"
                :class="['mb-5 h-0.5 w-12', step.number < currentStep ? 'bg-green-500' : 'bg-gray-200']"
            />
        </template>
    </div>
</template>
