<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import Badge from '@/components/ui/Badge.vue'
import Table from '@/components/ui/Table.vue'
import TableBody from '@/components/ui/TableBody.vue'
import TableCell from '@/components/ui/TableCell.vue'
import TableHead from '@/components/ui/TableHead.vue'
import TableHeader from '@/components/ui/TableHeader.vue'
import TableRow from '@/components/ui/TableRow.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue'
import { 
  MoreVertical, 
  Plus, 
  Search, 
  Eye, 
  Edit, 
  Trash2,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  Filter,
  Download,
  RefreshCw,
  Users,
  X,
  Building2,
  MapPin,
  Phone,
  Mail,
  Calendar,
} from 'lucide-vue-next'
import type { Client, PaginatedData, ClientFilters, Permissions, BreadcrumbItem } from '@/types'

interface Props {
  clients: PaginatedData<Client>
  filters: ClientFilters
  can: Permissions
}

const props = defineProps<Props>()
const page = usePage()

// Estado reactivo para filtros
const searchInput = ref(props.filters.search || '')
const sort = ref(props.filters.sort || '')
const direction = ref(props.filters.direction || '')
const isLoading = ref(false)
const showFilters = ref(false)

// Debounced search - busca automáticamente mientras escribes
let searchTimeout: ReturnType<typeof setTimeout>
watch(searchInput, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    performSearch()
  }, 300)
})

// Función para realizar búsqueda
const performSearch = () => {
  if (isLoading.value) return
  
  isLoading.value = true
  router.get(route('clients.index'), {
    search: searchInput.value,
    sort: sort.value,
    direction: direction.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      isLoading.value = false
    }
  })
}

// Función para limpiar búsqueda
const clearSearch = () => {
  searchInput.value = ''
}

// Función para refrescar datos
const refreshData = () => {
  isLoading.value = true
  router.get(route('clients.index'), {
    search: searchInput.value,
    sort: sort.value,
    direction: direction.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      isLoading.value = false
    }
  })
}

// Función para ordenar columnas
const sortBy = (column: string) => {
  if (sort.value === column) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = column
    direction.value = 'asc'
  }
  performSearch()
}

// Función para eliminar cliente
const deleteClient = (client: Client) => {
  if (confirm(`¿Estás seguro de que deseas eliminar al cliente ${client.full_name}?`)) {
    router.delete(route('clients.destroy', client.id), {
      onSuccess: () => {
        // El mensaje se mostrará automáticamente
      }
    })
  }
}

// Función para obtener el ícono de ordenamiento
const getSortIcon = (column: string) => {
  if (sort.value !== column) return ArrowUpDown
  return direction.value === 'asc' ? ArrowUp : ArrowDown
}

