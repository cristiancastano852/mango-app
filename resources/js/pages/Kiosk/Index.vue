<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import KioskController from '@/actions/App/Http/Controllers/KioskController';
import InputError from '@/components/InputError.vue';
import KioskLayout from '@/layouts/KioskLayout.vue';

defineOptions({ layout: KioskLayout });

type BreakType = { id: number; name: string; icon: string | null; color: string | null };
type TodayEntry = {
    status: string;
    clock_in: string | null;
    clock_out: string | null;
    net_hours: string | null;
    gross_hours: string | null;
    breaks: Array<{ started_at: string; ended_at: string | null; break_type: { name: string } | null }>;
};
type KioskAction = { type: string; time: string; name: string };

const props = defineProps<{
    company: { name: string; slug: string };
    kioskEmployee: { id: number; name: string } | null;
    todayEntry: TodayEntry | null;
    breakTypes: BreakType[];
    kioskAction: KioskAction | null;
    kioskError: string | null;
}>();

type Screen = 'document' | 'actions' | 'confirmation';

const screen = computed<Screen>(() => {
    if (props.kioskAction) return 'confirmation';
    if (props.kioskEmployee) return 'actions';
    return 'document';
});

const showClockOutConfirm = ref(false);
const clockOutConfirmNow = ref(Date.now());
const errorMessage = ref<string | null>(null);
let errorTimeout: ReturnType<typeof setTimeout> | null = null;
const countdown = ref(5);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const lookupForm = useForm({ document_number: '' });

function submitLookup() {
    lookupForm.post(KioskController.lookup().url, {
        preserveScroll: true,
    });
}

function doClockIn() {
    router.post(KioskController.clockIn().url);
}
function doClockOut() {
    router.post(KioskController.clockOut().url);
}
function openClockOutConfirm() {
    clockOutConfirmNow.value = Date.now();
    showClockOutConfirm.value = true;
}
function confirmClockOut() {
    showClockOutConfirm.value = false;
    doClockOut();
}
function doStartBreak(breakTypeId: number) {
    router.post(KioskController.startBreak().url, { break_type_id: breakTypeId });
}
function doEndBreak() {
    router.post(KioskController.endBreak().url);
}

function startCountdown() {
    countdown.value = 5;
    countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            resetKiosk();
        }
    }, 1000);
}

function resetKiosk() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    router.post(KioskController.reset().url);
}

watch(() => props.kioskAction, (val) => {
    if (val) startCountdown();
}, { immediate: true });

watch(() => props.kioskError, (val) => {
    errorMessage.value = val;
    if (errorTimeout) clearTimeout(errorTimeout);
    if (val) {
        errorTimeout = setTimeout(() => { errorMessage.value = null; }, 6000);
    }
}, { immediate: true });

watch(screen, (val) => {
    if (val !== 'actions') {
        showClockOutConfirm.value = false;
    }
});

onMounted(() => {
    if (screen.value === 'confirmation' && !countdownInterval) startCountdown();
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
    if (errorTimeout) clearTimeout(errorTimeout);
});

const entryStatus = computed(() => {
    if (!props.todayEntry?.clock_in) return 'idle';
    if (props.todayEntry.clock_out) return 'clocked_out';
    if (props.todayEntry.breaks?.some((b) => !b.ended_at)) return 'on_break';
    return 'clocked_in';
});
const activeBreak = computed(() => props.todayEntry?.breaks?.find(b => !b.ended_at) ?? null);

const formattedClockIn = computed(() => {
    if (!props.todayEntry?.clock_in) return null;
    return new Date(props.todayEntry.clock_in).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
});

const formattedActiveBreak = computed(() => {
    if (!activeBreak.value) return null;
    return new Date(activeBreak.value.started_at).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
});

const workedTime = computed(() => {
    if (!props.todayEntry?.clock_in) return '—';
    const minutes = Math.max(0, Math.floor((clockOutConfirmNow.value - new Date(props.todayEntry.clock_in).getTime()) / 60000));
    const hours = Math.floor(minutes / 60);
    return `${hours}h ${String(minutes % 60).padStart(2, '0')}m`;
});

