<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    ClipboardList,
    Calendar,
    FileText,
    ArrowLeftRight,
    MessageSquare,
    Building2,
    CreditCard,
    Send,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import NavUser from '@/components/NavUser.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
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
import type { Partner } from '@/types/partner';

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

const partner = computed(() => (page.props as Record<string, unknown>).partner as Partner | undefined);

const navItems: NavItem[] = [
    { title: 'Dashboard', href: '/partner/dashboard', icon: LayoutDashboard },
    { title: 'Onboarding', href: '/partner/onboarding', icon: ClipboardList },
    { title: 'Sessions', href: '/partner/sessions', icon: Send },
    { title: 'Schedule', href: '/partner/schedule', icon: Calendar },
    { title: 'Invoices', href: '/partner/invoices', icon: FileText },
    { title: 'Change Requests', href: '/partner/change-requests', icon: ArrowLeftRight },
    { title: 'Feedback', href: '/partner/feedback', icon: MessageSquare },
];
</script>

<template>
    <AppShell variant="sidebar">
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child>
                            <Link href="/partner/dashboard">
                                <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                    <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
                                </div>
                                <div class="ml-1 grid flex-1 text-left text-sm">
                                    <span class="mb-0.5 truncate leading-tight font-heading font-semibold">AHAIC</span>
                                    <span class="truncate text-xs text-sidebar-foreground/60">Partner Portal</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <!-- Partner info -->
                <SidebarGroup v-if="partner" class="px-2 py-2">
                    <div class="flex items-center gap-2 rounded-md bg-sidebar-accent/50 px-3 py-2">
                        <Building2 class="size-4 shrink-0 text-sidebar-foreground/70" />
                        <div class="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
                            <p class="truncate text-xs font-medium">{{ partner.organization_name }}</p>
                            <StatusBadge :status="partner.status" type="partner" class="mt-1" />
                        </div>
                    </div>
                </SidebarGroup>

                <!-- Navigation -->
                <SidebarGroup class="px-2 py-0">
                    <SidebarGroupLabel>Navigation</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in navItems" :key="item.title">
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
    </AppShell>
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
