<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js' // Agregar esta importación
import { computed, ref, watch } from 'vue'
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
  RefreshCw,
  Smartphone,
  X,
  ArrowLeft,
  Wifi,
  WifiOff,
  Settings,
  Car,
  Zap,
  Clock,
  Cpu,
  Hash
} from 'lucide-vue-next'
import type { DeviceIndexProps, BreadcrumbItem } from '@/types'

const props = defineProps<DeviceIndexProps>()
const page = usePage()

// Estado reactivo
const searchInput = ref(props.filters.search || '')
const isLoading = ref(false)

// Búsqueda con debounce
let searchTimeout: ReturnType<typeof setTimeout>
watch(searchInput, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    performSearch()
  }, 300)
})

const performSearch = () => {
  if (isLoading.value) return
  
  isLoading.value = true
  router.get(route('clients.devices.index', props.client.id), {
    search: searchInput.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      isLoading.value = false
    }
  })
}

const clearSearch = () => {
  searchInput.value = ''
}

const refreshData = () => {
  isLoading.value = true
  router.get(route('clients.devices.index', props.client.id), {
    search: searchInput.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      isLoading.value = false
    }
  })
}

const deleteDevice = (device: any) => {
  if (confirm(`¿Estás seguro de que deseas eliminar el dispositivo ${device.device_name}?`)) {
    router.delete(route('clients.devices.destroy', [props.client.id, device.id]))
  }
}

const activateDevice = (device: any) => {
  router.post(route('clients.devices.activate', [props.client.id, device.id]))
}

const deactivateDevice = (device: any) => {
  router.post(route('clients.devices.deactivate', [props.client.id, device.id]))
}

// Computadas
const flashMessage = computed(() => {
  const flash = page.props.flash as any
  return flash?.message
})

const totalDevices = computed(() => props.devices?.meta?.total || 0)
const hasActiveFilters = computed(() => searchInput.value)

