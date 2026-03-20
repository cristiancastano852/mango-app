<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import OnboardingProgress from '@/components/OnboardingProgress.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/actions/App/Http/Controllers/Onboarding/OnboardingBreakTypesController';
import { ref } from 'vue';

const props = defineProps<{
    breakTypes: {
        id: number;
        name: string;
        slug: string;
        icon: string | null;
        color: string | null;
        is_active: boolean;
        is_paid: boolean;
    }[];
}>();

const activeIds = ref<number[]>(props.breakTypes.filter((bt) => bt.is_active).map((bt) => bt.id));

function toggle(id: number) {
    const index = activeIds.value.indexOf(id);
    if (index === -1) {
        activeIds.value.push(id);
    } else {
        activeIds.value.splice(index, 1);
    }
}
</script>

<template>
    <Head title="Tipos de pausa — MangoApp" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="text-3xl">🥭</span>
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Tipos de pausa</h1>
                <p class="mt-1 text-sm text-gray-500">Paso 3 de 3 — Activa los tipos de pausa para tu empresa</p>
            </div>

            <OnboardingProgress :currentStep="3" />

            <div class="mt-8 rounded-2xl bg-white p-8 shadow-sm">
                <Form
                    v-bind="update.form()"
                    v-slot="{ processing }"
                    class="flex flex-col gap-5"
                >
                    <!-- Hidden fields for active_ids -->
                    <input
                        v-for="id in activeIds"
                        :key="id"
                        type="hidden"
                        name="active_ids[]"
                        :value="id"
                    />

                    <div class="flex flex-col gap-3">
                        <button
                            v-for="bt in breakTypes"
                            :key="bt.id"
                            type="button"
                            @click="toggle(bt.id)"
                            :class="[
                                'flex items-center justify-between rounded-xl border-2 px-4 py-3 text-left transition-colors',
                                activeIds.includes(bt.id)
                                    ? 'border-orange-400 bg-orange-50'
                                    : 'border-gray-200 bg-white',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-xl">{{ bt.icon ?? '⏸️' }}</span>
                                <div>
                                    <p class="font-medium text-gray-900">{{ bt.name }}</p>
                                    <p class="text-xs text-gray-500">{{ bt.is_paid ? 'No descuenta tiempo' : 'Descuenta del tiempo trabajado' }}</p>
                                </div>
                            </div>
                            <div
                                :class="[
                                    'h-5 w-5 rounded-full border-2 flex items-center justify-center',
                                    activeIds.includes(bt.id)
                                        ? 'border-orange-500 bg-orange-500 text-white'
                                        : 'border-gray-300',
                                ]"
                            >
                                <span v-if="activeIds.includes(bt.id)" class="text-xs">✓</span>
                            </div>
                        </button>
                    </div>

                    <Button type="submit" class="mt-2 w-full" :disabled="processing">
                        <Spinner v-if="processing" />
                        ¡Empezar a usar MangoApp! 🥭
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
