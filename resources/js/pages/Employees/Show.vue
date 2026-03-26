<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2, ArrowLeft, Eye, EyeOff, Copy, Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Employee } from '@/types';

type Props = {
    employee: Employee;
};

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: { url: '/dashboard', method: 'get' } },
    { title: t('employees.breadcrumb'), href: employeesIndex() },
    { title: props.employee.user.name, href: { url: `/employees/${props.employee.id}`, method: 'get' } },
];

const createdPassword = computed(() => page.props.flash?.created_password ?? null);
const showCreatedPassword = ref(false);
const passwordCopied = ref(false);

function copyPassword() {
    if (!createdPassword.value) {
        return;
    }
    navigator.clipboard.writeText(createdPassword.value).then(() => {
        passwordCopied.value = true;
        setTimeout(() => {
            passwordCopied.value = false;
        }, 2000);
    }).catch(() => {
        // clipboard not available (non-HTTPS or denied); user can copy manually from the visible text
    });
}

function deleteEmployee() {
    if (confirm(t('employees.confirm_delete', { name: props.employee.user.name }))) {
        router.delete(`/employees/${props.employee.id}`);
    }
}
</script>

<template>
    <Head :title="employee.user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <!-- Created password banner -->
            <Alert v-if="createdPassword" class="mb-6 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                <AlertTitle class="text-amber-800 dark:text-amber-200">{{ t('employees.show.created_password_title') }}</AlertTitle>
                <AlertDescription class="mt-2 text-amber-700 dark:text-amber-300">
                    <p class="mb-3 text-sm">{{ t('employees.show.created_password_warning') }}</p>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm tracking-widest">
                            {{ showCreatedPassword ? createdPassword : '••••••••••••' }}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7 text-amber-700 hover:text-amber-900 dark:text-amber-300"
                            :aria-label="showCreatedPassword ? t('employees.form.hide_password') : t('employees.form.show_password')"
                            @click="showCreatedPassword = !showCreatedPassword"
                        >
                            <EyeOff v-if="showCreatedPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7 text-amber-700 hover:text-amber-900 dark:text-amber-300"
                            :aria-label="t('employees.show.copy_password')"
                            @click="copyPassword"
                        >
                            <Check v-if="passwordCopied" class="size-4" />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>

            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <Link href="/employees">
                            <ArrowLeft class="size-4" />
                        </Link>
                    </Button>
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary text-lg font-semibold">
                            {{ employee.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h1 class="text-xl font-bold">{{ employee.user.name }}</h1>
                            <p class="text-muted-foreground text-sm">{{ employee.user.email }}</p>
                        </div>
                        <Badge v-if="!employee.user.is_active" variant="secondary">{{ t('common.inactive_badge') }}</Badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/employees/${employee.id}/edit`">
                            <Pencil class="mr-2 size-4" />
                            {{ t('common.edit') }}
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="deleteEmployee">
                        <Trash2 class="mr-2 size-4" />
                        {{ t('common.delete') }}
                    </Button>
                </div>
            </div>

            <!-- Details -->
            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('employees.show.personal_info') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.phone') }}</span>
                            <span>{{ employee.user.phone ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.code') }}</span>
                            <Badge variant="outline" class="font-mono">{{ employee.employee_code ?? '—' }}</Badge>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.hire_date') }}</span>
                            <span>{{ employee.hire_date ?? '—' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('employees.show.organization') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.department') }}</span>
                            <span>{{ employee.department?.name ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.position') }}</span>
                            <span>{{ employee.position?.name ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.schedule') }}</span>
                            <span>{{ employee.schedule?.name ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.location') }}</span>
                            <span>{{ employee.location?.name ?? '—' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('employees.show.salary') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.salary_type') }}</span>
                            <span>{{ employee.salary_type === 'hourly' ? t('employees.show.salary_hourly') : t('employees.show.salary_monthly') }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ t('employees.show.hourly_rate') }}</span>
                            <span>{{ employee.hourly_rate ? `$${Number(employee.hourly_rate).toLocaleString(locale)}` : '—' }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