function fmt(ts: string) {
    return new Date(ts).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
}

const timeline = computed(() => {
    const entry = props.todayEntry;
    if (!entry?.clock_in) return [];

    const items: Array<{ tone: 'in' | 'break' | 'out'; label: string; time: string; active?: boolean }> = [];

    items.push({ tone: 'in', label: 'Entrada', time: fmt(entry.clock_in) });

    for (const b of entry.breaks ?? []) {
        const breakName = b.break_type?.name ?? 'Pausa';
        if (b.ended_at) {
            items.push({ tone: 'break', label: breakName, time: `${fmt(b.started_at)} – ${fmt(b.ended_at)}` });
        } else {
            items.push({ tone: 'break', label: breakName, time: `${fmt(b.started_at)} – en curso`, active: true });
        }
    }

    if (entry.clock_out) {
        items.push({ tone: 'out', label: 'Salida', time: fmt(entry.clock_out) });
    }

    return items;
});

const daySummary = computed(() => {
    const entry = props.todayEntry;
    if (!entry?.clock_out) return null;
    return {
        netHours: Number(entry.net_hours ?? 0),
        grossHours: Number(entry.gross_hours ?? 0),
        breakCount: (entry.breaks ?? []).filter(b => b.ended_at).length,
    };
});

const confirmationTitle = computed(() => {
    if (!props.kioskAction) return '';
    const map: Record<string, string> = {
        clock_in: '¡Bienvenid@!',
        clock_out: '¡Hasta pronto!',
        break_start: '¡Buen descanso!',
        break_end: '¡De vuelta al trabajo!',
    };
    return map[props.kioskAction.type] ?? '¡Listo!';
});

const confirmationSub = computed(() => {
    if (!props.kioskAction) return '';
    const map: Record<string, string> = {
        clock_in: 'Jornada iniciada',
        clock_out: 'Jornada finalizada',
        break_start: 'Pausa iniciada',
        break_end: 'Pausa finalizada',
    };
    return map[props.kioskAction.type] ?? 'Acción registrada';
});

const progressWidth = computed(() => `${((5 - countdown.value) / 5) * 100}%`);
</script>

