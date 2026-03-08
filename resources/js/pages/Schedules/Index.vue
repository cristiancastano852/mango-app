<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clock, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import {
    create as schedulesCreate,
    destroy as destroySchedule,
    edit as editSchedule,
    index as schedulesIndex,
} from '@/actions/App/Http/Controllers/SchedulesController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, Schedule } from '@/types';

type ScheduleWithCount = Schedule & { employees_count: number };

type Props = {
    schedules: ScheduleWithCount[];
};

defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('schedules.breadcrumb'), href: schedulesIndex() },
];

const dayLabels: Record<number, string> = {
    0: t('schedules.days.0'),
    1: t('schedules.days.1'),
    2: t('schedules.days.2'),
    3: t('schedules.days.3'),
    4: t('schedules.days.4'),
    5: t('schedules.days.5'),
    6: t('schedules.days.6'),
};

function deleteSchedule(schedule: ScheduleWithCount) {
    if (confirm(t('schedules.confirm_delete', { name: schedule.name }))) {
        router.delete(destroySchedule(schedule.id).url);
    }
}
</script>

<template>
    <Head :title="t('schedules.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight">{{ t('schedules.title') }}</h1>
                <Button as="a" :href="schedulesCreate().url">
                    <Plus class="mr-2 size-4" />
                    {{ t('schedules.new_schedule') }}
                </Button>
            </div>

            <!-- Schedule list -->
            <div class="grid gap-3">
                <Card
                    v-for="schedule in schedules"
                    :key="schedule.id"
                    class="transition-colors hover:bg-muted/50"
                >
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full">
                            <Clock class="size-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ schedule.name }}</p>
                            <p class="text-muted-foreground text-sm">
                                {{ schedule.start_time }} – {{ schedule.end_time }}
                                &middot; {{ schedule.break_duration }}{{ t('common.min') }} {{ t('schedules.break_label') }}
                            </p>
                        </div>

                        <!-- Days -->
                        <div class="hidden flex-wrap gap-1 sm:flex">
                            <Badge
                                v-for="day in schedule.days_of_week"
                                :key="day"
                                variant="secondary"
                                class="text-xs"
                            >
                                {{ dayLabels[day] }}
                            </Badge>
                        </div>

                        <!-- Employees count -->
                        <Badge variant="outline" class="hidden shrink-0 lg:flex">
                            {{ t('schedules.employees_assigned', { count: schedule.employees_count }) }}
                        </Badge>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="icon" as="a" :href="editSchedule(schedule.id).url">
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                @click="deleteSchedule(schedule)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty state -->
                <div
                    v-if="schedules.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center"
                >
                    <p class="text-muted-foreground text-sm">{{ t('schedules.no_schedules') }}</p>
                    <Button as="a" :href="schedulesCreate().url" variant="link" class="mt-2">
                        {{ t('schedules.create_first') }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
