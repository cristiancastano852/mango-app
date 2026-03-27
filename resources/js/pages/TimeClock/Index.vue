<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LogIn, LogOut, Play, Coffee } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDecimalHours } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, BreakType, Employee, TimeEntry } from '@/types';

type Props = {
    employee: (Employee & { user: { name: string } }) | null;
    todayEntry: TimeEntry | null;
    breakTypes: BreakType[];
    recentEntries: TimeEntry[];
};

const props = defineProps<Props>();
const { t, locale } = useI18n();

const dateLocale = computed(() => locale.value === 'es' ? 'es-CO' : 'en-US');

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: { url: '/dashboard', method: 'get' } },
    { title: t('time_clock.breadcrumb'), href: { url: '/time-clock', method: 'get' } },
];

// Live timer
const elapsed = ref('00:00:00');
let timerInterval: ReturnType<typeof setInterval> | null = null;

function updateTimer() {
    if (!props.todayEntry?.clock_in || props.todayEntry?.clock_out) {
        elapsed.value = props.todayEntry?.clock_out
            ? formatHours(Number(props.todayEntry.net_hours))
            : '00:00:00';
        return;
    }

    const start = new Date(props.todayEntry.clock_in).getTime();
    const now = Date.now();
    const diff = Math.floor((now - start) / 1000);

    const h = String(Math.floor(diff / 3600)).padStart(2, '0');
    const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
    const s = String(diff % 60).padStart(2, '0');
    elapsed.value = `${h}:${m}:${s}`;
}

