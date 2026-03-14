<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CompanyProfileController from '@/actions/App/Http/Controllers/Settings/CompanyProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/company-profile';
import type { BreadcrumbItem } from '@/types';

type CompanyData = {
    name: string;
    logo: string | null;
    country: string;
    timezone: string;
} | null;

const { t } = useI18n();

const props = defineProps<{ company: CompanyData }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.company_profile'),
        href: edit(),
    },
];

const logoPreview = ref<string | null>(props.company?.logo ?? null);
const removeLogo = ref(false);
let blobUrl: string | null = null;

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        if (blobUrl) {
            URL.revokeObjectURL(blobUrl);
        }
        blobUrl = URL.createObjectURL(file);
        logoPreview.value = blobUrl;
        removeLogo.value = false;
    }
}

onUnmounted(() => {
    if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
    }
});

function handleRemoveLogo() {
    logoPreview.value = null;
    removeLogo.value = true;
}

const timezones = [
    'America/Bogota',
    'America/Mexico_City',
    'America/Lima',
    'America/Santiago',
    'America/Buenos_Aires',
    'America/Caracas',
    'America/Guayaquil',
    'America/La_Paz',
    'America/Panama',
    'America/Sao_Paulo',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'UTC',
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.company_profile')" />

        <h1 class="sr-only">{{ t('settings.company_profile') }}</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="t('settings.company_profile')"
                    :description="t('company.name')"
                />

                <template v-if="company">
                    <Form
                        v-bind="CompanyProfileController.update.form()"
                        :options="{ preserveScroll: true, forceFormData: true }"
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">{{ t('company.name') }}</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="company.name"
                                class="max-w-sm"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('company.logo') }}</Label>
                            <div class="flex items-center gap-4">
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    :alt="t('company.logo')"
                                    class="h-16 w-16 rounded-md border object-contain"
                                />
                                <div
                                    v-else
                                    class="bg-muted text-muted-foreground flex h-16 w-16 items-center justify-center rounded-md border text-xs"
                                >
                                    {{ t('company.logo') }}
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/svg+xml"
                                        class="w-64"
                                        @change="onLogoChange"
                                    />
                                    <Button
                                        v-if="logoPreview && !removeLogo"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive w-fit"
                                        @click="handleRemoveLogo"
                                    >
                                        {{ t('company.remove_logo') }}
                                    </Button>
                                </div>
                            </div>
                            <input v-if="removeLogo" type="hidden" name="remove_logo" value="1" />
                            <InputError :message="errors.logo" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="country">{{ t('company.country') }}</Label>
                            <select
                                id="country"
                                name="country"
                                :value="company.country"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full max-w-sm rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="CO">Colombia</option>
                                <option value="MX">Mexico</option>
                                <option value="PE">Peru</option>
                                <option value="CL">Chile</option>
                                <option value="AR">Argentina</option>
                                <option value="EC">Ecuador</option>
                                <option value="VE">Venezuela</option>
                                <option value="PA">Panama</option>
                                <option value="BR">Brasil</option>
                                <option value="US">Estados Unidos</option>
                            </select>
                            <InputError :message="errors.country" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="timezone">{{ t('company.timezone') }}</Label>
                            <select
                                id="timezone"
                                name="timezone"
                                :value="company.timezone"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full max-w-sm rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option v-for="tz in timezones" :key="tz" :value="tz">
                                    {{ tz }}
                                </option>
                            </select>
                            <InputError :message="errors.timezone" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing">
                                {{ processing ? t('common.saving') : t('common.save') }}
                            </Button>
                            <span v-if="recentlySuccessful" class="text-sm text-green-600">{{ t('common.saved') }}</span>
                        </div>
                    </Form>
                </template>

                <p v-else class="text-muted-foreground">
                    {{ t('company.no_company') }}
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
