<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
// TODO: Schedules feature temporarily disabled — restore Sliders import when resuming
import { Building2, CalendarDays, Clock, CreditCard, FileText, LayoutGrid, Settings, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { index as reportsIndex } from '@/actions/App/Http/Controllers/ReportController';
// TODO: Schedules feature temporarily disabled — restore schedulesIndex import when resuming
// import { index as schedulesIndex } from '@/actions/App/Http/Controllers/SchedulesController';
import { index as superAdminCompaniesIndex } from '@/actions/App/Http/Controllers/SuperAdmin/CompanyController';
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
import { index as calendarIndex } from '@/routes/calendar';
import { index as employeesIndex } from '@/routes/employees';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const userRoles = computed(() => (page.props.auth as { user: { roles: string[] } })?.user?.roles ?? []);
const isSuperAdmin = computed(() => userRoles.value.includes('super-admin'));
const isAdmin = computed(() => userRoles.value.includes('admin') || isSuperAdmin.value);

const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) {
        return [
            {
                title: t('nav.companies'),
                href: superAdminCompaniesIndex(),
                icon: Building2,
            },
        ];
    }

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

    if (!isAdmin.value) {
        items.push({
            title: t('nav.time_clock'),
            href: { url: '/time-clock', method: 'get' },
            icon: Clock,
        });
    }

    if (isAdmin.value) {
        items.push(
            // TODO: Schedules feature temporarily disabled — restore this nav item when resuming
            // { title: t('nav.schedules'), href: schedulesIndex(), icon: Sliders },
            {
                title: t('nav.calendar'),
                href: calendarIndex(),
                icon: CalendarDays,
            },
            {
                title: t('nav.reports'),
                href: reportsIndex(),
                icon: FileText,
            },
            // LOCATIONS FEATURE DISABLED — backend model/DB exist but UI is hidden until the feature is complete.
            // Restore this nav item when LocationController and routes are implemented.
            // {
            //     title: t('nav.locations'),
            //     href: { url: '/locations', method: 'get' },
            //     icon: MapPin,
            // },
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
