<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Users,
    CreditCard,
    CalendarDays,
    DoorOpen,
    Wrench,
    AlertTriangle,
    BookOpen,
    Send,
    ArrowLeftRight,
    Settings,
    Package,
    BarChart3,
    Radio,
    ClipboardCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import NavUser from '@/components/NavUser.vue';
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
    SidebarMenuButton,
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useFlashMessages } from '@/composables/useFlashMessages';
import type { BreadcrumbItem, NavItem } from '@/types';

// Surfaces success/error/info flashes from every admin controller action.
useFlashMessages();

withDefaults(
    defineProps<{
        title?: string;
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        title: '',
        breadcrumbs: () => [],
    },
);

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const user = computed(() => (page.props as Record<string, unknown>).auth as { user: { name: string; role?: string } } | undefined);
const userRole = computed(() => user.value?.user?.role ?? 'admin');

/** Items may declare `roles`; without it an item is visible to every staff role. */
interface NavGroup {
    label: string;
    items: (NavItem & { roles?: string[] })[];
}

const allNavGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
        ],
    },
    {
        label: 'People',
        items: [
            { title: 'Users', href: '/admin/users', icon: Users },
            { title: 'Partner List', href: '/admin/partners', icon: Users },
        ],
    },
    {
        label: 'Finance',
        items: [
            { title: 'Payments', href: '/admin/finance/payments', icon: CreditCard },
        ],
    },
    {
        label: 'Scheduling',
        items: [
            { title: 'Board', href: '/admin/scheduling', icon: CalendarDays },
            { title: 'Rooms', href: '/admin/rooms', icon: DoorOpen },
            { title: 'Booths', href: '/admin/booths', icon: Package },
            { title: 'Resources', href: '/admin/resources', icon: Wrench },
            { title: 'Conflicts', href: '/admin/scheduling/conflicts', icon: AlertTriangle },
        ],
    },
    {
        label: 'Agenda',
        items: [
            { title: 'Master', href: '/admin/agenda/master', icon: BookOpen },
            { title: 'Published', href: '/admin/agenda/published', icon: Send },
        ],
    },
    {
        label: 'Management',
        items: [
            {
                title: 'Session Review',
                href: '/admin/sessions',
                icon: ClipboardCheck,
                roles: ['super_admin', 'admin', 'partnerships'],
            },
            {
                title: 'Change Requests',
                href: '/admin/change-requests',
                icon: ArrowLeftRight,
                roles: ['super_admin', 'admin', 'partnerships', 'programme', 'pco'],
            },
        ],
    },
    {
        label: 'Conference',
        items: [
            { title: 'Settings', href: '/admin/conference/settings', icon: Settings },
            { title: 'Packages', href: '/admin/conference/packages', icon: Package },
        ],
    },
    {
        label: 'Analytics',
        items: [
            { title: 'Reports', href: '/admin/reports', icon: BarChart3 },
            { title: 'Live Dashboard', href: '/admin/live', icon: Radio },
        ],
    },
];

const navGroups = computed(() =>
    allNavGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.roles || item.roles.includes(userRole.value)),
        }))
        .filter((group) => group.items.length > 0),
);
</script>

<template>
    <AppShell variant="sidebar">
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child>
                            <Link href="/admin/dashboard">
                                <AppLogoIcon class="aspect-square size-8 shrink-0" />
                                <div class="ml-1 grid flex-1 text-left text-sm">
                                    <span class="mb-0.5 truncate leading-tight font-heading font-semibold">AHAIC</span>
                                    <span class="truncate text-xs text-sidebar-foreground/60">Admin Panel</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <!-- Role badge -->
                <div class="flex items-center justify-center px-2 py-1 group-data-[collapsible=icon]:hidden">
                    <Badge variant="secondary" class="text-xs capitalize">
                        {{ userRole }}
                    </Badge>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup v-for="group in navGroups" :key="group.label" class="px-2 py-0">
                    <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in group.items" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentOrParentUrl(item.href)"
                                :tooltip="item.title"
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>

        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
                <div v-if="title" class="flex items-center justify-between">
                    <h1 class="font-heading text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <slot name="actions" />
                </div>
                <slot />
            </div>
        </AppContent>
        <Toaster position="top-right" :duration="5000" rich-colors close-button />
    </AppShell>
</template>
