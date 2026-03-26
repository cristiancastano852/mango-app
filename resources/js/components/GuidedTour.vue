<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { dismiss } from '@/actions/App/Http/Controllers/TourController';

const props = defineProps<{
    show: boolean;
}>();

const currentStep = ref(0);
const visible = ref(props.show);

const steps = [
    {
        title: '¡Bienvenido a MangoApp! 🥭',
        description: 'Tu empresa está lista. Te mostramos rápidamente los elementos clave del panel.',
        emoji: '👋',
    },
    {
        title: 'KPIs en tiempo real',
        description: 'Estas 4 tarjetas muestran cuántos empleados están presentes, en pausa, ausentes y las horas netas del día. Se actualizan cada 60 segundos.',
        emoji: '📊',
    },
    {
        title: 'Estado de empleados',
        description: 'La lista de abajo muestra el estado de cada empleado en tiempo real: trabajando, en pausa, ausente o finalizado.',
        emoji: '👥',
    },
    {
        title: 'Check-in manual',
        description: 'Con el botón flotante (+) puedes registrar manualmente la entrada de un empleado sin que él lo haga desde su celular.',
        emoji: '✅',
    },
    {
        title: '¡Listo para empezar!',
        description: 'Explora el menú lateral para gestionar empleados, horarios, reportes y más. ¡Mucho éxito con tu equipo!',
        emoji: '🚀',
    },
];

function next() {
    if (currentStep.value < steps.length - 1) {
        currentStep.value++;
    } else {
        dismissTour();
    }
}

function dismissTour() {
    visible.value = false;
    router.post(dismiss.url());
}
</script>

<template>
    <Transition name="fade">
        <div
            v-if="visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        >
            <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">
                <div class="text-center">
                    <div class="mb-4 text-5xl">{{ steps[currentStep].emoji }}</div>
                    <h2 class="text-xl font-bold text-gray-900">{{ steps[currentStep].title }}</h2>
                    <p class="mt-3 text-sm text-gray-600">{{ steps[currentStep].description }}</p>
                </div>

                <!-- Progress dots -->
                <div class="mt-6 flex justify-center gap-2">
                    <div
                        v-for="(_, i) in steps"
                        :key="i"
                        :class="[
                            'h-2 w-2 rounded-full transition-colors',
                            i === currentStep ? 'bg-orange-500' : 'bg-gray-200',
                        ]"
                    />
                </div>

                <div class="mt-6 flex gap-3">
                    <Button variant="outline" class="flex-1" @click="dismissTour">
                        Saltar tour
                    </Button>
                    <Button class="flex-1" @click="next">
                        {{ currentStep < steps.length - 1 ? 'Siguiente →' : '¡Empezar!' }}
                    </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