const getStatusBadge = (status: string) => {
  const badges = {
    pending: { variant: 'secondary', text: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' },
    active: { variant: 'success', text: 'Activo', class: 'bg-green-100 text-green-800' },
    inactive: { variant: 'destructive', text: 'Inactivo', class: 'bg-red-100 text-red-800' },
    maintenance: { variant: 'warning', text: 'Mantenimiento', class: 'bg-orange-100 text-orange-800' },
    retired: { variant: 'secondary', text: 'Retirado', class: 'bg-gray-100 text-gray-800' }
  }
  return badges[status as keyof typeof badges] || badges.pending
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clients' },
  { title: props.client.full_name, href: `/clients/${props.client.id}` },
  { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
]
</script>

<template>
  <Head :title="`Dispositivos de ${client.full_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.show', client.id)">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver al Cliente
            </Button>
          </Link>
          
          <div class="flex items-center space-x-4">
            <div class="p-3 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
              <Smartphone class="h-8 w-8 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                Dispositivos
              </h1>
              <p class="text-gray-600 dark:text-gray-400 mt-1">
                Gestiona los {{ totalDevices }} {{ totalDevices === 1 ? 'dispositivo' : 'dispositivos' }} de {{ client.full_name }}
              </p>
            </div>
          </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
          <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
            <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoading }]" />
            <span class="ml-2 hidden sm:inline">Actualizar</span>
          </Button>
          
          <Link 
            v-if="can.create_device"
            :href="route('clients.devices.create', client.id)"
          >
            <Button class="bg-blue-600 hover:bg-blue-700 text-white shadow-lg">
              <Plus class="h-4 w-4" />
              <span class="ml-2">Nuevo Dispositivo</span>
            </Button>
          </Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Mensaje flash -->
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

        <!-- Estadísticas rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/50 rounded-lg">
                  <Smartphone class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ totalDevices }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-green-50 dark:bg-green-900/50 rounded-lg">
                  <Wifi class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Activos</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ devices?.data?.filter(d => d.status === 'active').length || 0 }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-orange-50 dark:bg-orange-900/50 rounded-lg">
                  <Clock class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pendientes</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ devices?.data?.filter(d => d.status === 'pending').length || 0 }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-purple-50 dark:bg-purple-900/50 rounded-lg">
                  <Car class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Con Vehículo</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ devices?.data?.filter(d => d.vehicle).length || 0 }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Barra de búsqueda -->
        <Card class="border border-gray-200 dark:border-gray-700">
          <CardContent class="p-6">
            <div class="flex flex-col space-y-4">
              <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                  <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <Input
                      v-model="searchInput"
                      placeholder="Buscar por nombre de dispositivo o dirección MAC..."
                      class="pl-10 pr-10 h-12 text-base border-gray-300 dark:border-gray-600"
                    />
                    <button
                      v-if="searchInput"
                      @click="clearSearch"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      <X class="h-5 w-5" />
                    </button>
                  </div>
                </div>

                <Link 
                  v-if="can.create_device"
                  :href="route('clients.devices.create', client.id)"
                >
                  <Button size="lg" class="bg-blue-600 hover:bg-blue-700">
                    <Plus class="h-4 w-4" />
                    <span class="ml-2">Agregar Dispositivo</span>
                  </Button>
                </Link>
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

        <!-- Tabla de dispositivos -->
        <Card class="border border-gray-200 dark:border-gray-700 overflow-hidden">
          <!-- Loading overlay -->
          <div v-if="isLoading" class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 z-10 flex items-center justify-center">
            <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
              <RefreshCw class="h-6 w-6 animate-spin" />
              <span class="text-lg font-medium">Cargando dispositivos...</span>
            </div>
          </div>

          <div class="relative">
            <Table>
              <TableHeader>
                <TableRow class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                  <TableHead class="font-semibold">Dispositivo</TableHead>
                  <TableHead class="font-semibold">Hardware</TableHead>
                  <TableHead class="font-semibold">Estado</TableHead>
                  <TableHead class="font-semibold">Conexión</TableHead>
                  <TableHead class="font-semibold">Vehículo</TableHead>
                  <TableHead class="text-center font-semibold">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow 
                  v-for="device in devices?.data || []" 
                  :key="device.id"
                  class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800"
                >
                  <!-- Dispositivo -->
                  <TableCell class="py-4">
                    <div class="flex items-center space-x-4">
                      <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-md">
                        <Smartphone class="h-6 w-6 text-white" />
                      </div>
                      <div>
                        <Link 
                          :href="route('clients.devices.show', [client.id, device.id])"
                          class="font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                        >
                          {{ device.device_name }}
                        </Link>
                        <div class="flex items-center space-x-2 mt-1">
                          <Hash class="h-3 w-3 text-gray-400" />
                          <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                            {{ device.mac_address }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </TableCell>

                  <!-- Hardware -->
                  <TableCell class="py-4">
                    <div v-if="device.device_inventory" class="space-y-1">
                      <div class="flex items-center space-x-2">
                        <Cpu class="h-4 w-4 text-gray-400" />
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                          {{ device.device_inventory.model }}
                        </span>
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        SN: {{ device.device_inventory.serial_number }}
                      </div>
                      <div class="text-xs text-gray-400">
                        HW: {{ device.device_inventory.hardware_version }} | 
                        FW: {{ device.device_inventory.firmware_version }}
                      </div>
                    </div>
                    <span v-else class="text-gray-400 italic text-sm">Sin información</span>
                  </TableCell>

                  <!-- Estado -->
                  <TableCell class="py-4">
                    <Badge :class="getStatusBadge(device.status).class">
                      {{ getStatusBadge(device.status).text }}
                    </Badge>
                  </TableCell>

                  <!-- Conexión -->
                  <TableCell class="py-4">
                    <div class="space-y-1">
                      <div v-if="device.last_ping" class="flex items-center space-x-2">
                        <Wifi class="h-4 w-4 text-green-500" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                          Último ping
                        </span>
                      </div>
                      <div v-else class="flex items-center space-x-2">
                        <WifiOff class="h-4 w-4 text-gray-400" />
                        <span class="text-sm text-gray-400">Sin conexión</span>
                      </div>
                      
                      <div v-if="device.last_ping" class="text-xs text-gray-500">
                        {{ new Date(device.last_ping).toLocaleString('es-ES') }}
                      </div>
                      
                      <div v-if="device.activated_at" class="text-xs text-gray-400">
                        Activado: {{ new Date(device.activated_at).toLocaleDateString('es-ES') }}
                      </div>
                    </div>
                  </TableCell>

                  <!-- Vehículo -->
                  <TableCell class="py-4">
                    <div v-if="device.vehicle" class="flex items-center space-x-2">
                      <Car class="h-4 w-4 text-gray-400" />
                      <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">
                          {{ device.vehicle.nickname || `${device.vehicle.make} ${device.vehicle.model}` }}
                        </div>
                        <div v-if="device.vehicle.license_plate" class="text-sm text-gray-500">
                          {{ device.vehicle.license_plate }}
                        </div>
                      </div>
                    </div>
                    <span v-else class="text-gray-400 italic text-sm">Sin vehículo</span>
                  </TableCell>

                  <!-- Acciones -->
                  <TableCell class="py-4">
                    <div class="flex items-center justify-center space-x-1">
                      <Link 
                        v-if="device.can?.view"
                        :href="route('clients.devices.show', [client.id, device.id])"
                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/50 rounded-lg transition-all"
                        title="Ver detalles"
                      >
                        <Eye class="h-4 w-4" />
                      </Link>
                      
                      <Link 
                        v-if="device.can?.update"
                        :href="route('clients.devices.edit', [client.id, device.id])"
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
                          v-if="device.can?.view"
                          :href="route('clients.devices.show', [client.id, device.id])" 
                          class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                          <Eye class="mr-3 h-4 w-4" />
                          Ver Detalles
                        </Link>
                        
                        <Link 
                          v-if="device.can?.update"
                          :href="route('clients.devices.edit', [client.id, device.id])" 
                          class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                          <Edit class="mr-3 h-4 w-4" />
                          Editar Dispositivo
                        </Link>
                        
                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                        
                        <button 
                          v-if="device.status === 'pending' || device.status === 'inactive'"
                          @click="activateDevice(device)"
                          class="flex items-center w-full px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors"
                        >
                          <Zap class="mr-3 h-4 w-4" />
                          Activar Dispositivo
                        </button>
                        
                        <button 
                          v-if="device.status === 'active'"
                          @click="deactivateDevice(device)"
                          class="flex items-center w-full px-4 py-2 text-sm text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors"
                        >
                          <Settings class="mr-3 h-4 w-4" />
                          Desactivar Dispositivo
                        </button>
                        
                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                        
                        <button 
                          v-if="device.can?.delete"
                          @click="deleteDevice(device)"
                          class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                        >
                          <Trash2 class="mr-3 h-4 w-4" />
                          Eliminar Dispositivo
                        </button>
                      </SimpleDropdown>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <!-- Estado vacío -->
          <div 
            v-if="(devices?.data?.length || 0) === 0 && !isLoading" 
            class="text-center py-20 px-6"
          >
            <div class="max-w-md mx-auto">
              <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                <Smartphone class="h-12 w-12 text-gray-400" />
              </div>
              
              <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                {{ searchInput ? 'No se encontraron dispositivos' : 'No hay dispositivos registrados' }}
              </h3>
              
              <p class="text-gray-600 dark:text-gray-400 mb-8">
                {{ searchInput 
                  ? `No encontramos dispositivos que coincidan con "${searchInput}".`
                  : 'Comienza agregando el primer dispositivo para este cliente.' 
                }}
              </p>
              
              <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <Link 
                  v-if="can.create_device && !searchInput"
                  :href="route('clients.devices.create', client.id)"
                >
                  <Button size="lg" class="bg-blue-600 hover:bg-blue-700 text-white shadow-lg">
                    <Plus class="mr-2 h-5 w-5" />
                    Crear Primer Dispositivo
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

          <!-- Paginación -->
          <div v-if="(devices?.links?.length || 0) > 3 && (devices?.data?.length || 0) > 0" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Mostrando <span class="font-semibold">{{ devices?.meta?.from || 0 }}</span> a 
                <span class="font-semibold">{{ devices?.meta?.to || 0 }}</span> de 
                <span class="font-semibold">{{ totalDevices }}</span> dispositivos
              </div>
              
              <div class="flex items-center space-x-2">
                <template v-for="link in devices?.links || []" :key="link.label">
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