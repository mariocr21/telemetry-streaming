<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client, ClientDevice, User as UserType } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js'; // Agregar esta importación
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Building,
    Calendar,
    Car,
    CheckCircle2,
    Clock,
    Copy,
    Edit,
    ExternalLink,
    Eye,
    Hash,
    Mail,
    MapPin,
    MoreVertical,
    Phone,
    Shield,
    Smartphone,
    Trash,
    Trash2,
    User,
    UserPlus,
    Users,
    Wifi,
    WifiOff,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ClientCreatedModal from './ClientCreatedModal.vue';

interface Props {
    client: Client & {
        users: UserType[];
        devices: ClientDevice[];
        can: {
            view: boolean;
            update: boolean;
            delete: boolean;
        };
    };
}

const props = defineProps<Props>();
const page = usePage();

// Estado reactivo
const copied = ref('');
const showCredentialsModal = ref(false);
const userCredentials = ref<any>(null);

// ================ FUNCIONES DE CLIENTE ================

const deleteClient = () => {
    if (confirm(`¿Estás seguro de que deseas eliminar al cliente ${props.client.full_name}?`)) {
        router.delete(route('clients.destroy', props.client.id), {
            onSuccess: () => {
                router.visit(route('clients.index'));
            },
        });
    }
};

const getFullAddress = () => {
    const parts = [props.client.address, props.client.city, props.client.state, props.client.zip_code, props.client.country].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : null;
};

const copyToClipboard = async (text: string, type: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = type;
        setTimeout(() => {
            copied.value = '';
        }, 2000);
    } catch (err) {
        console.error('Error al copiar:', err);
    }
};

// ================ FUNCIONES DE USUARIOS ================

const deleteUser = (user: UserType) => {
    if (confirm(`¿Estás seguro de que deseas eliminar al usuario ${user.name}?`)) {
        router.delete(route('users.destroy', user.id), {
            onSuccess: () => {
                router.reload();
            },
        });
    }
};