onMounted(() => {
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// Status
const status = computed(() => {
    if (!props.todayEntry || !props.todayEntry.clock_in) return 'idle';
    if (props.todayEntry.clock_out) return 'done';

    const activeBreak = props.todayEntry.breaks?.find((b) => !b.ended_at);
    if (activeBreak) return 'on-break';
    return 'working';
});

const activeBreak = computed(() =>
    props.todayEntry?.breaks?.find((b) => !b.ended_at) ?? null,
);

const statusLabel = computed(() => {
    switch (status.value) {
        case 'idle': return t('time_clock.status.idle');
        case 'working': return t('time_clock.status.working');
        case 'on-break': return activeBreak.value?.break_type?.name ?? t('time_clock.status.on_break');
        case 'done': return t('time_clock.status.done');
        default: return '';
    }
});

const statusColor = computed(() => {
    switch (status.value) {
        case 'idle': return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
        case 'working': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'on-break': return 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300';
        case 'done': return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        default: return '';
    }
});

function formatHours(hours: number): string {
    const h = Math.floor(hours);
    const m = Math.floor((hours - h) * 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
}

function formatTime(datetime: string | null): string {
    if (!datetime) return '—';
    return new Date(datetime).toLocaleTimeString(dateLocale.value, { hour: '2-digit', minute: '2-digit' });
}

// Actions
const loading = ref(false);

function clockIn() {
    loading.value = true;
    router.post('/time-clock/clock-in', {}, {
        onFinish: () => (loading.value = false),
    });
}

function clockOut() {
    if (!confirm(t('time_clock.confirm_checkout'))) return;
    loading.value = true;
    router.post('/time-clock/clock-out', {}, {
        onFinish: () => (loading.value = false),
    });
}

function startBreak(breakTypeId: number) {
    loading.value = true;
    router.post('/time-clock/break/start', { break_type_id: breakTypeId }, {
        onFinish: () => (loading.value = false),
    });
}

function endBreak() {
    loading.value = true;
    router.post('/time-clock/break/end', {}, {
        onFinish: () => (loading.value = false),
    });
}
</script>

<template>
    <Head :title="t('time_clock.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
            <!-- No employee profile -->
            <div v-if="!employee" class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
                <p class="text-muted-foreground">{{ t('time_clock.no_employee') }}</p>
            </div>

            <template v-else>
                <!-- Status Card -->
                <Card>
                    <CardContent class="flex flex-col items-center gap-6 p-6 sm:p-8">
                        <!-- Status badge -->
                        <div :class="['inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium', statusColor]">
                            <span class="relative flex size-2">
                                <span
                                    v-if="status === 'working'"
                                    class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75"
                                />
                                <span
                                    :class="[
                                        'relative inline-flex size-2 rounded-full',
                                        status === 'working' ? 'bg-green-500' :
                                        status === 'on-break' ? 'bg-amber-500' :
                                        status === 'done' ? 'bg-blue-500' : 'bg-gray-400'
                                    ]"
                                />
                            </span>
                            {{ statusLabel }}
                        </div>

                        <!-- Timer -->
                        <div class="text-5xl font-bold tracking-tight tabular-nums sm:text-6xl">
                            {{ elapsed }}
                        </div>

                        <!-- Clock in/out times -->
                        <div v-if="todayEntry?.clock_in" class="text-muted-foreground flex gap-6 text-sm">
                            <span>{{ t('time_clock.clock_in_label') }} {{ formatTime(todayEntry.clock_in) }}</span>
                            <span v-if="todayEntry.clock_out">{{ t('time_clock.clock_out_label') }} {{ formatTime(todayEntry.clock_out) }}</span>
                        </div>

                        <!-- Main action -->
                        <div class="flex gap-3">
                            <Button
                                v-if="status === 'idle'"
                                size="lg"
                                class="bg-green-600 hover:bg-green-700 gap-2 px-8"
                                :disabled="loading"
                                @click="clockIn"
                            >
                                <LogIn class="size-5" />
                                {{ t('time_clock.check_in') }}
                            </Button>

                            <Button
                                v-if="status === 'on-break'"
                                size="lg"
                                class="gap-2 px-8"
                                :disabled="loading"
                                @click="endBreak"
                            >
                                <Play class="size-5" />
                                {{ t('time_clock.resume_work') }}
                            </Button>

                            <Button
                                v-if="status === 'working'"
                                variant="destructive"
                                size="lg"
                                class="gap-2 px-8"
                                :disabled="loading"
                                @click="clockOut"
                            >
                                <LogOut class="size-5" />
                                {{ t('time_clock.check_out') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Break buttons -->
                <Card v-if="status === 'working'">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Coffee class="size-4" />
                            {{ t('time_clock.start_break') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <Button
                                v-for="bt in breakTypes"
                                :key="bt.id"
                                variant="outline"
                                class="h-auto flex-col gap-1 py-3"
                                :disabled="loading"
                                @click="startBreak(bt.id)"
                            >
                                <span class="text-lg">{{ bt.icon }}</span>
                                <span class="text-xs">{{ bt.name }}</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Active break info -->
                <Card v-if="status === 'on-break' && activeBreak">
                    <CardContent class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ activeBreak.break_type?.icon }}</span>
                            <div>
                                <p class="font-medium">{{ activeBreak.break_type?.name }}</p>
                                <p class="text-muted-foreground text-sm">{{ t('time_clock.since') }} {{ formatTime(activeBreak.started_at) }}</p>
                            </div>
                        </div>
                        <Button :disabled="loading" @click="endBreak">
                            <Play class="mr-2 size-4" />
                            {{ t('time_clock.end_break') }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Today's breaks -->
                <Card v-if="todayEntry && todayEntry.breaks && todayEntry.breaks.length > 0">
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('time_clock.today_breaks') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-2">
                        <div
                            v-for="b in todayEntry.breaks"
                            :key="b.id"
                            class="flex items-center justify-between rounded-md border p-3 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <span>{{ b.break_type?.icon }}</span>
                                <span class="font-medium">{{ b.break_type?.name }}</span>
                            </div>
                            <div class="text-muted-foreground flex items-center gap-3">
                                <span>{{ formatTime(b.started_at) }} — {{ b.ended_at ? formatTime(b.ended_at) : t('time_clock.active') }}</span>
                                <Badge v-if="b.duration_minutes" variant="outline" class="text-xs">
                                    {{ b.duration_minutes }} {{ t('common.min') }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Summary (when day is done) -->
                <Card v-if="status === 'done' && todayEntry">
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('time_clock.day_summary') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('time_clock.gross_hours') }}</span>
                            <span class="font-medium">{{ formatDecimalHours(todayEntry.gross_hours) }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('time_clock.break_time_unpaid') }}</span>
                            <span class="font-medium">{{ formatDecimalHours(todayEntry.break_hours) }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between text-base font-semibold">
                            <span>{{ t('time_clock.net_hours') }}</span>
                            <span>{{ formatDecimalHours(todayEntry.net_hours) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent history -->
                <Card v-if="recentEntries.length > 0">
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('time_clock.last_7_days') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-2">
                        <div
                            v-for="entry in recentEntries"
                            :key="entry.id"
                            class="flex items-center justify-between rounded-md border p-3 text-sm"
                        >
                            <span class="font-medium">
                                {{ new Date(entry.date + 'T12:00:00').toLocaleDateString(dateLocale, { weekday: 'short', day: 'numeric', month: 'short' }) }}
                            </span>
                            <div class="flex items-center gap-4">
                                <span class="text-muted-foreground">
                                    {{ formatTime(entry.clock_in) }} — {{ formatTime(entry.clock_out) }}
                                </span>
                                <Badge variant="outline" class="font-mono">
                                    {{ formatDecimalHours(entry.net_hours) }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
