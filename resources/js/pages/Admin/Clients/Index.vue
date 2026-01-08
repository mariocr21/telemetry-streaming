<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Badge from '@/components/ui/Badge.vue';
import Table from '@/components/ui/Table.vue';
import TableBody from '@/components/ui/TableBody.vue';
import TableCell from '@/components/ui/TableCell.vue';
import TableHead from '@/components/ui/TableHead.vue';
import TableHeader from '@/components/ui/TableHeader.vue';
import TableRow from '@/components/ui/TableRow.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue';
import {
    MoreVertical,
    Search,
    Eye,
    Edit,
    Trash2,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    RefreshCw,
    X,
    Users,
    Smartphone,
    Car,
    Building2,
    CheckCircle2,
    XCircle,
    Plus,
    Mail,
    Phone,
    MapPin,
    Copy,
    UserPlus,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Client {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    phone: string | null;
    company: string | null;
    city: string | null;
    country: string | null;
    devices_count: number;
    users_count: number;
    vehicles_count: number;
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    links: any[];
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

interface Filters {
    search: string | null;
    sort: string;
    direction: string;
}

interface Stats {
    total: number;
    with_devices: number;
    with_users: number;
    recent: number;
}

interface Props {
    clients: PaginatedData<Client>;
    filters: Filters;
    stats: Stats;
}

const props = defineProps<Props>();
const page = usePage();

// State
const searchInput = ref(props.filters.search || '');
const sort = ref(props.filters.sort || 'created_at');
const direction = ref(props.filters.direction || 'desc');
const isLoading = ref(false);

// Create client modal state
const showCreateModal = ref(false);
const createForm = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    country: '',
    job_title: '',
    create_user: true,
});
const createErrors = ref<Record<string, string>>({});
const isCreating = ref(false);

// Edit client modal state
const showEditModal = ref(false);
const editingClient = ref<Client | null>(null);
const editForm = ref({ ...createForm.value });
const editErrors = ref<Record<string, string>>({});
const isUpdating = ref(false);

// Password display state
const showPasswordModal = ref(false);
const generatedPassword = ref('');
const passwordCopied = ref(false);

// Search debounce
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchInput, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch();
    }, 300);
});

const performSearch = () => {
    if (isLoading.value) return;

    isLoading.value = true;
    router.get(
        route('admin.clients.index'),
        {
            search: searchInput.value || undefined,
            sort: sort.value,
            direction: direction.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        }
    );
};

const clearFilters = () => {
    searchInput.value = '';
    performSearch();
};

const refreshData = () => {
    performSearch();
};

const sortBy = (column: string) => {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    performSearch();
};

const getSortIcon = (column: string) => {
    if (sort.value !== column) return ArrowUpDown;
    return direction.value === 'asc' ? ArrowUp : ArrowDown;
};

// Create modal functions
const openCreateModal = () => {
    showCreateModal.value = true;
    createForm.value = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        company: '',
        address: '',
        city: '',
        state: '',
        zip_code: '',
        country: '',
        job_title: '',
        create_user: true,
    };
    createErrors.value = {};
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const submitCreateClient = () => {
    createErrors.value = {};
    
    if (!createForm.value.first_name) {
        createErrors.value.first_name = 'El nombre es obligatorio';
        return;
    }
    if (!createForm.value.last_name) {
        createErrors.value.last_name = 'El apellido es obligatorio';
        return;
    }
    if (!createForm.value.email) {
        createErrors.value.email = 'El email es obligatorio';
        return;
    }
    
    isCreating.value = true;
    
    router.post(
        route('admin.clients.store'),
        createForm.value,
        {
            preserveScroll: true,
            onSuccess: (page) => {
                closeCreateModal();
                // Check if password was generated
                const flash = page.props.flash as any;
                if (flash?.user_created && flash?.user_password) {
                    generatedPassword.value = flash.user_password;
                    showPasswordModal.value = true;
                }
            },
            onError: (errors) => {
                createErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                isCreating.value = false;
            },
        }
    );
};

// Edit modal functions
const openEditModal = (client: Client) => {
    editingClient.value = client;
    editForm.value = {
        first_name: client.first_name,
        last_name: client.last_name,
        email: client.email,
        phone: client.phone || '',
        company: client.company || '',
        address: '',
        city: client.city || '',
        state: '',
        zip_code: '',
        country: client.country || '',
        job_title: '',
        create_user: false,
    };
    editErrors.value = {};
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingClient.value = null;
};