const getRoleBadgeColor = (role: string) => {
    switch (role) {
        case 'SA':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'CA':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        default:
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
};

// ================ FUNCIONES DE DISPOSITIVOS ================

const getDeviceStatusBadge = (status: string) => {
    const badges = {
        pending: { text: 'Pendiente', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
        active: { text: 'Activo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
        inactive: { text: 'Inactivo', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
        maintenance: { text: 'Mantenimiento', class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
        retired: { text: 'Retirado', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' },
    };
    return badges[status as keyof typeof badges] || badges.pending;
};

// ================ COMPUTADAS ================

const usersStats = computed(() => {
    const users = props.client.users || [];
    return {
        total: users.length,
        active: users.filter((u) => u.is_active).length,
        admins: users.filter((u) => u.role === 'SA' || u.role === 'CA').length,
        regularUsers: users.filter((u) => u.role === 'CU').length,
    };
});

const devicesStats = computed(() => {
    const devices = props.client.devices || [];
    return {
        total: devices.length,
        active: devices.filter((d) => d.status === 'active').length,
        pending: devices.filter((d) => d.status === 'pending').length,
        withVehicle: devices.filter((d) => d.vehicle).length,
    };
});

const clientAge = computed(() => {
    const createdDate = new Date(props.client.created_at);
    const now = new Date();
    const diffTime = Math.abs(now.getTime() - createdDate.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 30) {
        return `${diffDays} días`;
    } else if (diffDays < 365) {
        const months = Math.floor(diffDays / 30);
        return `${months} ${months === 1 ? 'mes' : 'meses'}`;
    } else {
        const years = Math.floor(diffDays / 365);
        return `${years} ${years === 1 ? 'año' : 'años'}`;
    }
});

const lastUpdated = computed(() => {
    const updatedDate = new Date(props.client.updated_at);
    const now = new Date();
    const diffTime = Math.abs(now.getTime() - updatedDate.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Hoy';
    } else if (diffDays === 1) {
        return 'Ayer';
    } else if (diffDays < 7) {
        return `Hace ${diffDays} días`;
    } else {
        return updatedDate.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }
});

const flashMessage = computed(() => {
    const flash = page.props.flash as any;
    return flash?.message;
});

const flashSuccess = computed(() => {
    const flash = page.props.flash as any;
    return flash?.success;
});

// Manejar credenciales del flash
if (flashSuccess.value?.user_created) {
    userCredentials.value = {
        userEmail: flashSuccess.value.user_email,
        userPassword: flashSuccess.value.user_password,
        userName: flashSuccess.value.user_name,
        userRole: flashSuccess.value.user_role,
        userRoleLabel: flashSuccess.value.user_role_label,
    };
    showCredentialsModal.value = true;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clients' },
    { title: props.client.full_name, href: `/clients/${props.client.id}` },
];
</script>

<template>
    <Head :title="`${client.full_name} - Detalles`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <template #header>
            <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                <div class="flex items-center space-x-4">
                    <Link :href="route('clients.index')">
                        <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Volver a Clientes
                        </Button>
                    </Link>

                    <div class="flex items-center space-x-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-blue-600 shadow-lg">
                            <span class="text-2xl font-bold text-white">
                                {{ client.first_name.charAt(0).toUpperCase() }}{{ client.last_name.charAt(0).toUpperCase() }}
                            </span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ client.full_name }}
                            </h1>
                            <div class="mt-2 flex items-center space-x-4">
                                <span class="text-sm text-gray-500 dark:text-gray-400"> Cliente desde {{ clientAge }} </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400"> • Actualizado {{ lastUpdated }} </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link v-if="client.can?.update" :href="route('clients.edit', client.id)">
                        <Button variant="outline" size="sm">
                            <Edit class="mr-2 h-4 w-4" />
                            Editar Cliente
                        </Button>
                    </Link>

                    <SimpleDropdown align="right">
                        <template #trigger>
                            <Button variant="outline" size="sm">
                                <MoreVertical class="h-4 w-4" />
                            </Button>
                        </template>

                        <Link
                            v-if="client.can?.update"
                            :href="route('clients.edit', client.id)"
                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <Edit class="mr-2 h-4 w-4" />
                            Editar Cliente
                        </Link>

                        <button
                            v-if="client.email"
                            @click="copyToClipboard(client.email, 'email')"
                            class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <Copy class="mr-2 h-4 w-4" />
                            Copiar Email
                        </button>

                        <div class="border-t border-gray-100 dark:border-gray-700"></div>

                        <button
                            v-if="client.can?.delete"
                            @click="deleteClient"
                            class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Eliminar Cliente
                        </button>
                    </SimpleDropdown>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Mensajes Flash -->
                <div
                    v-if="flashMessage || flashSuccess?.message"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-800 dark:bg-green-900/20"
                >
                    <div class="flex items-center">
                        <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-green-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                {{ flashMessage || flashSuccess?.message }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Notificación de copiado -->
                <div v-if="copied" class="rounded-lg border border-blue-200 bg-blue-50 p-4 shadow-sm dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex items-center">
                        <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-blue-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                {{
                                    copied === 'email'
                                        ? 'Email copiado al portapapeles'
                                        : copied === 'phone'
                                          ? 'Teléfono copiado al portapapeles'
                                          : 'Información copiada'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Dashboard de estadísticas -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Información Personal -->
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/50">
                                    <User class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Cliente</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Activo</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Usuarios -->
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/50">
                                    <Users class="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Usuarios</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ usersStats.total }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Dispositivos -->
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-900/50">
                                    <Smartphone class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Dispositivos</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ devicesStats.total }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Vehículos -->
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-orange-50 p-3 dark:bg-orange-900/50">
                                    <Car class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Vehículos</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ devicesStats.withVehicle }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Contenido principal -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Columna principal -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Información Personal -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center text-lg">
                                    <User class="mr-2 h-5 w-5 text-blue-600" />
                                    Información Personal
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div class="space-y-4">
                                        <div>
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Nombre Completo</h4>
                                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {{ client.full_name }}
                                            </p>
                                        </div>

                                        <div v-if="client.job_title">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Cargo</h4>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ client.job_title }}</p>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-if="client.email">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Correo Electrónico</h4>
                                            <div class="flex items-center space-x-2">
                                                <Mail class="h-4 w-4 text-gray-400" />
                                                <a
                                                    :href="`mailto:${client.email}`"
                                                    class="flex items-center space-x-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                >
                                                    <span>{{ client.email }}</span>
                                                    <ExternalLink class="h-3 w-3" />
                                                </a>
                                                <button
                                                    @click="copyToClipboard(client.email, 'email')"
                                                    class="rounded p-1 text-gray-400 hover:text-gray-600"
                                                    title="Copiar email"
                                                >
                                                    <Copy class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>

                                        <div v-if="client.phone">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Teléfono</h4>
                                            <div class="flex items-center space-x-2">
                                                <Phone class="h-4 w-4 text-gray-400" />
                                                <a
                                                    :href="`tel:${client.phone}`"
                                                    class="flex items-center space-x-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                >
                                                    <span>{{ client.phone }}</span>
                                                    <ExternalLink class="h-3 w-3" />
                                                </a>
                                                <button
                                                    @click="copyToClipboard(client.phone, 'phone')"
                                                    class="rounded p-1 text-gray-400 hover:text-gray-600"
                                                    title="Copiar teléfono"
                                                >
                                                    <Copy class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empresa -->
                                <div v-if="client.company" class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                                    <div class="mb-2 flex items-center space-x-2">
                                        <Building class="h-4 w-4 text-gray-400" />
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Empresa</h4>
                                    </div>
                                    <Badge variant="secondary" class="px-3 py-1 text-base">
                                        {{ client.company }}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Dirección -->
                        <Card v-if="getFullAddress()">
                            <CardHeader>
                                <CardTitle class="flex items-center text-lg">
                                    <MapPin class="mr-2 h-5 w-5 text-red-600" />
                                    Dirección
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div v-if="client.address" class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <p class="text-gray-900 dark:text-gray-100">{{ client.address }}</p>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div v-if="client.city">
                                            <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Ciudad</h4>
                                            <p class="font-medium">{{ client.city }}</p>
                                        </div>
                                        <div v-if="client.state">
                                            <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Estado</h4>
                                            <p class="font-medium">{{ client.state }}</p>
                                        </div>
                                        <div v-if="client.zip_code">
                                            <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">C.P.</h4>
                                            <p class="font-medium">{{ client.zip_code }}</p>
                                        </div>
                                    </div>

                                    <div v-if="client.country" class="border-t border-gray-200 pt-2 dark:border-gray-700">
                                        <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">País</h4>
                                        <p class="font-medium">{{ client.country }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Dispositivos -->
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between">
                                    <CardTitle class="flex items-center text-lg">
                                        <Smartphone class="mr-2 h-5 w-5 text-purple-600" />
                                        Dispositivos ({{ devicesStats.total }})
                                    </CardTitle>
                                    <div class="flex space-x-2">
                                        <Link :href="route('clients.devices.index', client.id)">
                                            <Button size="sm" variant="outline">
                                                <Eye class="mr-2 h-4 w-4" />
                                                Ver Todos
                                            </Button>
                                        </Link>
                                        <Link :href="route('clients.devices.create', client.id)">
                                            <Button size="sm">
                                                <Smartphone class="mr-2 h-4 w-4" />
                                                Agregar
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="client.devices && client.devices.length > 0">
                                    <!-- Estadísticas -->
                                    <div class="mb-6 grid grid-cols-4 gap-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-purple-600">{{ devicesStats.total }}</div>
                                            <div class="text-xs text-gray-500">Total</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-green-600">{{ devicesStats.active }}</div>
                                            <div class="text-xs text-gray-500">Activos</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-orange-600">{{ devicesStats.pending }}</div>
                                            <div class="text-xs text-gray-500">Pendientes</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-blue-600">{{ devicesStats.withVehicle }}</div>
                                            <div class="text-xs text-gray-500">Con Auto</div>
                                        </div>
                                    </div>

                                    <!-- Lista de dispositivos -->
                                    <div class="space-y-3">
                                        <div
                                            v-for="device in client.devices.slice(0, 3)"
                                            :key="device.id"
                                            class="flex items-center justify-between rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                        >
                                            <div class="flex items-center space-x-4">
                                                <div
                                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-400 to-purple-600"
                                                >
                                                    <Smartphone class="h-5 w-5 text-white" />
                                                </div>

                                                <div>
                                                    <Link
                                                        :href="route('clients.devices.show', [client.id, device.id])"
                                                        class="font-medium text-gray-900 transition-colors hover:text-purple-600 dark:text-gray-100"
                                                    >
                                                        {{ device.device_name }}
                                                    </Link>
                                                    <div class="mt-1 flex items-center space-x-4">
                                                        <div class="flex items-center space-x-1">
                                                            <Hash class="h-3 w-3 text-gray-400" />
                                                            <span class="font-mono text-sm text-gray-500">{{ device.mac_address }}</span>
                                                        </div>
                                                        <Badge :class="getDeviceStatusBadge(device.status).class" class="text-xs">
                                                            {{ getDeviceStatusBadge(device.status).text }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <div v-if="device.last_ping" class="text-green-500" title="Conectado">
                                                    <Wifi class="h-4 w-4" />
                                                </div>
                                                <div v-else class="text-gray-400" title="Desconectado">
                                                    <WifiOff class="h-4 w-4" />
                                                </div>

                                                <!-- Quick Dashboard Access if device has active vehicle -->
                                                <Link
                                                    v-if="device.vehicle?.id"
                                                    :href="`/dashboard-dynamic/${device.vehicle.id}`"
                                                    class="rounded p-1 text-cyan-500 hover:text-cyan-600 hover:bg-cyan-50 dark:hover:bg-cyan-900/20"
                                                    title="Ver Dashboard en Vivo"
                                                >
                                                    <Activity class="h-4 w-4" />
                                                </Link>

                                                <Link
                                                    :href="route('clients.devices.show', [client.id, device.id])"
                                                    class="rounded p-1 text-gray-400 hover:text-purple-600"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ver más -->
                                    <div v-if="client.devices.length > 3" class="mt-4 text-center">
                                        <Link :href="route('clients.devices.index', client.id)">
                                            <Button variant="outline" size="sm"> Ver {{ client.devices.length - 3 }} dispositivos más </Button>
                                        </Link>
                                    </div>
                                </div>

                                <!-- Estado vacío dispositivos -->
                                <div v-else class="py-8 text-center">
                                    <Smartphone class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                    <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-gray-100">No hay dispositivos</h3>
                                    <p class="mb-6 text-gray-600 dark:text-gray-400">
                                        Este cliente aún no tiene dispositivos registrados. Agrega el primer dispositivo para comenzar el seguimiento.
                                    </p>
                                    <Link :href="route('clients.devices.create', client.id)">
                                        <Button>
                                            <Smartphone class="mr-2 h-4 w-4" />
                                            Registrar Primer Dispositivo
                                        </Button>
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Usuarios -->
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between">
                                    <CardTitle class="flex items-center text-lg">
                                        <Users class="mr-2 h-5 w-5 text-green-600" />
                                        Usuarios ({{ usersStats.total }})
                                    </CardTitle>
                                    <Button size="sm" @click="router.visit(route('users.create', client.id))">
                                        <UserPlus class="mr-2 h-4 w-4" />
                                        Agregar
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="client.users && client.users.length > 0">
                                    <!-- Estadísticas usuarios -->
                                    <div class="mb-6 grid grid-cols-3 gap-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-green-600">{{ usersStats.active }}</div>
                                            <div class="text-xs text-gray-500">Activos</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-blue-600">{{ usersStats.admins }}</div>
                                            <div class="text-xs text-gray-500">Admins</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-gray-600">{{ usersStats.regularUsers }}</div>
                                            <div class="text-xs text-gray-500">Usuarios</div>
                                        </div>
                                    </div>

                                    <!-- Lista de usuarios -->
                                    <div class="space-y-3">
                                        <div
                                            v-for="user in client.users"
                                            :key="user.id"
                                            class="flex items-center justify-between rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                        >
                                            <div class="flex items-center space-x-4">
                                                <div
                                                    :class="[
                                                        'flex h-10 w-10 items-center justify-center rounded-full',
                                                        user.role === 'SA'
                                                            ? 'bg-red-100 dark:bg-red-900'
                                                            : user.role === 'CA'
                                                              ? 'bg-blue-100 dark:bg-blue-900'
                                                              : 'bg-green-100 dark:bg-green-900',
                                                    ]"
                                                >
                                                    <span
                                                        :class="[
                                                            'text-sm font-medium',
                                                            user.role === 'SA'
                                                                ? 'text-red-600 dark:text-red-400'
                                                                : user.role === 'CA'
                                                                  ? 'text-blue-600 dark:text-blue-400'
                                                                  : 'text-green-600 dark:text-green-400',
                                                        ]"
                                                    >
                                                        {{ user.name.charAt(0).toUpperCase() }}
                                                    </span>
                                                </div>

                                                <div>
                                                    <div class="flex items-center space-x-2">
                                                        <p class="font-medium text-gray-900 dark:text-gray-100">
                                                            {{ user.name }}
                                                        </p>
                                                        <div v-if="!user.is_active">
                                                            <Badge variant="destructive" class="text-xs">Inactivo</Badge>
                                                        </div>
                                                    </div>
                                                    <div class="mt-1 flex items-center space-x-2">
                                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                                            {{ user.email }}
                                                        </p>
                                                        <div class="flex items-center space-x-1">
                                                            <Shield class="h-3 w-3 text-gray-400" />
                                                            <span
                                                                :class="[
                                                                    'rounded-full px-2 py-1 text-xs font-medium',
                                                                    getRoleBadgeColor(user.role ?? 'CU'),
                                                                ]"
                                                            >
                                                                {{ user.role_label }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <SimpleDropdown align="right">
                                                <template #trigger>
                                                    <Button size="sm" variant="ghost">
                                                        <MoreVertical class="h-4 w-4" />
                                                    </Button>
                                                </template>

                                                <button
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <Edit class="mr-2 h-4 w-4" />
                                                    Editar Usuario
                                                </button>

                                                <button
                                                    v-if="user.is_active"
                                                    @click="router.patch(route('users.toggle-status', user.id))"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20"
                                                >
                                                    <AlertCircle class="mr-2 h-4 w-4" />
                                                    Desactivar
                                                </button>

                                                <button
                                                    v-else
                                                    @click="router.patch(route('users.toggle-status', user.id))"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
                                                >
                                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                                    Activar
                                                </button>

                                                <button
                                                    @click="copyToClipboard(user.email, 'user-email')"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <Copy class="mr-2 h-4 w-4" />
                                                    Copiar Email
                                                </button>

                                                <div class="border-t border-gray-100 dark:border-gray-700"></div>

                                                <button
                                                    @click="deleteUser(user)"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash class="mr-2 h-4 w-4" />
                                                    Eliminar Usuario
                                                </button>
                                            </SimpleDropdown>
                                        </div>
                                    </div>
                                </div>

                                <!-- Estado vacío usuarios -->
                                <div v-else class="py-8 text-center">
                                    <UserPlus class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                    <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-gray-100">No hay usuarios</h3>
                                    <p class="mb-6 text-gray-600 dark:text-gray-400">
                                        Este cliente no tiene usuarios de acceso al sistema. Crea el primer usuario para darle acceso.
                                    </p>
                                    <Button @click="router.visit(route('users.create', client.id))">
                                        <UserPlus class="mr-2 h-4 w-4" />
                                        Crear Primer Usuario
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Acciones Rápidas -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Acciones Rápidas</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <Link v-if="client.can?.update" :href="route('clients.edit', client.id)">
                                    <Button class="w-full justify-start" variant="outline">
                                        <Edit class="mr-2 h-4 w-4" />
                                        Editar Cliente
                                    </Button>
                                </Link>

                                <Link :href="route('clients.devices.index', client.id)">
                                    <Button class="w-full justify-start" variant="outline">
                                        <Smartphone class="mr-2 h-4 w-4" />
                                        Gestionar Dispositivos
                                    </Button>
                                </Link>

                                <Button
                                    v-if="client.email"
                                    @click="copyToClipboard(client.email, 'email')"
                                    class="w-full justify-start"
                                    variant="outline"
                                >
                                    <Mail class="mr-2 h-4 w-4" />
                                    Copiar Email
                                </Button>

                                <Button
                                    v-if="client.phone"
                                    @click="copyToClipboard(client.phone, 'phone')"
                                    class="w-full justify-start"
                                    variant="outline"
                                >
                                    <Phone class="mr-2 h-4 w-4" />
                                    Copiar Teléfono
                                </Button>

                                <div v-if="client.can?.delete" class="border-t border-gray-200 pt-3 dark:border-gray-700">
                                    <Button
                                        @click="deleteClient"
                                        class="w-full justify-start text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                                        variant="outline"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Eliminar Cliente
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Información del Sistema -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center text-lg">
                                    <Clock class="mr-2 h-5 w-5 text-gray-600" />
                                    Información del Sistema
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">ID del Cliente</h4>
                                    <p class="rounded bg-gray-100 px-2 py-1 font-mono text-sm dark:bg-gray-800">#{{ client.id }}</p>
                                </div>

                                <div>
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Registro</h4>
                                    <div class="flex items-center space-x-2">
                                        <Calendar class="h-4 w-4 text-gray-400" />
                                        <div>
                                            <p class="font-medium">
                                                {{
                                                    new Date(client.created_at).toLocaleDateString('es-ES', {
                                                        weekday: 'long',
                                                        year: 'numeric',
                                                        month: 'long',
                                                        day: 'numeric',
                                                    })
                                                }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{
                                                    new Date(client.created_at).toLocaleTimeString('es-ES', {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Última Actualización</h4>
                                    <div class="flex items-center space-x-2">
                                        <Clock class="h-4 w-4 text-gray-400" />
                                        <div>
                                            <p class="font-medium">{{ lastUpdated }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{
                                                    new Date(client.updated_at).toLocaleTimeString('es-ES', {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-500">Tiempo como cliente</span>
                                        <Badge variant="secondary">{{ clientAge }}</Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Completitud del Perfil -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Completitud del Perfil</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Información básica</span>
                                        <CheckCircle2 class="h-4 w-4 text-green-500" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Contacto</span>
                                        <CheckCircle2 v-if="client.email" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-amber-500" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Dirección</span>
                                        <CheckCircle2 v-if="getFullAddress()" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Información profesional</span>
                                        <CheckCircle2 v-if="client.company || client.job_title" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Dispositivos</span>
                                        <CheckCircle2 v-if="devicesStats.total > 0" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Usuarios de acceso</span>
                                        <CheckCircle2 v-if="usersStats.total > 0" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Credenciales -->
        <ClientCreatedModal
            v-if="userCredentials"
            :is-open="showCredentialsModal"
            :user-email="userCredentials.userEmail"
            :user-password="userCredentials.userPassword"
            :user-name="userCredentials.userName"
            :user-role="userCredentials.userRole"
            :user-role-label="userCredentials.userRoleLabel"
            @close="showCredentialsModal = false"
        />
    </AppLayout>
</template>