<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    Activity,
    Car,
    Cpu, 
    FileText, 
    LayoutDashboard, 
    LayoutGrid, 
    Play,
    Settings2,
    Smartphone,
    Users,
    User,
    Package2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

// Type assertion for page.props.auth
interface AuthUser {
    client_id: number | null;
    role: string;
}
interface PageProps {
    auth: {
        user?: AuthUser;
    };
}

const authUser = computed(() => (page.props as unknown as PageProps).auth.user);
const isClient = computed(() => authUser.value?.client_id !== null && authUser.value?.client_id !== undefined);
const isSuperAdmin = computed(() => authUser.value?.role === 'SA');
const clientId = computed(() => authUser.value?.client_id);

// Navigation items organized by role and function
const allNavItems = computed(() => [
    // === MAIN DASHBOARD ITEMS ===
    {
        title: 'Mis Dashboards',
        href: '/dashboard-config',
        icon: LayoutDashboard,
        hasPermission: true,
        allowedForClients: true,
        isForSuperAdmin: true,
    },
    {
        title: 'Telemetría en Vivo',
        href: '/dashboard-dynamic',
        icon: Activity,
        hasPermission: true,
        allowedForClients: true,
        isForSuperAdmin: true,
    },
    {
        title: 'Replays',
        href: '/replays',
        icon: Play,
        hasPermission: true,
        allowedForClients: true,
        isForSuperAdmin: true,
    },
    {
        title: 'Catálogo de Clientes',
        href: '/admin/clients',
        icon: Users,
        hasPermission: true,
        allowedForClients: false, // SA only
        isForSuperAdmin: true,
    },
    {
        title: 'Inventario de Dispositivos',
        href: '/device-inventory',
        icon: Package2,
        hasPermission: true,
        allowedForClients: false, // SA only
        isForSuperAdmin: true,
    },
    {
        title: 'Catálogo de Sensores',
        href: '/admin/sensors',
        icon: Cpu,
        hasPermission: true,
        allowedForClients: false, // SA only
        isForSuperAdmin: true,
    },
    {
        title: 'Catálogo de Vehículos',
        href: '/admin/vehicles',
        icon: Car,
        hasPermission: true,
        allowedForClients: false, // SA only
        isForSuperAdmin: true,
    },
    {
        title: 'Logs del Sistema',
        href: '/log-monitor',
        icon: FileText,
        hasPermission: true,
        allowedForClients: false, // SA only
        isForSuperAdmin: true,
    },
    // === CLIENT ITEMS (dynamically added) ===
    ...(isClient.value
        ? [
              {
                  title: 'Mis Dispositivos',
                  href: `/clients/${clientId.value}/devices`,
                  icon: Smartphone,
                  hasPermission: true,
                  allowedForClients: true,
                  isForSuperAdmin: false,
              },
              {
                  title: 'Mi Perfil',
                  href: `/clients/${clientId.value}`,
                  icon: User,
                  hasPermission: true,
                  allowedForClients: true,
                  isForSuperAdmin: false,
              },
          ]
        : []),
]);

// Filter items based on user role
const mainNavItems = computed(() => {
    if (isClient.value) {
        return allNavItems.value.filter((item) => item.allowedForClients);
    }
    // Super Admin - show all except client-specific profile items
    return allNavItems.value.filter((item) => item.isForSuperAdmin);
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
