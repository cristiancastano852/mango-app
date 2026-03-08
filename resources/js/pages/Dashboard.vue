<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Clock, Coffee, UserCheck, Users } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { store as manualCheckIn } from '@/actions/App/Http/Controllers/Admin/ManualCheckInController';
import { edit as editTimeEntry } from '@/actions/App/Http/Controllers/Admin/TimeEntryController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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

type Kpis = {
    present: number;
    present_delta: number;
    on_break: number;
    absent: number;
    net_hours_today: number;
    avg_net_hours: number;
};

type EmployeeStatus = {
    id: number;
    name: string;
    avatar: string | null;
    status: 'working' | 'on_break' | 'absent' | 'done';
    clock_in: string | null;
    clock_out: string | null;
    net_hours_today: number;
    time_entry_id: number | null;
};

type LateArrival = {
    employee: { id: number; name: string };
    scheduled_at: string;
    minutes_late: number;
};

type SimpleEmployee = {
    id: number;
    name: string;
};

type Props = {
    kpis: Kpis;
    employeeStatus: EmployeeStatus[];
    lateArrivals: LateArrival[];
    employees?: SimpleEmployee[];
};

defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
];

const secondsSinceUpdate = ref(0);
let pollingInterval: ReturnType<typeof setInterval>;
let secondsInterval: ReturnType<typeof setInterval>;

onMounted(() => {
    pollingInterval = setInterval(() => {
        router.reload({ only: ['kpis', 'employeeStatus', 'lateArrivals'], preserveScroll: true });
        secondsSinceUpdate.value = 0;
    }, 60000);

    secondsInterval = setInterval(() => {
        secondsSinceUpdate.value++;
    }, 1000);
});

onUnmounted(() => {
    clearInterval(pollingInterval);
    clearInterval(secondsInterval);
});

const checkInOpen = ref(false);
const checkInForm = useForm({ employee_id: '' });

function submitCheckIn() {
    checkInForm.post(manualCheckIn.url(), {
        onSuccess: () => {
            checkInOpen.value = false;
            checkInForm.reset();
        },
    });
}


function statusColor(status: EmployeeStatus['status']) {
    const map: Record<EmployeeStatus['status'], string> = {
        working: 'bg-green-500',
        on_break: 'bg-amber-400',
        absent: 'bg-red-500',
        done: 'bg-slate-400',
    };
    return map[status];
}

function deltaSign(delta: number) {
    return delta > 0 ? `+${delta}` : `${delta}`;
}
</script>

<template>
    <Head :title="t('dashboard.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Live indicator -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ t('dashboard.title') }}</h1>
                <div class="text-muted-foreground flex items-center gap-2 text-sm">
                    <span class="relative flex size-2">
                        <span class="bg-green-500 absolute inline-flex size-full animate-ping rounded-full opacity-75" />
                        <span class="bg-green-500 relative inline-flex size-2 rounded-full" />
                    </span>
                    {{ t('dashboard.live') }} &middot;
                    {{ t('dashboard.updated', { seconds: secondsSinceUpdate }) }}
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <!-- Present -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.kpi.present') }}</CardTitle>
                        <UserCheck class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ kpis.present }}</div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            <span :class="kpis.present_delta >= 0 ? 'text-green-600' : 'text-red-500'">
                                {{ deltaSign(kpis.present_delta) }}
                            </span>
                            {{ t('dashboard.kpi.vs_yesterday') }}
                        </p>
                    </CardContent>
                </Card>

                <!-- On Break -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.kpi.on_break') }}</CardTitle>
                        <Coffee class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ kpis.on_break }}</div>
                        <p class="text-muted-foreground mt-1 text-xs">&nbsp;</p>
                    </CardContent>
                </Card>

                <!-- Absent -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.kpi.absent') }}</CardTitle>
                        <Users class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ kpis.absent }}</div>
                        <p class="text-muted-foreground mt-1 text-xs">&nbsp;</p>
                    </CardContent>
                </Card>

                <!-- Net Hours -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.kpi.net_hours') }}</CardTitle>
                        <Clock class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ kpis.net_hours_today }}h</div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ t('dashboard.kpi.avg_per_employee') }}: {{ kpis.avg_net_hours }}h
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Late Arrivals -->
            <div v-if="lateArrivals.length > 0">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <AlertTriangle class="size-4 text-amber-500" />
                    {{ t('dashboard.late_arrivals') }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="late in lateArrivals"
                        :key="late.employee.id"
                        variant="outline"
                        class="border-amber-400 text-amber-600 dark:text-amber-400"
                    >
                        {{ late.employee.name }} &mdash;
                        {{ t('dashboard.minutes_late', { min: late.minutes_late }) }}
                        ({{ late.scheduled_at }})
                    </Badge>
                </div>
            </div>

            <!-- Employee Status Table -->
            <div>
                <h2 class="mb-3 text-sm font-semibold">{{ t('dashboard.employee_status') }}</h2>
                <Card>
                    <CardContent class="p-0">
                        <div
                            v-if="employeeStatus.length === 0"
                            class="text-muted-foreground p-8 text-center text-sm"
                        >
                            {{ t('dashboard.no_employees') }}
                        </div>
                        <div v-else class="divide-y">
                            <div
                                v-for="emp in employeeStatus"
                                :key="emp.id"
                                class="flex items-center gap-4 px-4 py-3"
                            >
                                <!-- Avatar -->
                                <div class="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                                    {{ emp.name.charAt(0).toUpperCase() }}
                                </div>

                                <!-- Name + Status -->
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ emp.name }}</p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="size-2 rounded-full" :class="statusColor(emp.status)" />
                                        <span class="text-muted-foreground text-xs">
                                            {{ t(`dashboard.status.${emp.status}`) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Clock times -->
                                <div class="text-muted-foreground hidden text-right text-xs sm:block">
                                    <div v-if="emp.clock_in">{{ emp.clock_in }}</div>
                                    <div v-if="emp.clock_out">→ {{ emp.clock_out }}</div>
                                </div>

                                <!-- Net hours -->
                                <div class="hidden text-right text-sm font-medium sm:block">
                                    <span v-if="emp.net_hours_today > 0">{{ emp.net_hours_today }}h</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </div>

                                <!-- Edit link -->
                                <a
                                    v-if="emp.time_entry_id"
                                    :href="editTimeEntry(emp.time_entry_id).url"
                                    class="text-muted-foreground hover:text-foreground text-xs underline-offset-2 hover:underline"
                                >
                                    {{ t('common.edit') }}
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- FAB Manual Check-in -->
        <Dialog v-model:open="checkInOpen">
            <DialogTrigger as-child>
                <Button
                    size="icon"
                    class="fixed bottom-6 right-6 size-14 rounded-full shadow-lg"
                    :title="t('dashboard.manual_checkin')"
                >
                    <UserCheck class="size-6" />
                </Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ t('dashboard.manual_checkin') }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitCheckIn">
                    <Select v-model="checkInForm.employee_id">
                        <SelectTrigger>
                            <SelectValue :placeholder="t('dashboard.select_employee')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="emp in employeeStatus"
                                :key="emp.id"
                                :value="String(emp.id)"
                            >
                                {{ emp.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="checkInForm.errors.employee_id" class="text-destructive text-sm">
                        {{ checkInForm.errors.employee_id }}
                    </p>
                    <Button type="submit" :disabled="checkInForm.processing || !checkInForm.employee_id">
                        {{ checkInForm.processing ? t('common.saving') : t('dashboard.checkin_btn') }}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
