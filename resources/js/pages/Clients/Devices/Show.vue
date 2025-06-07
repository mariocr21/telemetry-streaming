<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue'

// Importar componentes segmentados
import DeviceInfo from '@/components/devices/DeviceInfo.vue'
import DeviceHardware from '@/components/devices/DeviceHardware.vue'
import DeviceConnectivity from '@/components/devices/DeviceConnectivity.vue'
import DeviceVehiclesList from '@/components/devices/DeviceVehiclesList.vue'
import DeviceSidebar from '@/components/devices/DeviceSidebar.vue'

import { 
  ArrowLeft,
  Smartphone,
  Edit,
  Trash2,
  MoreVertical,
  Wifi,
  WifiOff,
  Car,
  Zap,
  Settings,
  CheckCircle2,
  Copy,
  Plus,
  Activity
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface DeviceInventory {
  id: number
  serial_number: string
  device_uuid: string
  model: string
  hardware_version: string
  firmware_version: string
  manufactured_date?: string
  sold_date?: string
}

interface Vehicle {
  id: number
  make: string
  model: string
  year: number
  license_plate: string
  color?: string
  nickname?: string
  vin?: string
  protocol?: string
  status?: string
  auto_detected?: boolean
  is_configured?: boolean
  first_reading_at?: string
  last_reading_at?: string
  created_at: string
  sensors_count?: number
  active_sensors_count?: number
  supported_pids?: any
}

interface Device {
  id: number
  device_name: string
  mac_address: string
  status: string
  activated_at?: string
  last_ping?: string
  device_config?: any
  created_at: string
  updated_at: string
  device_inventory?: DeviceInventory
  vehicles?: Vehicle[]
  vehicles_count?: number
  can: {
    view: boolean
    update: boolean
    delete: boolean
  }
}

interface Client {
  id: number
  full_name: string
  email: string
}

interface Props {
  client: Client
  device: Device
}

const props = defineProps<Props>()
const page = usePage()

// Estado reactivo
const copied = ref('')

// Computadas
const flashMessage = computed(() => {
  const flash = page.props.flash as any
  return flash?.message
})

const getStatusBadge = (status: string) => {
  const badges = {
    pending: { text: 'Pendiente', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
    active: { text: 'Activo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
    inactive: { text: 'Inactivo', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
    maintenance: { text: 'Mantenimiento', class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
    retired: { text: 'Retirado', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }
  }
  return badges[status as keyof typeof badges] || badges.pending
}

const deviceAge = computed(() => {
  const createdDate = new Date(props.device.created_at)
  const now = new Date()
  const diffTime = Math.abs(now.getTime() - createdDate.getTime())
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays < 30) {
    return `${diffDays} días`
  } else if (diffDays < 365) {
    const months = Math.floor(diffDays / 30)
    return `${months} ${months === 1 ? 'mes' : 'meses'}`
  } else {
    const years = Math.floor(diffDays / 365)
    return `${years} ${years === 1 ? 'año' : 'años'}`
  }
})

const isOnline = computed(() => {
  if (!props.device.last_ping) return false
  
  const lastPing = new Date(props.device.last_ping)
  const now = new Date()
  const diffMinutes = (now.getTime() - lastPing.getTime()) / (1000 * 60)
  
  return diffMinutes < 10 // Consideramos online si el último ping fue hace menos de 10 minutos
})

const vehiclesSummary = computed(() => {
  const vehicles = props.device.vehicles || []
  const totalVehicles = vehicles.length
  const configuredVehicles = vehicles.filter(v => v.is_configured).length
  const activeVehicles = vehicles.filter(v => v.status === 'configured').length
  const recentActivity = vehicles.filter(v => {
    if (!v.last_reading_at) return false
    const lastReading = new Date(v.last_reading_at)
    const now = new Date()
    const diffHours = (now.getTime() - lastReading.getTime()) / (1000 * 60 * 60)
    return diffHours < 24
  }).length

  return {
    total: totalVehicles,
    configured: configuredVehicles,
    active: activeVehicles,
    recentActivity
  }
})

// Métodos de eventos
const copyToClipboard = async (text: string, type: string) => {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = type
    setTimeout(() => {
      copied.value = ''
    }, 2000)
  } catch (err) {
    console.error('Error al copiar:', err)
  }
}

const deleteDevice = () => {
  if (confirm(`¿Estás seguro de que deseas eliminar el dispositivo ${props.device.device_name}?`)) {
    router.delete(route('clients.devices.destroy', [props.client.id, props.device.id]), {
      onSuccess: () => {
        router.visit(route('clients.devices.index', props.client.id))
      }
    })
  }
}

const activateDevice = () => {
  router.post(route('clients.devices.activate', [props.client.id, props.device.id]))
}

const deactivateDevice = () => {
  router.post(route('clients.devices.deactivate', [props.client.id, props.device.id]))
}

// Métodos para manejar vehículos
const handleEditVehicle = (vehicleId: number) => {
  router.visit(route('clients.devices.vehicles.edit', [props.client.id, props.device.id, vehicleId]))
}

const handleDeleteVehicle = (vehicleId: number) => {
  if (confirm('¿Estás seguro de que deseas desvincular este vehículo?')) {
    router.delete(route('clients.devices.vehicles.destroy', [props.client.id, props.device.id, vehicleId]))
  }
}

const handleConfigureSensors = (vehicleId: number) => {
  router.visit(route('clients.devices.vehicles.sensors', [props.client.id, props.device.id, vehicleId]))
}

const handleAddVehicle = () => {
  router.visit(route('clients.devices.vehicles.create', [props.client.id, props.device.id]))
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clients' },
  { title: props.client.full_name, href: `/clients/${props.client.id}` },
  { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
  { title: props.device.device_name, href: `/clients/${props.client.id}/devices/${props.device.id}` }
]
</script>

<template>
  <Head :title="`${device.device_name} - ${client.full_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.devices.index', client.id)">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver a Dispositivos
            </Button>
          </Link>

          <div class="flex items-center space-x-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 shadow-lg">
              <Smartphone class="h-8 w-8 text-white" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                {{ device.device_name }}
              </h1>
              <div class="mt-2 flex items-center space-x-4">
                <Badge :class="getStatusBadge(device.status).class">
                  {{ getStatusBadge(device.status).text }}
                </Badge>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  Registrado hace {{ deviceAge }}
                </span>
                <div v-if="isOnline" class="flex items-center space-x-1 text-green-600">
                  <Wifi class="h-4 w-4" />
                  <span class="text-sm">En línea</span>
                </div>
                <div v-else class="flex items-center space-x-1 text-gray-400">
                  <WifiOff class="h-4 w-4" />
                  <span class="text-sm">Desconectado</span>
                </div>
                <div v-if="device.vehicles_count" class="flex items-center space-x-1 text-blue-600">
                  <Car class="h-4 w-4" />
                  <span class="text-sm">{{ device.vehicles_count }} vehículo{{ device.vehicles_count > 1 ? 's' : '' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <Link v-if="device.can?.update" :href="route('clients.devices.edit', [client.id, device.id])">
            <Button variant="outline" size="sm">
              <Edit class="mr-2 h-4 w-4" />
              Editar
            </Button>
          </Link>

          <SimpleDropdown align="right">
            <template #trigger>
              <Button variant="outline" size="sm">
                <MoreVertical class="h-4 w-4" />
              </Button>
            </template>

            <Link
              v-if="device.can?.update"
              :href="route('clients.devices.edit', [client.id, device.id])"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Edit class="mr-2 h-4 w-4" />
              Editar Dispositivo
            </Link>

            <button
              @click="copyToClipboard(device.mac_address, 'mac')"
              class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Copy class="mr-2 h-4 w-4" />
              Copiar MAC
            </button>

            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

            <button
              v-if="device.status === 'pending' || device.status === 'inactive'"
              @click="activateDevice"
              class="flex w-full items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
            >
              <Zap class="mr-2 h-4 w-4" />
              Activar Dispositivo
            </button>

            <button
              v-if="device.status === 'active'"
              @click="deactivateDevice"
              class="flex w-full items-center px-4 py-2 text-sm text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20"
            >
              <Settings class="mr-2 h-4 w-4" />
              Desactivar Dispositivo
            </button>

            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

            <button
              v-if="device.can?.delete"
              @click="deleteDevice"
              class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Eliminar Dispositivo
            </button>
          </SimpleDropdown>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Mensajes Flash -->
        <div
          v-if="flashMessage"
          class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-800 dark:bg-green-900/20"
        >
          <div class="flex items-center">
            <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-green-400" />
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800 dark:text-green-200">
                {{ flashMessage }}
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
                {{ copied === 'mac' ? 'Dirección MAC copiada al portapapeles' : 'Información copiada' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Resumen de Vehículos -->
        <div v-if="device.vehicles_count && device.vehicles_count > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center">
              <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                <Car class="h-6 w-6 text-blue-600 dark:text-blue-400" />
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Vehículos</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehiclesSummary.total }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center">
              <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/20">
                <CheckCircle2 class="h-6 w-6 text-green-600 dark:text-green-400" />
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Configurados</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehiclesSummary.configured }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center">
              <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                <Zap class="h-6 w-6 text-orange-600 dark:text-orange-400" />
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Activos</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehiclesSummary.active }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center">
              <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/20">
                <Activity class="h-6 w-6 text-purple-600 dark:text-purple-400" />
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Actividad Reciente</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehiclesSummary.recentActivity }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Contenido principal -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <!-- Columna principal -->
          <div class="space-y-6 lg:col-span-2">
            <!-- Información del Dispositivo -->
            <DeviceInfo 
              :device="device" 
              :client="client" 
              @copy-to-clipboard="copyToClipboard" 
            />

            <!-- Información del Hardware -->
            <DeviceHardware 
              v-if="device.device_inventory" 
              :device-inventory="device.device_inventory" 
            />

            <!-- Estado de Conectividad -->
            <DeviceConnectivity :device="device" />

            <!-- Lista de Vehículos Asociados -->
            <DeviceVehiclesList 
              :vehicles="device.vehicles || []"
              :client="client"
              :device="device"
              @edit-vehicle="handleEditVehicle"
              @delete-vehicle="handleDeleteVehicle"
              @configure-sensors="handleConfigureSensors"
              @add-vehicle="handleAddVehicle"
            />

            <!-- Configuración Adicional -->
            <Card v-if="device.device_config">
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Settings class="mr-2 h-5 w-5 text-gray-600" />
                  Configuración Adicional
                </CardTitle>
              </CardHeader>
              <CardContent>
                <pre class="rounded-lg bg-gray-100 p-4 text-sm overflow-x-auto dark:bg-gray-800">{{ JSON.stringify(device.device_config, null, 2) }}</pre>
              </CardContent>
            </Card>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <DeviceSidebar 
              :device="device"
              :client="client"
              :is-online="isOnline"
              @copy-to-clipboard="copyToClipboard"
              @activate-device="activateDevice"
              @deactivate-device="deactivateDevice"
              @delete-device="deleteDevice"
              @add-vehicle="handleAddVehicle"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>