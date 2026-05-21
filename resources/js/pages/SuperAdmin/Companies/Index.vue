<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, CalendarDays, Pencil } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { edit } from '@/actions/App/Http/Controllers/SuperAdmin/CompanyController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Company = {
    id: number;
    name: string;
    slug: string;
    subscription_plan: string | null;
    created_at: string;
};

type Props = {
    companies: Company[];
};

defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('super_admin.companies.title'), href: { url: '/super-admin/companies', method: 'get' } },
];

function getInitials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function planVariant(plan: string | null): 'default' | 'secondary' | 'outline' {
    if (plan === 'premium') return 'default';
    if (plan === 'trial') return 'secondary';
    return 'outline';
}
</script>

<template>
    <Head :title="t('super_admin.companies.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('super_admin.companies.title') }}</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ t('super_admin.companies.subtitle', { count: companies.length }) }}
                    </p>
                </div>
                <Badge variant="secondary" class="px-3 py-1 text-sm">
                    {{ companies.length }} {{ t('super_admin.companies.count_label') }}
                </Badge>
            </div>

            <!-- Company list -->
            <div class="grid gap-3">
                <Card
                    v-for="company in companies"
                    :key="company.id"
                    class="transition-colors hover:bg-muted/50"
                >
                    <CardContent class="flex items-center gap-4 p-4">
                        <!-- Initials avatar -->
                        <div
                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        >
                            {{ getInitials(company.name) }}
                        </div>

                        <!-- Name + slug -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ company.name }}</p>
                            <code class="text-muted-foreground font-mono text-xs">{{ company.slug }}</code>
                        </div>

                        <!-- Plan -->
                        <Badge :variant="planVariant(company.subscription_plan)" class="hidden shrink-0 capitalize sm:flex">
                            {{ company.subscription_plan ?? 'free' }}
                        </Badge>

                        <!-- Date -->
                        <div class="text-muted-foreground hidden shrink-0 items-center gap-1.5 text-sm md:flex">
                            <CalendarDays class="size-3.5" />
                            {{ formatDate(company.created_at) }}
                        </div>

                        <!-- Edit button -->
                        <Button size="sm" variant="outline" as-child class="shrink-0">
                            <a :href="edit(company).url">
                                <Pencil class="mr-2 size-3.5" />
                                {{ t('common.edit') }}
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Empty state -->
                <div
                    v-if="companies.length === 0"
                    class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-12 text-center"
                >
                    <Building2 class="text-muted-foreground/50 size-10" />
                    <p class="text-muted-foreground text-sm">{{ t('super_admin.companies.empty') }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
