<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard, ClipboardList, Calendar, FileText, ArrowLeftRight,
    MessageSquare, Building2, CreditCard, Send, Users, DollarSign,
    CalendarClock, MapPin, Wrench, BookOpen, Radio, BarChart3, Settings, Globe
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import type { NavItem } from '@/types';

const page = usePage();
const userRole = computed(() => page.props.userRole as string | null);

const partnerNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/partner/dashboard', icon: LayoutDashboard },
    { title: 'Onboarding', href: '/partner/onboarding', icon: ClipboardList },
    { title: 'Sessions', href: '/partner/sessions', icon: Calendar },
    { title: 'Invoices', href: '/partner/invoices', icon: FileText },
    { title: 'Schedule', href: '/partner/schedule', icon: CalendarClock },
    { title: 'Change Requests', href: '/partner/change-requests', icon: ArrowLeftRight },
    { title: 'Feedback', href: '/partner/feedback', icon: MessageSquare },
];

const adminNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
    { title: 'Partners', href: '/admin/partners', icon: Users },
    { title: 'Finance', href: '/admin/finance/payments', icon: DollarSign },
    { title: 'Scheduling', href: '/admin/scheduling', icon: CalendarClock },
    { title: 'Rooms', href: '/admin/rooms', icon: MapPin },
    { title: 'Resources', href: '/admin/resources', icon: Wrench },
    { title: 'Agenda', href: '/admin/agenda', icon: BookOpen },
    { title: 'Change Requests', href: '/admin/change-requests', icon: ArrowLeftRight },
    { title: 'Conferences', href: '/admin/conferences', icon: Globe },
    { title: 'Finalization', href: '/admin/finalization', icon: Send },
    { title: 'Live Dashboard', href: '/admin/live', icon: Radio },
    { title: 'Reports', href: '/admin/reports', icon: BarChart3 },
];

const navItems = computed(() => {
    if (userRole.value === 'partner') {
return partnerNavItems;
}

    return adminNavItems;
});

const dashboardHref = computed(() => {
    if (userRole.value === 'partner') {
return '/partner/dashboard';
}

    return '/admin/dashboard';
});

const footerNavItems: NavItem[] = [
    { title: 'Packages', href: '/packages', icon: Building2 },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="navItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
