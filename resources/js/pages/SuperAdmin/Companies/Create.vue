<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { index, store } from '@/actions/App/Http/Controllers/SuperAdmin/CompanyController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('super_admin.companies.title'), href: index() },
    { title: t('super_admin.companies.create_title'), href: { url: '/super-admin/companies/create', method: 'get' } },
];
</script>

<template>
    <Head :title="t('super_admin.companies.create_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-lg p-4 md:p-6">
            <!-- Back link -->
            <div class="mb-6 flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <a :href="index().url">
                        <ArrowLeft class="size-4" />
                    </a>
                </Button>
                <h1 class="text-xl font-bold">{{ t('super_admin.companies.create_title') }}</h1>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('super_admin.companies.create_title') }}</CardTitle>
                    <CardDescription>{{ t('super_admin.companies.create_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form v-bind="store.form()" v-slot="{ errors, processing }" class="grid gap-5">
                        <div class="grid gap-2">
                            <Label for="company_name">{{ t('super_admin.companies.field_company_name') }}</Label>
                            <Input
                                id="company_name"
                                name="company_name"
                                type="text"
                                required
                                autofocus
                                placeholder="Empresa SAS"
                            />
                            <InputError :message="errors.company_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="admin_name">{{ t('super_admin.companies.field_admin_name') }}</Label>
                            <Input
                                id="admin_name"
                                name="admin_name"
                                type="text"
                                required
                                placeholder="Juan Pérez"
                            />
                            <InputError :message="errors.admin_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="admin_email">{{ t('super_admin.companies.field_admin_email') }}</Label>
                            <Input
                                id="admin_email"
                                name="admin_email"
                                type="email"
                                required
                                placeholder="juan@empresa.com"
                            />
                            <InputError :message="errors.admin_email" />
                        </div>

                        <Button type="submit" class="mt-2 w-full" :disabled="processing">
                            <Spinner v-if="processing" />
                            {{ t('super_admin.companies.create_submit') }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
