<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { index as breakTypesIndex } from '@/routes/break-types';
import { edit as editCompanyProfile } from '@/routes/company-profile';
import { edit as editCompanySettings } from '@/routes/company-settings';
import { index as holidaysIndex } from '@/routes/holidays';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSurchargeRules } from '@/routes/surcharge-rules';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();

const isAdmin = computed(() => {
    const roles: string[] = (page.props.auth as { user: { roles: string[] } }).user.roles ?? [];
    return roles.includes('admin') || roles.includes('super-admin');
});

const sidebarNavItems: NavItem[] = [
    {
        title: t('settings_layout.profile'),
        href: editProfile(),
    },
    {
        title: t('settings_layout.password'),
        href: editPassword(),
    },
    {
        title: t('settings_layout.two_factor_auth'),
        href: show(),
    },
    {
        title: t('settings_layout.appearance'),
        href: editAppearance(),
    },
];

const adminNavItems: NavItem[] = [
    {
        title: t('settings.company_profile'),
        href: editCompanyProfile(),
    },
    {
        title: t('settings.company_settings'),
        href: editCompanySettings(),
    },
    {
        title: t('settings.break_types'),
        href: breakTypesIndex(),
    },
    {
        title: 'Recargos',
        href: editSurchargeRules(),
    },
    {
        title: 'Festivos',
        href: holidaysIndex(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="t('settings_layout.title')"
            :description="t('settings_layout.description')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>

                    <template v-if="isAdmin">
                        <Separator class="my-2" />
                        <Button
                            v-for="item in adminNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'w-full justify-start',
                                { 'bg-muted': isCurrentOrParentUrl(item.href) },
                            ]"
                            as-child
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.title }}
                            </Link>
                        </Button>
                    </template>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
