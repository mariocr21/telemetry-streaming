<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
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
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  Download,
  RefreshCw,
  Package,
  X,
  Cpu,
  Hash,
  Calendar,
  Settings,
  Users,
  CheckCircle2,
  AlertTriangle
} from 'lucide-vue-next'
import type { DeviceInventoryIndexProps, BreadcrumbItem } from '@/types'

const props = defineProps<DeviceInventoryIndexProps>()
const page = usePage()

// Estado reactivo
const searchInput = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const sort = ref(props.filters?.sort || '')
const direction = ref(props.filters?.direction || '')
const isLoading = ref(false)
const selectedDevices = ref<number[]>([])

// Búsqueda con debounce
let searchTimeout: ReturnType<typeof setTimeout>
watch([searchInput, statusFilter], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    performSearch()
  }, 300)
})

const performSearch = () => {
  if (isLoading.value) return
  
  isLoading.value = true
  router.get(route('device-inventory.index'), {
    search: searchInput.value,
    status: statusFilter.value,
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

const clearFilters = () => {
  searchInput.value = ''
  statusFilter.value = ''
  sort.value = ''
  direction.value = ''
  selectedDevices.value = []
  performSearch()
}

const refreshData = () => {
  isLoading.value = true
  router.get(route('device-inventory.index'), {
    search: searchInput.value,
    status: statusFilter.value,
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

const sortBy = (column: string) => {
  if (sort.value === column) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = column
    direction.value = 'asc'
  }
  performSearch()
}

const deleteDevice = (device: any) => {
  if (device.client_devices_count > 0) {
    alert('No se puede eliminar este dispositivo porque está asignado a clientes.')
    return
  }
  
  if (confirm(`¿Estás seguro de que deseas eliminar el dispositivo ${device.serial_number}?`)) {
    router.delete(route('device-inventory.destroy', device.id))
  }
}

const bulkUpdateStatus = (status: string) => {
  if (selectedDevices.value.length === 0) {
    alert('Selecciona al menos un dispositivo.')
    return
  }
  
  if (confirm(`¿Cambiar el estado de ${selectedDevices.value.length} dispositivos a "${status}"?`)) {
    router.post(route('device-inventory.bulk-update-status'), {
      device_ids: selectedDevices.value,
      status: status
    }, {
      onSuccess: () => {
        selectedDevices.value = []
      }
    })
  }
}

const toggleDeviceSelection = (deviceId: number) => {
  const index = selectedDevices.value.indexOf(deviceId)
  if (index > -1) {
    selectedDevices.value.splice(index, 1)
  } else {
    selectedDevices.value.push(deviceId)
  }
}

const toggleAllDevices = () => {
  const devicesData = props.devices?.data ?? []
  if (selectedDevices.value.length === devicesData.length) {
    selectedDevices.value = []
  } else {
    selectedDevices.value = devicesData.map(device => device.id)
  }
}

// Computadas
const flashMessage = computed(() => {
  const flash = page.props.flash as any
  return flash?.message
})

const totalDevices = computed(() => props.devices?.meta?.total ?? 0)
const hasActiveFilters = computed(() => searchInput.value || statusFilter.value)

const getSortIcon = (column: string) => {
  if (sort.value !== column) return ArrowUpDown
  return direction.value === 'asc' ? ArrowUp : ArrowDown
}

const getStatusBadge = (status: string) => {
  const badges = {
    available: { text: 'Disponible', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
    sold: { text: 'Vendido', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
    maintenance: { text: 'Mantenimiento', class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
    retired: { text: 'Retirado', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }
  }
  return badges[status as keyof typeof badges] || badges.available
}

const deviceStats = computed(() => {
  const devicesData = props.devices?.data ?? []
  return {
    total: totalDevices.value,
    available: devicesData.filter(d => d.status === 'available').length,
    sold: devicesData.filter(d => d.status === 'sold').length,
    assigned: devicesData.filter(d => (d.client_devices_count ?? 0) > 0).length
  }
})

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Inventario de Dispositivos', href: '/device-inventory' },
]
</script>

<template>
  <Head title="Inventario de Dispositivos" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        <div class="flex items-center space-x-4">
          <div class="p-3 bg-purple-100 dark:bg-purple-900/50 rounded-lg">
            <Package class="h-8 w-8 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
              Inventario de Dispositivos
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
              Gestiona tu inventario de {{ totalDevices.toLocaleString() }} dispositivos
            </p>
          </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
          <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
            <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoading }]" />
            <span class="ml-2 hidden sm:inline">Actualizar</span>
          </Button>
          
          <Button variant="outline" size="sm">
            <Download class="h-4 w-4" />
            <span class="ml-2 hidden sm:inline">Exportar</span>
          </Button>
          
          <Link 
            v-if="can?.create_device"
            :href="route('device-inventory.create')"
          >
            <Button class="bg-purple-600 hover:bg-purple-700 text-white shadow-lg">
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
            <CheckCircle2 class="h-5 w-5 text-green-400 flex-shrink-0" />
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
                <div class="p-2 bg-purple-50 dark:bg-purple-900/50 rounded-lg">
                  <Package class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ deviceStats.total.toLocaleString() }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-green-50 dark:bg-green-900/50 rounded-lg">
                  <CheckCircle2 class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Disponibles</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ deviceStats.available }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/50 rounded-lg">
                  <Settings class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Vendidos</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ deviceStats.sold }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-orange-50 dark:bg-orange-900/50 rounded-lg">
                  <Users class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Asignados</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ deviceStats.assigned }}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Filtros y búsqueda -->
        <Card class="border border-gray-200 dark:border-gray-700">
          <CardContent class="p-6">
            <div class="flex flex-col space-y-4">
              <!-- Búsqueda principal -->
              <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                  <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <Input
                      v-model="searchInput"
                      placeholder="Buscar por número de serie, UUID o modelo..."
                      class="pl-10 pr-10 h-12 text-base border-gray-300 dark:border-gray-600"
                    />
                    <button
                      v-if="searchInput"
                      @click="searchInput = ''"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      <X class="h-5 w-5" />
                    </button>
                  </div>
                </div>

                <!-- Filtros -->
                <div class="flex flex-wrap gap-2">
                  <select
                    v-model="statusFilter"
                    class="rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                  >
                    <option value="">Todos los estados</option>
                    <option v-for="status in filterOptions?.statuses ?? []" :key="status" :value="status">
                      {{ getStatusBadge(status).text }}
                    </option>
                  </select>

                  <Link 
                    v-if="can?.create_device"
                    :href="route('device-inventory.create')"
                  >
                    <Button size="lg" class="bg-purple-600 hover:bg-purple-700">
                      <Plus class="h-4 w-4" />
                      <span class="ml-2">Agregar</span>
                    </Button>
                  </Link>
                </div>
              </div>

              <!-- Filtros activos -->
              <div v-if="hasActiveFilters" class="flex flex-wrap gap-2">
                <span class="text-sm text-gray-500 dark:text-gray-400 py-2">Filtros activos:</span>
                <Badge v-if="searchInput" variant="secondary" class="flex items-center gap-2 px-3 py-1">
                  <span>Búsqueda: "{{ searchInput }}"</span>
                  <button @click="searchInput = ''" class="text-gray-500 hover:text-red-600">
                    <X class="h-3 w-3" />
                  </button>
                </Badge>
                <Badge v-if="statusFilter" variant="secondary" class="flex items-center gap-2 px-3 py-1">
                  <span>Estado: {{ getStatusBadge(statusFilter).text }}</span>
                  <button @click="statusFilter = ''" class="text-gray-500 hover:text-red-600">
                    <X class="h-3 w-3" />
                  </button>
                </Badge>
                <Button variant="outline" size="sm" @click="clearFilters">
                  Limpiar filtros
                </Button>
              </div>

              <!-- Acciones masivas -->
              <div v-if="selectedDevices.length > 0" class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                  {{ selectedDevices.length }} dispositivos seleccionados
                </span>
                <div class="flex space-x-2">
                  <Button size="sm" variant="outline" @click="bulkUpdateStatus('available')">
                    Marcar Disponible
                  </Button>
                  <Button size="sm" variant="outline" @click="bulkUpdateStatus('maintenance')">
                    Marcar Mantenimiento
                  </Button>
                  <Button size="sm" variant="outline" @click="selectedDevices = []">
                    Cancelar
                  </Button>
                </div>
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
              <span class="text-lg font-medium">Cargando inventario...</span>
            </div>
          </div>

          <div class="relative">
            <Table>
              <TableHeader>
                <TableRow class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                  <TableHead class="w-12">
                    <input
                      type="checkbox"
                      :checked="selectedDevices.length === (devices?.data?.length ?? 0) && (devices?.data?.length ?? 0) > 0"
                      @change="toggleAllDevices"
                      class="rounded border-gray-300"
                    />
                  </TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('serial_number')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Dispositivo</span>
                      <component :is="getSortIcon('serial_number')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('model')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Modelo</span>
                      <component :is="getSortIcon('model')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="font-semibold">Versiones</TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('status')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Estado</span>
                      <component :is="getSortIcon('status')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="font-semibold">Asignación</TableHead>
                  <TableHead class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('created_at')">
                    <div class="flex items-center space-x-2 font-semibold">
                      <span>Fecha</span>
                      <component :is="getSortIcon('created_at')" class="h-4 w-4" />
                    </div>
                  </TableHead>
                  <TableHead class="text-center font-semibold">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow 
                  v-for="device in devices?.data ?? []" 
                  :key="device.id"
                  class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800"
                >
                  <!-- Checkbox -->
                  <TableCell>
                    <input
                      type="checkbox"
                      :checked="selectedDevices.includes(device.id)"
                      @change="toggleDeviceSelection(device.id)"
                      class="rounded border-gray-300"
                    />
                  </TableCell>

                  <!-- Dispositivo -->
                  <TableCell class="py-4">
                    <div class="flex items-center space-x-4">
                      <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-md">
                        <Package class="h-6 w-6 text-white" />
                      </div>
                      <div>
                        <Link 
                          :href="route('device-inventory.show', device.id)"
                          class="font-semibold text-gray-900 dark:text-gray-100 hover:text-purple-600 dark:hover:text-purple-400 transition-colors"
                        >
                          {{ device.serial_number }}
                        </Link>
                        <div class="flex items-center space-x-2 mt-1">
                          <Hash class="h-3 w-3 text-gray-400" />
                          <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                            {{ device.device_uuid }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </TableCell>

                  <!-- Modelo -->
                  <TableCell class="py-4">
                    <div class="flex items-center space-x-2">
                      <Cpu class="h-4 w-4 text-gray-400" />
                      <span class="font-medium text-gray-900 dark:text-gray-100">
                        {{ device.model }}
                      </span>
                    </div>
                  </TableCell>

                  <!-- Versiones -->
                  <TableCell class="py-4">
                    <div class="space-y-1">
                      <div v-if="device.hardware_version" class="flex items-center space-x-2">
                        <Settings class="h-4 w-4 text-gray-400" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                          HW: {{ device.hardware_version }}
                        </span>
                      </div>
                      <div v-if="device.firmware_version" class="flex items-center space-x-2">
                        <Cpu class="h-4 w-4 text-gray-400" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                          FW: {{ device.firmware_version }}
                        </span>
                      </div>
                      <div v-if="!device.hardware_version && !device.firmware_version">
                        <span class="text-sm text-gray-400 italic">No especificado</span>
                      </div>
                    </div>
                  </TableCell>

                  <!-- Estado -->
                  <TableCell class="py-4">
                    <Badge :class="getStatusBadge(device.status).class">
                      {{ getStatusBadge(device.status).text }}
                    </Badge>
                  </TableCell>

                  <!-- Asignación -->
                  <TableCell class="py-4">
                    <div v-if="device.client_devices_count && device.client_devices_count > 0" class="flex items-center space-x-2">
                      <Users class="h-4 w-4 text-blue-500" />
                      <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                        {{ device.client_devices_count }} {{ device.client_devices_count === 1 ? 'cliente' : 'clientes' }}
                      </span>
                    </div>
                    <div v-else class="flex items-center space-x-2">
                      <AlertTriangle class="h-4 w-4 text-gray-400" />
                      <span class="text-sm text-gray-400">Sin asignar</span>
                    </div>
                  </TableCell>

                  <!-- Fecha -->
                  <TableCell class="py-4">
                    <div class="flex items-center space-x-2">
                      <Calendar class="h-4 w-4 text-gray-400" />
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ new Date(device.created_at).toLocaleDateString('es-ES') }}
                      </span>
                    </div>
                  </TableCell>

                  <!-- Acciones -->
                  <TableCell class="py-4">
                    <div class="flex items-center justify-center space-x-1">
                      <Link 
                        v-if="device.can?.view"
                        :href="route('device-inventory.show', device.id)"
                        class="p-2 text-gray-400 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/50 rounded-lg transition-all"
                        title="Ver detalles"
                      >
                        <Eye class="h-4 w-4" />
                      </Link>
                      
                      <Link 
                        v-if="device.can?.update"
                        :href="route('device-inventory.edit', device.id)"
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
                          :href="route('device-inventory.show', device.id)" 
                          class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                          <Eye class="mr-3 h-4 w-4" />
                          Ver Detalles
                        </Link>
                        
                        <Link 
                          v-if="device.can?.update"
                          :href="route('device-inventory.edit', device.id)" 
                          class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                          <Edit class="mr-3 h-4 w-4" />
                          Editar Dispositivo
                        </Link>
                        
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
            v-if="(devices?.data?.length ?? 0) === 0 && !isLoading" 
            class="text-center py-20 px-6"
          >
            <div class="max-w-md mx-auto">
              <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                <Package class="h-12 w-12 text-gray-400" />
              </div>
              
              <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                {{ hasActiveFilters ? 'No se encontraron dispositivos' : 'Inventario vacío' }}
              </h3>
              
              <p class="text-gray-600 dark:text-gray-400 mb-8">
                {{ hasActiveFilters 
                  ? 'No encontramos dispositivos que coincidan con los filtros aplicados.'
                  : 'Comienza agregando dispositivos a tu inventario para gestionar el stock.' 
                }}
              </p>
              
              <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <Link 
                  v-if="can?.create_device && !hasActiveFilters"
                  :href="route('device-inventory.create')"
                >
                  <Button size="lg" class="bg-purple-600 hover:bg-purple-700 text-white shadow-lg">
                    <Plus class="mr-2 h-5 w-5" />
                    Agregar Primer Dispositivo
                  </Button>
                </Link>
                
                <Button 
                  v-if="hasActiveFilters" 
                  variant="outline" 
                  size="lg"
                  @click="clearFilters"
                >
                  <X class="mr-2 h-4 w-4" />
                  Limpiar Filtros
                </Button>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <div v-if="devices?.links?.length > 3 && devices?.data?.length > 0" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Mostrando <span class="font-semibold">{{ devices?.meta?.from || 0 }}</span> a 
                <span class="font-semibold">{{ devices?.meta?.to || 0 }}</span> de 
                <span class="font-semibold">{{ totalDevices.toLocaleString() }}</span> dispositivos
              </div>
              
              <div class="flex items-center space-x-2">
                <template v-for="link in devices?.links ?? []" :key="link.label">
                  <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                      'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200',
                      link.active
                        ? 'bg-purple-600 text-white shadow-md'
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
                        ? 'bg-purple-600 text-white'
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