<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Eye, EyeOff, ShieldCheck, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { index, storeAdminUser, update } from '@/actions/App/Http/Controllers/SuperAdmin/CompanyController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
};

type CompanyDetail = {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
    timezone: string;
    country: string | null;
    subscription_plan: string | null;
    trial_ends_at: string | null;
    onboarding_completed: boolean;
};

type Props = {
    company: CompanyDetail;
    admins: AdminUser[];
};

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('super_admin.companies.title'), href: index() },
    { title: props.company.name, href: { url: `/super-admin/companies/${props.company.id}/edit`, method: 'get' } },
];

// Company form
const companyForm = useForm({
    name: props.company.name,
    slug: props.company.slug,
    timezone: props.company.timezone,
    country: props.company.country ?? '',
    subscription_plan: props.company.subscription_plan ?? '',
    trial_ends_at: props.company.trial_ends_at ? props.company.trial_ends_at.substring(0, 10) : '',
});

function submitCompany() {
    companyForm.put(update.url(props.company), { preserveScroll: true });
}

// Admin creation form
const adminForm = useForm({
    name: '',
    email: '',
});

function submitAdmin() {
    adminForm.post(storeAdminUser.url(props.company), { preserveScroll: true });
}

// Created password banner
const createdPassword = computed(() => page.props.flash?.created_password ?? null);
const showCreatedPassword = ref(false);
const passwordCopied = ref(false);

function copyPassword() {
    if (!createdPassword.value) return;
    navigator.clipboard.writeText(createdPassword.value).then(() => {
        passwordCopied.value = true;
        setTimeout(() => { passwordCopied.value = false; }, 2000);
    }).catch(() => {
        // clipboard not available; user can copy manually
    });
}

// Flash success
const flashSuccess = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <Head :title="t('super_admin.companies.edit_title', { name: company.name })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <!-- Back link -->
            <div class="mb-6 flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <a :href="index().url">
                        <ArrowLeft class="size-4" />
                    </a>
                </Button>
                <h1 class="text-xl font-bold">{{ company.name }}</h1>
            </div>

            <!-- Flash success -->
            <Alert v-if="flashSuccess && !createdPassword" class="mb-6 border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                <AlertDescription class="text-green-700 dark:text-green-300">{{ flashSuccess }}</AlertDescription>
            </Alert>

            <!-- Created password banner -->
            <Alert v-if="createdPassword" class="mb-6 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                <AlertTitle class="text-amber-800 dark:text-amber-200">{{ t('super_admin.companies.admin_created_title') }}</AlertTitle>
                <AlertDescription class="mt-2 text-amber-700 dark:text-amber-300">
                    <p class="mb-3 text-sm">{{ t('super_admin.companies.admin_created_warning') }}</p>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm tracking-widest">
                            {{ showCreatedPassword ? createdPassword : '••••••••••••' }}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7 text-amber-700 hover:text-amber-900 dark:text-amber-300"
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
                            @click="copyPassword"
                        >
                            <Check v-if="passwordCopied" class="size-4" />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>

            <div class="flex flex-col gap-6">
                <!-- Section 1: Company info -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('super_admin.companies.info_title') }}</CardTitle>
                        <CardDescription>{{ t('super_admin.companies.info_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-5" @submit.prevent="submitCompany">
                            <!-- Name -->
                            <div class="grid gap-2">
                                <Label for="name">{{ t('super_admin.companies.field_name') }}</Label>
                                <Input id="name" v-model="companyForm.name" type="text" />
                                <InputError :message="companyForm.errors.name" />
                            </div>

                            <!-- Slug -->
                            <div class="grid gap-2">
                                <Label for="slug">{{ t('super_admin.companies.field_slug') }}</Label>
                                <Input id="slug" v-model="companyForm.slug" type="text" class="font-mono" />
                                <p class="text-muted-foreground text-xs">{{ t('super_admin.companies.field_slug_help') }}</p>
                                <InputError :message="companyForm.errors.slug" />
                            </div>

                            <!-- Timezone + Country side by side -->
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="timezone">{{ t('super_admin.companies.field_timezone') }}</Label>
                                    <Input id="timezone" v-model="companyForm.timezone" type="text" />
                                    <InputError :message="companyForm.errors.timezone" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="country">{{ t('super_admin.companies.field_country') }}</Label>
                                    <Input id="country" v-model="companyForm.country" type="text" maxlength="2" class="uppercase" />
                                    <InputError :message="companyForm.errors.country" />
                                </div>
                            </div>

                            <!-- Subscription plan + Trial ends -->
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="subscription_plan">{{ t('super_admin.companies.field_plan') }}</Label>
                                    <Input id="subscription_plan" v-model="companyForm.subscription_plan" type="text" />
                                    <InputError :message="companyForm.errors.subscription_plan" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="trial_ends_at">{{ t('super_admin.companies.field_trial') }}</Label>
                                    <Input id="trial_ends_at" v-model="companyForm.trial_ends_at" type="date" />
                                    <InputError :message="companyForm.errors.trial_ends_at" />
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pt-1">
                                <Button type="submit" :disabled="companyForm.processing">
                                    {{ companyForm.processing ? t('common.saving') : t('common.save') }}
                                </Button>
                                <span v-if="companyForm.recentlySuccessful" class="text-sm text-green-600">
                                    {{ t('common.saved') }}
                                </span>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Section 2: Existing admins -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <ShieldCheck class="text-muted-foreground size-5" />
                            <div>
                                <CardTitle>{{ t('super_admin.companies.admins_title') }}</CardTitle>
                                <CardDescription class="mt-1">{{ t('super_admin.companies.admins_description') }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="admins.length > 0" class="divide-y">
                            <div
                                v-for="admin in admins"
                                :key="admin.id"
                                class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <div class="bg-secondary flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                                    {{ admin.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ admin.name }}</p>
                                    <p class="text-muted-foreground truncate text-xs">{{ admin.email }}</p>
                                </div>
                                <Badge :variant="admin.is_active ? 'secondary' : 'outline'" class="shrink-0 text-xs">
                                    {{ admin.is_active ? t('common.active') : t('common.inactive_badge') }}
                                </Badge>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground text-sm">
                            {{ t('super_admin.companies.admins_empty') }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Section 3: Create admin -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <UserPlus class="text-muted-foreground size-5" />
                            <div>
                                <CardTitle>{{ t('super_admin.companies.create_admin_title') }}</CardTitle>
                                <CardDescription class="mt-1">{{ t('super_admin.companies.create_admin_description') }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-5" @submit.prevent="submitAdmin">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="admin_name">{{ t('common.name') }}</Label>
                                    <Input id="admin_name" v-model="adminForm.name" type="text" />
                                    <InputError :message="adminForm.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="admin_email">{{ t('common.email') }}</Label>
                                    <Input id="admin_email" v-model="adminForm.email" type="email" />
                                    <InputError :message="adminForm.errors.email" />
                                </div>
                            </div>

                            <Separator />

                            <div>
                                <Button type="submit" :disabled="adminForm.processing">
                                    {{ adminForm.processing ? t('common.saving') : t('super_admin.companies.create_admin_submit') }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