<template>
    <Head :title="`Kiosko — ${company.name}`" />

    <div class="kiosk-container">

        <!-- Header strip -->
        <header class="kiosk-header">
            <span class="kiosk-brand">🥭 MangoApp</span>
            <span class="kiosk-company">{{ company.name }}</span>
        </header>

        <!-- SCREEN: document input -->
        <Transition name="slide" mode="out-in">
            <div v-if="screen === 'document'" key="document" class="kiosk-screen">
                <div class="kiosk-card">
                    <div class="kiosk-eyebrow">Registro de asistencia</div>
                    <h1 class="kiosk-title">Ingresa tu<br><em>número de documento</em></h1>

                    <form @submit.prevent="submitLookup" class="kiosk-form">
                        <div class="kiosk-input-wrap">
                            <input
                                v-model="lookupForm.document_number"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="Ej: 1234567890"
                                class="kiosk-input"
                                autofocus
                            />
                            <InputError :message="lookupForm.errors.document_number" class="kiosk-error" />
                        </div>
                        <button
                            type="submit"
                            class="kiosk-btn kiosk-btn--primary"
                            :disabled="lookupForm.processing || !lookupForm.document_number"
                        >
                            <span v-if="lookupForm.processing">Verificando...</span>
                            <span v-else>Continuar →</span>
                        </button>
                    </form>
                </div>

                <div class="kiosk-leaves kiosk-leaves--left" aria-hidden="true">🌿</div>
                <div class="kiosk-leaves kiosk-leaves--right" aria-hidden="true">🌿</div>
            </div>
        </Transition>

        <!-- SCREEN: action select -->
        <Transition name="slide" mode="out-in">
            <div v-if="screen === 'actions' && kioskEmployee" key="actions" class="kiosk-screen">
                <div class="kiosk-card kiosk-card--wide">
                    <div class="kiosk-greeting-name">{{ kioskEmployee.name }}</div>
                    <h1 class="kiosk-greeting">¡Hola! 👋</h1>

                    <!-- Today status -->
                    <div v-if="todayEntry" class="kiosk-status-bar">
                        <div v-if="entryStatus === 'clocked_in'" class="kiosk-status-chip kiosk-status-chip--working">
                            <span class="kiosk-dot kiosk-dot--pulse"></span>
                            Trabajando desde las {{ formattedClockIn }}
                        </div>
                        <div v-else-if="entryStatus === 'on_break'" class="kiosk-status-chip kiosk-status-chip--break">
                            <span class="kiosk-dot kiosk-dot--amber"></span>
                            En pausa desde las {{ formattedActiveBreak }}
                            <span v-if="activeBreak?.break_type"> · {{ activeBreak.break_type.name }}</span>
                        </div>
                        <div v-else-if="entryStatus === 'clocked_out'" class="kiosk-status-chip kiosk-status-chip--done">
                            ✓ Jornada finalizada a las {{ new Date(todayEntry.clock_out!).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) }}
                        </div>
                    </div>
                    <div v-else class="kiosk-status-bar">
                        <div class="kiosk-status-chip kiosk-status-chip--idle">
                            Sin registros de hoy
                        </div>
                    </div>

                    <!-- Inline error feedback -->
                    <Transition name="fade">
                        <div v-if="errorMessage" class="kiosk-alert">
                            <span class="kiosk-alert-icon" aria-hidden="true">⚠</span>
                            <span>{{ errorMessage }}</span>
                        </div>
                    </Transition>

                    <!-- Timeline -->
                    <div v-if="timeline.length" class="kiosk-timeline">
                        <div
                            v-for="(item, i) in timeline"
                            :key="i"
                            class="kiosk-tl-row"
                            :class="{ 'kiosk-tl-row--active': item.active }"
                        >
                            <div class="kiosk-tl-icon">
                                <span class="kiosk-tl-dot" :class="`kiosk-tl-dot--${item.tone}`"></span>
                            </div>
                            <div class="kiosk-tl-line" v-if="i < timeline.length - 1"></div>
                            <div class="kiosk-tl-content">
                                <span class="kiosk-tl-label">{{ item.label }}</span>
                                <span class="kiosk-tl-time">{{ item.time }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Day summary (only when finished) -->
                    <div v-if="daySummary" class="kiosk-summary">
                        <div class="kiosk-summary-item">
                            <span class="kiosk-summary-value">{{ daySummary.netHours.toFixed(1) }}h</span>
                            <span class="kiosk-summary-label">Horas netas</span>
                        </div>
                        <div class="kiosk-summary-divider"></div>
                        <div class="kiosk-summary-item">
                            <span class="kiosk-summary-value">{{ daySummary.grossHours.toFixed(1) }}h</span>
                            <span class="kiosk-summary-label">Horas brutas</span>
                        </div>
                        <div class="kiosk-summary-divider"></div>
                        <div class="kiosk-summary-item">
                            <span class="kiosk-summary-value">{{ daySummary.breakCount }}</span>
                            <span class="kiosk-summary-label">Pausas</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="kiosk-actions">
                        <!-- No entry today or not yet clocked in -->
                        <template v-if="entryStatus === 'idle'">
                            <button @click="doClockIn" class="kiosk-btn kiosk-btn--primary kiosk-btn--lg">
                                ▶ Iniciar jornada
                            </button>
                        </template>

                        <!-- Working -->
                        <template v-else-if="entryStatus === 'clocked_in'">
                            <p v-if="breakTypes.length" class="kiosk-break-label">¿Vas a tomar un descanso?</p>
                            <div v-if="breakTypes.length" class="kiosk-break-grid">
                                <button
                                    v-for="bt in breakTypes"
                                    :key="bt.id"
                                    @click="doStartBreak(bt.id)"
                                    class="kiosk-break-btn"
                                >
                                    <span v-if="bt.icon" class="kiosk-break-icon">{{ bt.icon }}</span>
                                    {{ bt.name }}
                                </button>
                            </div>

                            <div class="kiosk-divider">
                                <span class="kiosk-divider-label">¿Terminaste?</span>
                            </div>

                            <button @click="openClockOutConfirm" class="kiosk-btn kiosk-btn--danger">
                                ⏹ Finalizar jornada
                            </button>
                        </template>

                        <!-- On break -->
                        <template v-else-if="entryStatus === 'on_break'">
                            <button @click="doEndBreak" class="kiosk-btn kiosk-btn--primary kiosk-btn--lg">
                                ▶ Finalizar pausa
                            </button>
                        </template>

                        <!-- Finished -->
                        <template v-else-if="entryStatus === 'clocked_out'">
                            <div class="kiosk-done-msg">
                                Tu jornada de hoy ya fue registrada. ¡Hasta mañana! 🌅
                            </div>
                        </template>
                    </div>

                    <button @click="resetKiosk" class="kiosk-link">
                        ← Ingresar otro documento
                    </button>
                </div>
            </div>
        </Transition>

        <!-- SCREEN: confirmation -->
        <Transition name="slide" mode="out-in">
            <div v-if="screen === 'confirmation' && kioskAction" key="confirmation" class="kiosk-screen">
                <div class="kiosk-card kiosk-card--confirm">
                    <div class="kiosk-check">✓</div>
                    <div class="kiosk-confirm-name">{{ kioskAction.name }}</div>
                    <h1 class="kiosk-confirm-title">{{ confirmationTitle }}</h1>
                    <p class="kiosk-confirm-sub">
                        {{ confirmationSub }}
                        <span class="kiosk-confirm-time">a las {{ kioskAction.time }}</span>
                    </p>

                    <!-- Progress bar -->
                    <div class="kiosk-progress-track">
                        <div class="kiosk-progress-bar" :style="{ width: progressWidth }"></div>
                    </div>
                    <p class="kiosk-progress-label">Regresando en {{ countdown }}...</p>

                    <button @click="resetKiosk" class="kiosk-btn kiosk-btn--ghost kiosk-btn--sm">
                        Nuevo registro →
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Clock-out confirmation modal -->
        <Transition name="fade">
            <div
                v-if="showClockOutConfirm"
                class="kiosk-modal-overlay"
                @click.self="showClockOutConfirm = false"
            >
                <div class="kiosk-modal" role="dialog" aria-modal="true" aria-labelledby="kiosk-clockout-title">
                    <div class="kiosk-modal-icon">⏹</div>
                    <h2 id="kiosk-clockout-title" class="kiosk-modal-title">¿Finalizar tu jornada?</h2>

                    <div class="kiosk-modal-stats">
                        <div class="kiosk-modal-stat">
                            <span class="kiosk-modal-stat-label">Entrada</span>
                            <span class="kiosk-modal-stat-value">{{ formattedClockIn ?? '—' }}</span>
                        </div>
                        <div class="kiosk-modal-stat">
                            <span class="kiosk-modal-stat-label">Trabajado</span>
                            <span class="kiosk-modal-stat-value">{{ workedTime }}</span>
                        </div>
                    </div>

                    <div class="kiosk-modal-actions">
                        <button @click="showClockOutConfirm = false" class="kiosk-btn kiosk-btn--primary kiosk-btn--lg">
                            No, volver
                        </button>
                        <button @click="confirmClockOut" class="kiosk-btn kiosk-btn--danger-solid">
                            Sí, finalizar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;1,9..144,300&family=DM+Sans:wght@300;400;500;600&display=swap');

/* ─── Layout ─── */
.kiosk-container {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: radial-gradient(ellipse at 20% 50%, #132a16 0%, #0b1a0c 60%, #080f08 100%);
}

.kiosk-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 2rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.kiosk-brand {
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    font-size: 1.1rem;
    color: #f0ebe0;
    letter-spacing: -0.02em;
}

.kiosk-company {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    color: rgba(240,235,224,0.5);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

/* ─── Screens ─── */
.kiosk-screen {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    position: relative;
}

/* ─── Card ─── */
.kiosk-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 2rem;
    padding: 3rem 2.5rem;
    width: 100%;
    max-width: 480px;
    backdrop-filter: blur(12px);
    box-shadow: 0 32px 80px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.08);
    text-align: center;
}

.kiosk-card--wide {
    max-width: 560px;
}

.kiosk-card--confirm {
    max-width: 480px;
}

/* ─── Typography ─── */
.kiosk-eyebrow {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #e8a020;
    margin-bottom: 1rem;
}

.kiosk-title {
    font-family: 'Fraunces', serif;
    font-size: clamp(2rem, 5vw, 2.75rem);
    font-weight: 300;
    line-height: 1.15;
    color: #f0ebe0;
    margin-bottom: 2.5rem;
}

.kiosk-title em {
    font-style: italic;
    color: #e8a020;
}

.kiosk-greeting-name {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    font-weight: 500;
    color: #e8a020;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.kiosk-greeting {
    font-family: 'Fraunces', serif;
    font-size: clamp(2rem, 5vw, 2.75rem);
    font-weight: 300;
    color: #f0ebe0;
    margin-bottom: 1.5rem;
}

/* ─── Form ─── */
.kiosk-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kiosk-input-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kiosk-input {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 1rem;
    padding: 1.125rem 1.5rem;
    font-family: 'DM Sans', sans-serif;
    font-size: 1.375rem;
    font-weight: 400;
    color: #f0ebe0;
    text-align: center;
    letter-spacing: 0.1em;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
}

.kiosk-input::placeholder {
    color: rgba(240,235,224,0.25);
    letter-spacing: 0.02em;
}

.kiosk-input:focus {
    border-color: #e8a020;
    background: rgba(232,160,32,0.06);
}

.kiosk-error {
    color: #f87171;
    font-size: 0.875rem;
}

/* ─── Buttons ─── */
.kiosk-btn {
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
    border-radius: 1rem;
    border: none;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
    display: block;
    width: 100%;
    padding: 1rem 2rem;
    font-size: 1rem;
}

.kiosk-btn--primary {
    background: #e8a020;
    color: #0b1a0c;
    font-weight: 600;
}

.kiosk-btn--primary:hover:not(:disabled) {
    background: #f5b030;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(232,160,32,0.3);
}

.kiosk-btn--primary:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.kiosk-btn--amber {
    background: rgba(232,160,32,0.15);
    color: #e8a020;
    border: 1.5px solid rgba(232,160,32,0.3);
    font-weight: 500;
}

.kiosk-btn--amber:hover {
    background: rgba(232,160,32,0.25);
    transform: translateY(-1px);
}

.kiosk-btn--ghost {
    background: transparent;
    color: rgba(240,235,224,0.5);
    border: 1.5px solid rgba(255,255,255,0.12);
}

.kiosk-btn--ghost:hover {
    border-color: rgba(255,255,255,0.25);
    color: rgba(240,235,224,0.8);
}

.kiosk-btn--danger {
    background: transparent;
    color: #e0875f;
    border: 1.5px solid rgba(210,115,74,0.35);
    font-weight: 500;
}

.kiosk-btn--danger:hover {
    background: rgba(210,115,74,0.12);
    border-color: rgba(210,115,74,0.55);
    color: #ec9870;
}

.kiosk-btn--danger-solid {
    background: #c2683f;
    color: #fdf3ec;
    font-weight: 600;
}

.kiosk-btn--danger-solid:hover {
    background: #d4764a;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(194,104,63,0.3);
}

/* ─── Divider ─── */
.kiosk-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1rem 0;
}

