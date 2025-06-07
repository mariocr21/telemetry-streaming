<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/components/ui/button'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  Edit,
  Copy,
  Plus,
  Zap,
  Settings,
  Trash2,
  Clock,
  Calendar,
  CheckCircle2,
  AlertCircle,
  Car,
  Activity
} from 'lucide-vue-next'

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
  device: Device
  client: Client
  isOnline: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  copyToClipboard: [text: string, type: string]
  activateDevice: []
  deactivateDevice: []
  deleteDevice: []
  addVehicle: []
}>()

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

const lastUpdated = computed(() => {
  const updatedDate = new Date(props.device.updated_at)
  const now = new Date()
  const diffTime = Math.abs(now.getTime() - updatedDate.getTime())
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return 'Hoy'
  } else if (diffDays === 1) {
    return 'Ayer'
  } else if (diffDays < 7) {
    return `Hace ${diffDays} días`
  } else {
    return updatedDate.toLocaleDateString('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    })
  }
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

const formatLastReading = (dateString?: string) => {
  if (!dateString) return 'Sin lecturas'
  
  const date = new Date(dateString)
  const now = new Date()
  const diffHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60)
  
  if (diffHours < 1) {
    const diffMinutes = Math.floor(diffHours * 60)
    return `Hace ${diffMinutes} minutos`
  } else if (diffHours < 24) {
    return `Hace ${Math.floor(diffHours)} horas`
  } else {
    const diffDays = Math.floor(diffHours / 24)
    return `Hace ${diffDays} días`
  }
}

const handleCopyToClipboard = (text: string, type: string) => {
  emit('copyToClipboard', text, type)
}

const handleActivateDevice = () => {
  emit('activateDevice')
}

const handleDeactivateDevice = () => {
  emit('deactivateDevice')
}

const handleDeleteDevice = () => {
  emit('deleteDevice')
}

const handleAddVehicle = () => {
  emit('addVehicle')
}
</script>

<template>
  <div class="space-y-6">
    <!-- Acciones Rápidas -->
    <Card>
      <CardHeader>
        <CardTitle class="text-lg">Acciones Rápidas</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <Link v-if="device.can?.update" :href="route('clients.devices.edit', [client.id, device.id])">
          <Button class="w-full justify-start" variant="outline">
            <Edit class="mr-2 h-4 w-4" />
            Editar Dispositivo
          </Button>
        </Link>

        <Button
          @click="handleCopyToClipboard(device.mac_address, 'mac')"
          class="w-full justify-start"
          variant="outline"
        >
          <Copy class="mr-2 h-4 w-4" />
          Copiar MAC
        </Button>

        <Button
          @click="handleAddVehicle"
          class="w-full justify-start"
          variant="outline"
        >
          <Plus class="mr-2 h-4 w-4" />
          Agregar Vehículo
        </Button>

        <Button
          v-if="device.status === 'pending' || device.status === 'inactive'"
          @click="handleActivateDevice"
          class="w-full justify-start text-green-600 hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-900/20"
          variant="outline"
        >
          <Zap class="mr-2 h-4 w-4" />
          Activar Dispositivo
        </Button>

        <Button
          v-if="device.status === 'active'"
          @click="handleDeactivateDevice"
          class="w-full justify-start text-orange-600 hover:bg-orange-50 hover:text-orange-700 dark:hover:bg-orange-900/20"
          variant="outline"
        >
          <Settings class="mr-2 h-4 w-4" />
          Desactivar Dispositivo
        </Button>

        <div v-if="device.can?.delete" class="border-t border-gray-200 pt-3 dark:border-gray-700">
          <Button
            @click="handleDeleteDevice"
            class="w-full justify-start text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
            variant="outline"
          >
            <Trash2 class="mr-2 h-4 w-4" />
            Eliminar Dispositivo
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
          <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">ID del Dispositivo</h4>
          <p class="rounded bg-gray-100 px-2 py-1 font-mono text-sm dark:bg-gray-800">#{{ device.id }}</p>
        </div>

        <div>
          <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Registro</h4>
          <div class="flex items-center space-x-2">
            <Calendar class="h-4 w-4 text-gray-400" />
            <div>
              <p class="font-medium">
                {{ new Date(device.created_at).toLocaleDateString('es-ES', {
                  weekday: 'long',
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                }) }}
              </p>
              <p class="text-sm text-gray-500">
                {{ new Date(device.created_at).toLocaleTimeString('es-ES', {
                  hour: '2-digit',
                  minute: '2-digit'
                }) }}
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
                {{ new Date(device.updated_at).toLocaleTimeString('es-ES', {
                  hour: '2-digit',
                  minute: '2-digit'
                }) }}
              </p>
            </div>
          </div>
        </div>

        <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Tiempo registrado</span>
            <Badge variant="secondary">{{ deviceAge }}</Badge>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Estado del Dispositivo -->
    <Card>
      <CardHeader>
        <CardTitle class="text-lg">Estado del Dispositivo</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-sm">Configuración básica</span>
            <CheckCircle2 class="h-4 w-4 text-green-500" />
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Hardware asignado</span>
            <CheckCircle2 v-if="device.device_inventory" class="h-4 w-4 text-green-500" />
            <AlertCircle v-else class="h-4 w-4 text-amber-500" />
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Estado de activación</span>
            <CheckCircle2 v-if="device.status === 'active'" class="h-4 w-4 text-green-500" />
            <AlertCircle v-else-if="device.status === 'pending'" class="h-4 w-4 text-yellow-500" />
            <AlertCircle v-else class="h-4 w-4 text-red-500" />
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Conectividad</span>
            <CheckCircle2 v-if="isOnline" class="h-4 w-4 text-green-500" />
            <AlertCircle v-else class="h-4 w-4 text-gray-400" />
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Vehículos asignados</span>
            <div class="flex items-center space-x-2">
              <CheckCircle2 v-if="device.vehicles && device.vehicles.length > 0" class="h-4 w-4 text-green-500" />
              <AlertCircle v-else class="h-4 w-4 text-gray-400" />
              <span class="text-xs text-gray-500">
                {{ device.vehicles?.length || 0 }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Vehículos configurados</span>
            <div class="flex items-center space-x-2">
              <CheckCircle2 v-if="vehiclesSummary.configured > 0" class="h-4 w-4 text-green-500" />
              <AlertCircle v-else class="h-4 w-4 text-gray-400" />
              <span class="text-xs text-gray-500">
                {{ vehiclesSummary.configured }}/{{ vehiclesSummary.total }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Configuración avanzada</span>
            <CheckCircle2 v-if="device.device_config" class="h-4 w-4 text-green-500" />
            <AlertCircle v-else class="h-4 w-4 text-gray-400" />
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm">Actividad reciente</span>
            <div class="flex items-center space-x-2">
              <CheckCircle2 v-if="vehiclesSummary.recentActivity > 0" class="h-4 w-4 text-green-500" />
              <AlertCircle v-else class="h-4 w-4 text-gray-400" />
              <span class="text-xs text-gray-500">
                {{ vehiclesSummary.recentActivity }} vehículo{{ vehiclesSummary.recentActivity !== 1 ? 's' : '' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Progreso general -->
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
          <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-gray-500">Configuración completada</span>
            <span class="font-medium">
              {{ Math.round((vehiclesSummary.configured / Math.max(vehiclesSummary.total, 1)) * 100) }}%
            </span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
            <div 
              class="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full transition-all duration-300"
              :style="{ width: `${Math.round((vehiclesSummary.configured / Math.max(vehiclesSummary.total, 1)) * 100)}%` }"
            ></div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Estadísticas de Vehículos -->
    <Card v-if="device.vehicles && device.vehicles.length > 0">
      <CardHeader>
        <CardTitle class="text-lg">Estadísticas de Vehículos</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ vehiclesSummary.total }}
              </div>
              <div class="text-xs text-gray-500">Total</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ vehiclesSummary.configured }}
              </div>
              <div class="text-xs text-gray-500">Configurados</div>
            </div>
          </div>

          <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-gray-500">Por año de fabricación</span>
            </div>
            <div class="space-y-2">
              <template v-for="year in [...new Set(device.vehicles?.map(v => v.year))].sort().reverse()" :key="year">
                <div class="flex items-center justify-between text-sm">
                  <span>{{ year }}</span>
                  <Badge variant="secondary" class="text-xs">
                    {{ device.vehicles?.filter(v => v.year === year).length }}
                  </Badge>
                </div>
              </template>
            </div>
          </div>

          <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-gray-500">Por marca</span>
            </div>
            <div class="space-y-2">
              <template v-for="make in [...new Set(device.vehicles?.map(v => v.make))].sort()" :key="make">
                <div class="flex items-center justify-between text-sm">
                  <span>{{ make }}</span>
                  <Badge variant="secondary" class="text-xs">
                    {{ device.vehicles?.filter(v => v.make === make).length }}
                  </Badge>
                </div>
              </template>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Últimas Lecturas -->
    <Card v-if="device.vehicles && device.vehicles.length > 0">
      <CardHeader>
        <CardTitle class="flex items-center text-lg">
          <Activity class="mr-2 h-5 w-5 text-purple-600" />
          Actividad Reciente
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-3">
          <template v-for="vehicle in device.vehicles?.filter(v => v.last_reading_at).sort((a, b) => new Date(b.last_reading_at || 0).getTime() - new Date(a.last_reading_at || 0).getTime()).slice(0, 5)" :key="vehicle.id">
            <div class="flex items-center space-x-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/20">
                <Car class="h-4 w-4 text-purple-600 dark:text-purple-400" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                  {{ vehicle.make }} {{ vehicle.model }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatLastReading(vehicle.last_reading_at) }}
                </p>
              </div>
              <div class="flex items-center space-x-1">
                <div class="h-2 w-2 rounded-full bg-green-400" v-if="vehicle.last_reading_at && new Date(vehicle.last_reading_at).getTime() > Date.now() - 3600000"></div>
                <div class="h-2 w-2 rounded-full bg-yellow-400" v-else-if="vehicle.last_reading_at && new Date(vehicle.last_reading_at).getTime() > Date.now() - 86400000"></div>
                <div class="h-2 w-2 rounded-full bg-red-400" v-else></div>
              </div>
            </div>
          </template>

          <div v-if="!device.vehicles?.some(v => v.last_reading_at)" class="text-center py-4">
            <Activity class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
              No hay actividad reciente
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>