const submitEditClient = () => {
    if (!editingClient.value) return;
    
    editErrors.value = {};
    isUpdating.value = true;
    
    router.put(
        route('admin.clients.update', editingClient.value.id),
        editForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
            },
            onError: (errors) => {
                editErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                isUpdating.value = false;
            },
        }
    );
};

const deleteClient = (client: Client) => {
    if (confirm(`¿Estás seguro de eliminar al cliente "${client.full_name}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('admin.clients.destroy', client.id));
    }
};

const copyPassword = async () => {
    try {
        await navigator.clipboard.writeText(generatedPassword.value);
        passwordCopied.value = true;
        setTimeout(() => {
            passwordCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Error copying password:', err);
    }
};

const closePasswordModal = () => {
    showPasswordModal.value = false;
    generatedPassword.value = '';
};

const flashMessage = computed(() => {
    const flash = page.props.flash as any;
    return flash?.message;
});

const flashError = computed(() => {
    const flash = page.props.flash as any;
    return flash?.error;
});

const hasActiveFilters = computed(() => searchInput.value);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Clientes', href: '/admin/clients' },
];
</script>

<template>
    <Head title="Gestión de Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                    <div class="flex items-center space-x-4">
                        <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/50">
                            <Users class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Catálogo de Clientes</h1>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">
                                Gestiona los {{ stats.total }} clientes del sistema
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                            <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoading }]" />
                            <span class="ml-2 hidden sm:inline">Actualizar</span>
                        </Button>

                        <Button @click="openCreateModal" class="bg-blue-600 text-white shadow-lg hover:bg-blue-700">
                            <Plus class="h-4 w-4" />
                            <span class="ml-2">Nuevo Cliente</span>
                        </Button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <div
                    v-if="flashMessage"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-800 dark:bg-green-900/20"
                >
                    <div class="flex items-center">
                        <CheckCircle2 class="h-5 w-5 text-green-400" />
                        <p class="ml-3 text-sm font-medium text-green-800 dark:text-green-200">{{ flashMessage }}</p>
                    </div>
                </div>

                <div
                    v-if="flashError"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm dark:border-red-800 dark:bg-red-900/20"
                >
                    <div class="flex items-center">
                        <XCircle class="h-5 w-5 text-red-400" />
                        <p class="ml-3 text-sm font-medium text-red-800 dark:text-red-200">{{ flashError }}</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-blue-50 p-2 dark:bg-blue-900/50">
                                    <Users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-green-50 p-2 dark:bg-green-900/50">
                                    <Smartphone class="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Con Dispositivos</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.with_devices }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-purple-50 p-2 dark:bg-purple-900/50">
                                    <UserPlus class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Con Usuarios</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.with_users }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-cyan-50 p-2 dark:bg-cyan-900/50">
                                    <CheckCircle2 class="h-6 w-6 text-cyan-600 dark:text-cyan-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Últimos 30 días</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.recent }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Search -->
                <Card class="border border-gray-200 dark:border-gray-700">
                    <CardContent class="p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                            <div class="flex-1">
                                <div class="relative">
                                    <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        v-model="searchInput"
                                        placeholder="Buscar por nombre, email, empresa, teléfono..."
                                        class="h-12 border-gray-300 pl-10 pr-10 text-base focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600"
                                    />
                                    <button
                                        v-if="searchInput"
                                        @click="searchInput = ''"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <X class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters">
                                <X class="mr-2 h-4 w-4" />
                                Limpiar
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Table -->
                <Card class="overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-gray-900/80">
                        <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
                            <RefreshCw class="h-6 w-6 animate-spin" />
                            <span class="text-lg font-medium">Cargando clientes...</span>
                        </div>
                    </div>

                    <div class="relative">
                        <Table>
                            <TableHeader>
                                <TableRow class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                    <TableHead class="cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('first_name')">
                                        <div class="flex items-center space-x-2 font-semibold">
                                            <span>Cliente</span>
                                            <component :is="getSortIcon('first_name')" class="h-4 w-4" />
                                        </div>
                                    </TableHead>
                                    <TableHead class="font-semibold">Contacto</TableHead>
                                    <TableHead class="cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('company')">
                                        <div class="flex items-center space-x-2 font-semibold">
                                            <span>Empresa</span>
                                            <component :is="getSortIcon('company')" class="h-4 w-4" />
                                        </div>
                                    </TableHead>
                                    <TableHead class="font-semibold text-center">Recursos</TableHead>
                                    <TableHead class="text-center font-semibold">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="client in clients.data"
                                    :key="client.id"
                                    class="border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                >
                                    <!-- Client -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-blue-600 text-white font-bold">
                                                {{ client.first_name.charAt(0) }}{{ client.last_name.charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ client.full_name }}
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    Desde {{ client.created_at }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Contact -->
                                    <TableCell class="py-4">
                                        <div class="space-y-1">
                                            <div class="flex items-center space-x-2 text-sm">
                                                <Mail class="h-3 w-3 text-gray-400" />
                                                <span class="text-gray-600 dark:text-gray-400">{{ client.email }}</span>
                                            </div>
                                            <div v-if="client.phone" class="flex items-center space-x-2 text-sm">
                                                <Phone class="h-3 w-3 text-gray-400" />
                                                <span class="text-gray-600 dark:text-gray-400">{{ client.phone }}</span>
                                            </div>
                                            <div v-if="client.city || client.country" class="flex items-center space-x-2 text-sm">
                                                <MapPin class="h-3 w-3 text-gray-400" />
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    {{ [client.city, client.country].filter(Boolean).join(', ') }}
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Company -->
                                    <TableCell class="py-4">
                                        <div v-if="client.company" class="flex items-center space-x-2">
                                            <Building2 class="h-4 w-4 text-gray-400" />
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ client.company }}</span>
                                        </div>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>

                                    <!-- Resources -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center justify-center space-x-3">
                                            <Badge class="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                <Smartphone class="mr-1 h-3 w-3" />
                                                {{ client.devices_count }}
                                            </Badge>
                                            <Badge class="bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                                <Car class="mr-1 h-3 w-3" />
                                                {{ client.vehicles_count }}
                                            </Badge>
                                            <Badge class="bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                <Users class="mr-1 h-3 w-3" />
                                                {{ client.users_count }}
                                            </Badge>
                                        </div>
                                    </TableCell>

                                    <!-- Actions -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center justify-center space-x-1">
                                            <Link
                                                :href="`/admin/clients/${client.id}`"
                                                class="rounded-lg p-2 text-blue-600 transition-all hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-900/50"
                                                title="Ver detalles"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Link>

                                            <button
                                                @click="openEditModal(client)"
                                                class="rounded-lg p-2 text-amber-600 transition-all hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-900/50"
                                                title="Editar"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </button>

                                            <SimpleDropdown align="right">
                                                <template #trigger>
                                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                                                        <MoreVertical class="h-4 w-4" />
                                                    </Button>
                                                </template>

                                                <Link
                                                    :href="`/clients/${client.id}/devices`"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <Smartphone class="mr-3 h-4 w-4" />
                                                    Ver Dispositivos
                                                </Link>

                                                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                                                <button
                                                    @click="deleteClient(client)"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 class="mr-3 h-4 w-4" />
                                                    Eliminar Cliente
                                                </button>
                                            </SimpleDropdown>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="clients.data.length === 0 && !isLoading" class="px-6 py-20 text-center">
                        <div class="mx-auto max-w-md">
                            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                                <Users class="h-12 w-12 text-gray-400" />
                            </div>

                            <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ hasActiveFilters ? 'No se encontraron resultados' : 'No hay clientes registrados' }}
                            </h3>

                            <p class="mb-8 text-gray-600 dark:text-gray-400">
                                {{
                                    hasActiveFilters
                                        ? 'Intenta con otros términos de búsqueda.'
                                        : 'Comienza agregando tu primer cliente.'
                                }}
                            </p>

                            <Button v-if="hasActiveFilters" variant="outline" size="lg" @click="clearFilters">
                                <X class="mr-2 h-4 w-4" />
                                Limpiar Búsqueda
                            </Button>
                            <Button v-else @click="openCreateModal" class="bg-blue-600 text-white hover:bg-blue-700">
                                <Plus class="mr-2 h-4 w-4" />
                                Agregar Primer Cliente
                            </Button>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="clients.links.length > 3 && clients.data.length > 0"
                        class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50"
                    >
                        <div class="flex flex-col space-y-4 sm:flex-row sm:items-center sm:justify-between sm:space-y-0">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Mostrando <span class="font-semibold">{{ clients.meta.from }}</span> a
                                <span class="font-semibold">{{ clients.meta.to }}</span> de
                                <span class="font-semibold">{{ clients.meta.total }}</span> clientes
                            </div>

                            <div class="flex items-center space-x-2">
                                <template v-for="link in clients.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                                            link.active
                                                ? 'bg-blue-600 text-white shadow-md'
                                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'cursor-not-allowed rounded-lg px-4 py-2 text-sm font-medium opacity-50',
                                            link.active ? 'bg-blue-600 text-white' : 'text-gray-400',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Create Client Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeCreateModal"></div>
                    
                    <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/50">
                                    <Plus class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Nuevo Cliente
                                </h3>
                            </div>
                            <button
                                @click="closeCreateModal"
                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submitCreateClient" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Nombre <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="createForm.first_name"
                                        placeholder="Juan"
                                        :class="createErrors.first_name ? 'border-red-500' : ''"
                                    />
                                    <p v-if="createErrors.first_name" class="mt-1 text-xs text-red-500">{{ createErrors.first_name }}</p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Apellido <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="createForm.last_name"
                                        placeholder="Pérez"
                                        :class="createErrors.last_name ? 'border-red-500' : ''"
                                    />
                                    <p v-if="createErrors.last_name" class="mt-1 text-xs text-red-500">{{ createErrors.last_name }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <Input
                                    v-model="createForm.email"
                                    type="email"
                                    placeholder="juan@ejemplo.com"
                                    :class="createErrors.email ? 'border-red-500' : ''"
                                />
                                <p v-if="createErrors.email" class="mt-1 text-xs text-red-500">{{ createErrors.email }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Teléfono
                                    </label>
                                    <Input v-model="createForm.phone" placeholder="+52 555 123 4567" />
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Empresa
                                    </label>
                                    <Input v-model="createForm.company" placeholder="Racing Team MX" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Ciudad
                                    </label>
                                    <Input v-model="createForm.city" placeholder="Tijuana" />
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        País
                                    </label>
                                    <Input v-model="createForm.country" placeholder="México" />
                                </div>
                            </div>

                            <!-- Create User Checkbox -->
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="createForm.create_user"
                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">Crear usuario de acceso</p>
                                        <p class="text-sm text-gray-500">Se generará una contraseña automática</p>
                                    </div>
                                </label>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <Button type="button" variant="outline" @click="closeCreateModal">
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="isCreating"
                                    class="bg-blue-600 text-white hover:bg-blue-700"
                                >
                                    <RefreshCw v-if="isCreating" class="mr-2 h-4 w-4 animate-spin" />
                                    <Plus v-else class="mr-2 h-4 w-4" />
                                    {{ isCreating ? 'Creando...' : 'Crear Cliente' }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit Client Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeEditModal"></div>
                    
                    <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/50">
                                    <Edit class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Editar Cliente
                                </h3>
                            </div>
                            <button
                                @click="closeEditModal"
                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submitEditClient" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                                    <Input v-model="editForm.first_name" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Apellido</label>
                                    <Input v-model="editForm.last_name" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                <Input v-model="editForm.email" type="email" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Teléfono</label>
                                    <Input v-model="editForm.phone" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Empresa</label>
                                    <Input v-model="editForm.company" />
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <Button type="button" variant="outline" @click="closeEditModal">Cancelar</Button>
                                <Button type="submit" :disabled="isUpdating" class="bg-amber-600 text-white hover:bg-amber-700">
                                    <RefreshCw v-if="isUpdating" class="mr-2 h-4 w-4 animate-spin" />
                                    {{ isUpdating ? 'Guardando...' : 'Guardar Cambios' }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Password Display Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showPasswordModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
                    
                    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                        <div class="text-center">
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                                <CheckCircle2 class="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                            
                            <h3 class="mb-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                                ¡Usuario Creado!
                            </h3>
                            
                            <p class="mb-6 text-gray-600 dark:text-gray-400">
                                Guarda esta contraseña, no se mostrará de nuevo.
                            </p>

                            <div class="mb-6 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-800">
                                <p class="mb-2 text-sm text-gray-500">Contraseña generada:</p>
                                <div class="flex items-center justify-center space-x-2">
                                    <code class="text-xl font-mono font-bold text-gray-900 dark:text-gray-100">
                                        {{ generatedPassword }}
                                    </code>
                                    <button
                                        @click="copyPassword"
                                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700"
                                        title="Copiar"
                                    >
                                        <Copy v-if="!passwordCopied" class="h-5 w-5" />
                                        <CheckCircle2 v-else class="h-5 w-5 text-green-500" />
                                    </button>
                                </div>
                                <p v-if="passwordCopied" class="mt-2 text-sm text-green-600">¡Copiado!</p>
                            </div>

                            <Button @click="closePasswordModal" class="w-full bg-blue-600 text-white hover:bg-blue-700">
                                Entendido
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