.kiosk-divider::before,
.kiosk-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.08);
}

.kiosk-divider-label {
    font-size: 0.8rem;
    color: rgba(240,235,224,0.35);
    white-space: nowrap;
}

/* ─── Confirmation modal ─── */
.kiosk-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: rgba(8,15,8,0.72);
    backdrop-filter: blur(6px);
}

.kiosk-modal {
    width: 100%;
    max-width: 420px;
    background: rgba(18,34,20,0.98);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.75rem;
    padding: 2.5rem 2rem;
    text-align: center;
    box-shadow: 0 32px 80px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.08);
    animation: pop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.kiosk-modal-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background: rgba(210,115,74,0.15);
    border: 2px solid rgba(210,115,74,0.3);
    color: #e0875f;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
}

.kiosk-modal-title {
    font-family: 'Fraunces', serif;
    font-size: clamp(1.5rem, 4vw, 1.75rem);
    font-weight: 300;
    color: #f0ebe0;
    margin-bottom: 1.5rem;
}

.kiosk-modal-stats {
    display: flex;
    margin-bottom: 1.75rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem;
    padding: 1rem;
}

.kiosk-modal-stat {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.kiosk-modal-stat + .kiosk-modal-stat {
    border-left: 1px solid rgba(255,255,255,0.08);
}

.kiosk-modal-stat-label {
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(240,235,224,0.4);
}

.kiosk-modal-stat-value {
    font-family: 'Fraunces', serif;
    font-size: 1.4rem;
    font-weight: 300;
    color: #f0ebe0;
}

.kiosk-modal-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.kiosk-btn--lg {
    padding: 1.25rem 2rem;
    font-size: 1.125rem;
    margin-bottom: 0.75rem;
}

.kiosk-btn--sm {
    padding: 0.625rem 1.5rem;
    font-size: 0.875rem;
}

.kiosk-link {
    background: none;
    border: none;
    color: rgba(240,235,224,0.35);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    cursor: pointer;
    margin-top: 1.5rem;
    transition: color 0.2s;
    display: block;
    width: 100%;
    text-align: center;
    padding: 0;
}

.kiosk-link:hover {
    color: rgba(240,235,224,0.7);
}

/* ─── Actions layout ─── */
.kiosk-actions {
    margin: 1.5rem 0 0;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ─── Inline alert ─── */
.kiosk-alert {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0.75rem 0 0;
    padding: 0.85rem 1.1rem;
    border-radius: 1rem;
    background: rgba(210,115,74,0.14);
    border: 1px solid rgba(210,115,74,0.35);
    color: #ec9870;
    font-size: 0.95rem;
    font-weight: 500;
    text-align: left;
}

.kiosk-alert-icon {
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* ─── Status chip ─── */
.kiosk-status-bar {
    margin-bottom: 0.5rem;
}

.kiosk-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.kiosk-status-chip--working {
    background: rgba(34,197,94,0.12);
    color: #4ade80;
    border: 1px solid rgba(34,197,94,0.2);
}

.kiosk-status-chip--break {
    background: rgba(232,160,32,0.12);
    color: #e8a020;
    border: 1px solid rgba(232,160,32,0.2);
}

.kiosk-status-chip--done {
    background: rgba(148,163,184,0.1);
    color: rgba(240,235,224,0.5);
    border: 1px solid rgba(255,255,255,0.08);
}

.kiosk-status-chip--idle {
    background: rgba(255,255,255,0.05);
    color: rgba(240,235,224,0.4);
    border: 1px solid rgba(255,255,255,0.06);
}

.kiosk-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.kiosk-dot--pulse {
    background: #4ade80;
    animation: pulse 2s infinite;
}

.kiosk-dot--amber {
    background: #e8a020;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* ─── Break picker ─── */
.kiosk-break-label {
    font-size: 0.875rem;
    color: rgba(240,235,224,0.5);
    margin-bottom: 1rem;
}

.kiosk-break-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.kiosk-break-btn {
    background: rgba(255,255,255,0.05);
    border: 1.5px solid rgba(255,255,255,0.1);
    border-radius: 1rem;
    padding: 1rem 0.75rem;
    color: #f0ebe0;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.18s;
}

.kiosk-break-btn:hover {
    background: rgba(232,160,32,0.12);
    border-color: rgba(232,160,32,0.3);
    color: #e8a020;
    transform: translateY(-2px);
}

.kiosk-break-icon {
    font-size: 1.5rem;
}

/* ─── Done message ─── */
.kiosk-done-msg {
    background: rgba(255,255,255,0.04);
    border-radius: 1rem;
    padding: 1.5rem;
    color: rgba(240,235,224,0.5);
    font-size: 1rem;
    line-height: 1.6;
    margin: 0.5rem 0;
}

/* ─── Confirmation screen ─── */
.kiosk-check {
    width: 5rem;
    height: 5rem;
    border-radius: 50%;
    background: rgba(34,197,94,0.15);
    border: 2px solid rgba(34,197,94,0.3);
    color: #4ade80;
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    animation: pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes pop {
    from { transform: scale(0.5); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.kiosk-confirm-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #e8a020;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.kiosk-confirm-title {
    font-family: 'Fraunces', serif;
    font-size: clamp(1.75rem, 4vw, 2.5rem);
    font-weight: 300;
    color: #f0ebe0;
    margin-bottom: 0.75rem;
}

.kiosk-confirm-sub {
    font-size: 1rem;
    color: rgba(240,235,224,0.6);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.kiosk-confirm-time {
    color: #e8a020;
    font-weight: 500;
}

/* ─── Progress bar ─── */
.kiosk-progress-track {
    width: 100%;
    height: 4px;
    background: rgba(255,255,255,0.08);
    border-radius: 2px;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

.kiosk-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #e8a020, #4ade80);
    border-radius: 2px;
    transition: width 1s linear;
}

.kiosk-progress-label {
    font-size: 0.8rem;
    color: rgba(240,235,224,0.35);
    margin-bottom: 1.5rem;
}

/* ─── Decorative leaves ─── */
.kiosk-leaves {
    position: absolute;
    font-size: 6rem;
    opacity: 0.06;
    pointer-events: none;
    user-select: none;
}

.kiosk-leaves--left {
    left: -1rem;
    bottom: 2rem;
    transform: rotate(-30deg) scaleX(-1);
}

.kiosk-leaves--right {
    right: -1rem;
    top: 2rem;
    transform: rotate(20deg);
}

/* ─── Timeline ─── */
.kiosk-timeline {
    margin: 1.25rem 0 0.25rem;
    display: flex;
    flex-direction: column;
    gap: 0;
    text-align: left;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem;
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.025);
}

.kiosk-tl-row {
    display: grid;
    grid-template-columns: 1.75rem 2px 1fr;
    align-items: start;
    gap: 0 0.75rem;
    min-height: 2.25rem;
}

.kiosk-tl-row--active .kiosk-tl-label {
    color: #e8a020;
}

.kiosk-tl-row--active .kiosk-tl-time {
    color: rgba(232,160,32,0.7);
}

.kiosk-tl-icon {
    display: flex;
    justify-content: center;
    padding-top: 0.45rem;
}

.kiosk-tl-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    background: rgba(240,235,224,0.4);
}

.kiosk-tl-dot--in {
    background: #4ade80;
}

.kiosk-tl-dot--break {
    background: #e8a020;
}

.kiosk-tl-dot--out {
    background: #d2734a;
}

.kiosk-tl-row--active .kiosk-tl-dot {
    box-shadow: 0 0 0 3px rgba(232,160,32,0.2);
    animation: pulse 2s infinite;
}

.kiosk-tl-line {
    width: 2px;
    background: rgba(255,255,255,0.08);
    margin: 1.4rem auto 0;
    min-height: 1rem;
    align-self: stretch;
}

.kiosk-tl-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.1rem;
    padding: 0.15rem 0 0.65rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.kiosk-tl-row:last-child .kiosk-tl-content {
    border-bottom: none;
    padding-bottom: 0.2rem;
}

.kiosk-tl-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(240,235,224,0.8);
}

.kiosk-tl-time {
    font-size: 0.8rem;
    color: rgba(240,235,224,0.4);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* ─── Day summary ─── */
.kiosk-summary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin: 1rem 0 0.5rem;
    background: rgba(232,160,32,0.07);
    border: 1px solid rgba(232,160,32,0.15);
    border-radius: 1rem;
    padding: 1rem 1.25rem;
}

.kiosk-summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    flex: 1;
}

.kiosk-summary-value {
    font-family: 'Fraunces', serif;
    font-size: 1.5rem;
    font-weight: 300;
    color: #e8a020;
    line-height: 1;
}

.kiosk-summary-label {
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(240,235,224,0.4);
}

.kiosk-summary-divider {
    width: 1px;
    height: 2.5rem;
    background: rgba(255,255,255,0.08);
}

/* ─── Transitions ─── */
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-enter-from {
    opacity: 0;
    transform: translateX(24px);
}

.slide-leave-to {
    opacity: 0;
    transform: translateX(-24px);
}
</style>
