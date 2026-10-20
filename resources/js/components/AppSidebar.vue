<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Calendar, DoorOpen, Dumbbell, LayoutGrid, Users } from '@lucide/vue';
import { computed } from 'vue';
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
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth?.user as Record<string, any> | undefined);
const isAdmin = computed(() => user.value?.role === 'admin');
const isStaff = computed(() => user.value?.role === 'admin' || user.value?.role === 'front_desk');
const isTrainer = computed(() => user.value?.role === 'admin' || user.value?.role === 'trainer');

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (isAdmin.value) {
        items.push({
            title: 'Executive BI',
            href: '/executive',
            icon: BarChart3,
        });
    }

    if (isStaff.value) {
        items.push({
            title: 'Gate & Check-in',
            href: '/gate',
            icon: DoorOpen,
        });
        items.push({
            title: 'Members',
            href: '/members',
            icon: Users,
        });
    }

    if (isTrainer.value) {
        items.push({
            title: 'Portal Trainer',
            href: '/trainer',
            icon: Dumbbell,
        });
    }

    items.push({
        title: 'Kelas Studio',
        href: '/classes',
        icon: Calendar,
    });

    return items;
});
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
