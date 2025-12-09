<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Cpu, FileText, LayoutGrid, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

// Type assertion for page.props.auth
interface AuthUser {
    client_id: number | null;
    // add other user properties if needed
}
interface PageProps {
    auth: {
        user?: AuthUser;
        // add other auth properties if needed
    };
    // add other props if needed
}

const isClient = computed(() => {
    const user = (page.props as unknown as PageProps).auth.user;
    return user?.client_id !== null && user?.client_id !== undefined;
});

const clientId = computed(() => (page.props as unknown as PageProps).auth.user?.client_id);

// Hacer allNavItems computed para que la URL sea dinámica
const allNavItems = computed(() => [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
        hasPermission: true,
        allowedForClients: true,
    },
    {
        title: 'inventario de dispositivos',
        href: '/device-inventory',
        icon: Cpu,
        hasPermission: true,
        allowedForClients: false,
    },
    {
        title: 'Clients',
        href: '/clients',
        icon: Users,
        hasPermission: true,
        allowedForClients: false,
    },
    {
        title: 'Replays',
        href: '/replays',
        icon: Users,
        hasPermission: true,
        allowedForClients: false,
    },
    {
        title: 'Logs',
        href: '/log-monitor',
        icon: FileText,
        hasPermission: true,
        allowedForClients: false,
    },
    ...(isClient.value
        ? [
              {
                  title: 'My devices',
                  href: `/clients/${clientId.value}/devices`,
                  icon: Cpu,
                  hasPermission: true,
                  allowedForClients: true,
              },
              {
                  title: 'My profile',
                  href: `/clients/${clientId.value}`,
                  icon: Users,
                  hasPermission: true,
                  allowedForClients: true,
              },
          ]
        : []),
]);

// Filtrar items según si es cliente o no
const mainNavItems = computed(() => {
    if (isClient.value) {
        // Si es cliente, solo mostrar items permitidos para clientes
        return allNavItems.value.filter((item) => item.allowedForClients);
    }
    // Si no es cliente, mostrar todos (ya excluimos My devices con el spread condicional)
    return allNavItems.value.filter((item) => item.allowedForClients === false);
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
