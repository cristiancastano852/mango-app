<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Clock, FileText, LayoutGrid, MapPin, Settings, CreditCard, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as employeesIndex } from '@/routes/employees';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const userRoles = computed(() => (page.props.auth as { user: { roles: string[] } })?.user?.roles ?? []);
const isAdmin = computed(() => userRoles.value.includes('admin') || userRoles.value.includes('super-admin'));

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (isAdmin.value) {
        items.push({
            title: t('nav.employees'),
            href: employeesIndex(),
            icon: Users,
        });
    }

    items.push({
        title: t('nav.time_clock'),
        href: { url: '/time-clock', method: 'get' },
        icon: Clock,
    });

    if (isAdmin.value) {
        items.push(
            {
                title: t('nav.reports'),
                href: { url: '/reports', method: 'get' },
                icon: FileText,
            },
            {
                title: t('nav.locations'),
                href: { url: '/locations', method: 'get' },
                icon: MapPin,
            },
        );
    }

    return items;
});

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.settings'),
        href: { url: '/settings/profile', method: 'get' },
        icon: Settings,
    },
    {
        title: t('nav.billing'),
        href: { url: '/billing', method: 'get' },
        icon: CreditCard,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavMain :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