// Función para exportar clientes a CSV
const exportClients = () => {
  const headers = ['Nombre', 'Email', 'Teléfono', 'Empresa', 'Ciudad', 'Estado', 'País', 'Fecha Registro'];
  const csvRows = [headers.join(',')];
  
  props.clients.data.forEach(client => {
    const row = [
      `"${client.full_name}"`,
      `"${client.email}"`,
      `"${client.phone || ''}"`,
      `"${client.company || ''}"`,
      `"${client.city || ''}"`,
      `"${client.state || ''}"`,
      `"${client.country || ''}"`,
      `"${new Date(client.created_at).toLocaleDateString('es-ES')}"`,
    ];
    csvRows.push(row.join(','));
  });
  
  const csvContent = csvRows.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `clientes_${new Date().toISOString().split('T')[0]}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Computadas
const flashMessage = computed(() => {
  const flash = page.props.flash as any
  return flash?.message
})

const totalClients = computed(() => props.clients.meta.total)
const hasActiveFilters = computed(() => searchInput.value)
const currentPageStart = computed(() => props.clients.meta.from || 0)
const currentPageEnd = computed(() => props.clients.meta.to || 0)

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Clientes',
    href: '/clients',
  },
];

onMounted(() => {
  // check if user role is SA
  const user = (page.props as any).auth.user;
  if (user.role !== 'SA') {
    router.visit('/dashboard');
  }
})
</script>

<template>
  <Head title="Gestión de Clientes" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header mejorado -->
    <template #header>
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        <div class="flex items-center space-x-4">
          <div class="p-3 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
            <Users class="h-8 w-8 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
              Clientes
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
              Gestiona tu cartera de {{ totalClients.toLocaleString() }} {{ totalClients === 1 ? 'cliente' : 'clientes' }}
            </p>
          </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
          <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
            <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoading }]" />
            <span class="ml-2 hidden sm:inline">Actualizar</span>
          </Button>
          
          <Button variant="outline" size="sm" @click="exportClients">
            <Download class="h-4 w-4" />
            <span class="ml-2 hidden sm:inline">Exportar</span>
          </Button>
          
          <Link 
            v-if="can.create_client"
            :href="route('clients.create')"
          >
            <Button class="bg-blue-600 hover:bg-blue-700 text-white shadow-lg">
              <Plus class="h-4 w-4" />
              <span class="ml-2">Nuevo Cliente</span>
            </Button>
          </Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Mensaje flash mejorado -->
        <div 
          v-if="flashMessage" 
          class="rounded-lg bg-green-50 border border-green-200 p-4 shadow-sm dark:bg-green-900/20 dark:border-green-800"
        >
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800 dark:text-green-200">
                {{ flashMessage }}
              </p>
            </div>
          </div>
        </div>

        <!-- Barra de búsqueda y filtros mejorada -->
        <Card class="border border-gray-200 dark:border-gray-700">
          <CardContent class="p-6">
            <div class="flex flex-col space-y-4">
              <!-- Búsqueda principal -->
              <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                  <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <Input
                      v-model="searchInput"
                      placeholder="Buscar por nombre, email, empresa o ubicación..."
                      class="pl-10 pr-10 h-12 text-base border-gray-300 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500"
                    />
                    <button
                      v-if="searchInput"
                      @click="clearSearch"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    >
                      <X class="h-5 w-5" />
                    </button>
                  </div>
                </div>

                <div class="flex space-x-2">
                  <Button 
                    variant="outline" 
                    size="lg"
                    @click="showFilters = !showFilters"
                  >
                    <Filter class="h-4 w-4" />
                    <span class="ml-2">Filtros</span>
                  </Button>
                  
                  <Link 
                    v-if="can.create_client"
                    :href="route('clients.create')"
                  >
                    <Button size="lg" class="bg-blue-600 hover:bg-blue-700">
                      <Plus class="h-4 w-4" />
                      <span class="ml-2">Agregar</span>
                    </Button>
                  </Link>
                </div>
              </div>

              <!-- Filtros activos -->
              <div v-if="hasActiveFilters" class="flex flex-wrap gap-2">
                <span class="text-sm text-gray-500 dark:text-gray-400 py-2">Filtros activos:</span>
                <Badge variant="secondary" class="flex items-center gap-2 px-3 py-1">
                  <span>Búsqueda: "{{ searchInput }}"</span>
                  <button @click="clearSearch" class="text-gray-500 hover:text-red-600 transition-colors">
                    <X class="h-3 w-3" />
                  </button>
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Tabla principal mejorada -->
        <Card class="border border-gray-200 dark:border-gray-700 overflow-hidden">
          <!-- Loading overlay -->
          <div v-if="isLoading" class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 z-10 flex items-center justify-center">
            <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
              <RefreshCw class="h-6 w-6 animate-spin" />
              <span class="text-lg font-medium">Cargando clientes...</span>
            </div>
          </div>

          <div class="relative">
            <Table>
              <TableHeader>
                <TableRow class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" @click="sortBy('first_name')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Cliente</span>
                      <component :is="getSortIcon('first_name')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" @click="sortBy('email')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Contacto</span>
                      <component :is="getSortIcon('email')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" @click="sortBy('company')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Empresa</span>
                      <component :is="getSortIcon('company')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="font-semibold">Ubicación</TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" @click="sortBy('created_at')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Registro</span>
                      <component :is="getSortIcon('created_at')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="text-center font-semibold">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow 
                  v-for="client in clients.data" 
                  :key="client.id"
                  class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800"
                >
                  <!-- Cliente -->
                  <TableCell class="py-4">
                    <div class="flex items-center space-x-4">
                      <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-md">
                        <span class="text-lg font-bold text-white">
                          {{ client.first_name.charAt(0).toUpperCase() }}{{ client.last_name.charAt(0).toUpperCase() }}
                        </span>
                      </div>
                      <div>
                        <Link 
                          :href="route('clients.show', client.id)"
                          class="font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                        >
                          {{ client.full_name }}
                        </Link>
                        <div v-if="client.job_title" class="text-sm text-gray-500 dark:text-gray-400">
                          {{ client.job_title }}
                        </div>
                      </div>
                    </div>
                  </TableCell>

                  <!-- Contacto -->
                  <TableCell class="py-4">
                    <div class="space-y-1">
                      <div class="flex items-center space-x-2">
                        <Mail class="h-4 w-4 text-gray-400" />
                        <a 
                          :href="`mailto:${client.email}`" 
                          class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                        >
                          {{ client.email }}
                        </a>
                      </div>
                      <div v-if="client.phone" class="flex items-center space-x-2">
                        <Phone class="h-4 w-4 text-gray-400" />
                        <span class="text-gray-600 dark:text-gray-400">{{ client.phone }}</span>
                      </div>
                    </div>
                  </TableCell>

                  <!-- Empresa -->
                  <TableCell class="py-4">
                    <div v-if="client.company" class="flex items-center space-x-2">
                      <Building2 class="h-4 w-4 text-gray-400" />
                      <Badge variant="secondary" class="font-medium">
                        {{ client.company }}
                      </Badge>
                    </div>
                    <span v-else class="text-gray-400 italic text-sm">Sin empresa</span>
                  </TableCell>

                  <!-- Ubicación -->
                  <TableCell class="py-4">
                    <div v-if="client.city || client.state || client.country" class="flex items-center space-x-2">
                      <MapPin class="h-4 w-4 text-gray-400" />
                      <span class="text-gray-600 dark:text-gray-400">
                        {{ [client.city, client.state, client.country].filter(Boolean).join(', ') }}
                      </span>
                    </div>
                    <span v-else class="text-gray-400 italic text-sm">Sin ubicación</span>
                  </TableCell>

                  <!-- Fecha -->
                  <TableCell class="py-4">
                    <div class="flex items-center space-x-2">
                      <Calendar class="h-4 w-4 text-gray-400" />
                      <span class="text-gray-600 dark:text-gray-400">
                        {{ new Date(client.created_at).toLocaleDateString('es-ES', {
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric'
                        }) }}
                      </span>
                    </div>
                  </TableCell>

                  <!-- Acciones -->
                  <TableCell class="py-4">
                    <div class="flex items-center justify-center space-x-1">
                      <Link 
                        v-if="client.can?.view"
                        :href="route('clients.show', client.id)"
                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/50 rounded-lg transition-all"
                        title="Ver detalles"
                      >
                        <Eye class="h-4 w-4" />
                      </Link>
                      
                      <Link 
                        v-if="client.can?.update"
                        :href="route('clients.edit', client.id)"
                        class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/50 rounded-lg transition-all"
                        title="Editar"
                      >
                        <Edit class="h-4 w-4" />
                      </Link>

                      <SimpleDropdown align="right">
                        <template #trigger>
                          <Button variant="ghost" size="sm" class="h-8 w-8 p-0 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <MoreVertical class="h-4 w-4" />
                          </Button>
                        </template>
                        
                        <Link 
                          v-if="client.can?.view"
                          :href="route('clients.show', client.id)" 
                          class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                          <Eye class="mr-3 h-4 w-4" />
                          Ver Detalles
                        </Link>
                        
                        <Link 
                          v-if="client.can?.update"
                          :href="route('clients.edit', client.id)" 
                          class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                          <Edit class="mr-3 h-4 w-4" />
                          Editar Cliente
                        </Link>
                        
                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                        
                        <button 
                          v-if="client.can?.delete"
                          @click="deleteClient(client)"
                          class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
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

          <!-- Estado vacío mejorado -->
          <div 
            v-if="clients.data.length === 0 && !isLoading" 
            class="text-center py-20 px-6"
          >
            <div class="max-w-md mx-auto">
              <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                <Users class="h-12 w-12 text-gray-400" />
              </div>
              
              <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                {{ searchInput ? 'No se encontraron resultados' : 'No hay clientes registrados' }}
              </h3>
              
              <p class="text-gray-600 dark:text-gray-400 mb-8">
                {{ searchInput 
                  ? `No encontramos clientes que coincidan con "${searchInput}". Intenta con otros términos.`
                  : 'Comienza agregando tu primer cliente para gestionar tu cartera de negocios.' 
                }}
              </p>
              
              <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <Link 
                  v-if="can.create_client && !searchInput"
                  :href="route('clients.create')"
                >
                  <Button size="lg" class="bg-blue-600 hover:bg-blue-700 text-white shadow-lg">
                    <Plus class="mr-2 h-5 w-5" />
                    Crear Primer Cliente
                  </Button>
                </Link>
                
                <Button 
                  v-if="searchInput" 
                  variant="outline" 
                  size="lg"
                  @click="clearSearch"
                >
                  <X class="mr-2 h-4 w-4" />
                  Limpiar Búsqueda
                </Button>
              </div>
            </div>
          </div>

          <!-- Paginación mejorada -->
          <div v-if="clients.links.length > 3 && clients.data.length > 0" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Mostrando <span class="font-semibold">{{ currentPageStart }}</span> a 
                <span class="font-semibold">{{ currentPageEnd }}</span> de 
                <span class="font-semibold">{{ totalClients.toLocaleString() }}</span> clientes
              </div>
              
              <div class="flex items-center space-x-2">
                <template v-for="link in clients.links" :key="link.label">
                  <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                      'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200',
                      link.active
                        ? 'bg-blue-600 text-white shadow-md'
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-gray-700'
                    ]"
                  >
                    <span v-html="link.label"></span>
                  </Link>
                  <span
                    v-else
                    :class="[
                      'px-4 py-2 text-sm font-medium rounded-lg opacity-50 cursor-not-allowed',
                      link.active
                        ? 'bg-blue-600 text-white'
                        : 'text-gray-400'
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
  </AppLayout>
</template>