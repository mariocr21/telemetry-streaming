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
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue'
import { 
  Car,
  Plus,
  Eye,
  Edit,
  MoreVertical,
  CheckCircle2,
  AlertCircle,
  Trash2,
  Palette,
  FileText,
  Gauge,
  MapPin,
  Activity
} from 'lucide-vue-next'

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

interface Client {
  id: number
  full_name: string
  email: string
}

interface Device {
  id: number
  device_name: string
  vehicles?: Vehicle[]
}

interface Props {
  vehicles: Vehicle[]
  client: Client
  device: Device
}

const props = defineProps<Props>()

const emit = defineEmits<{
  editVehicle: [vehicleId: number]
  deleteVehicle: [vehicleId: number]
  configureSensors: [vehicleId: number]
  addVehicle: []
}>()

const getVehicleStatusBadge = (status?: string) => {
  const badges = {
    configured: { text: 'Configurado', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
    pending: { text: 'Pendiente', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
    error: { text: 'Error', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
    inactive: { text: 'Inactivo', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }
  }
  return badges[status as keyof typeof badges] || badges.pending
}

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

const handleEditVehicle = (vehicleId: number) => {
  emit('editVehicle', vehicleId)
}

const handleDeleteVehicle = (vehicleId: number) => {
  emit('deleteVehicle', vehicleId)
}

const handleConfigureSensors = (vehicleId: number) => {
  emit('configureSensors', vehicleId)
}

const handleAddVehicle = () => {
  emit('addVehicle')
}
</script>

<template>
  <!-- Lista de Vehículos Asociados -->
  <Card v-if="vehicles && vehicles.length > 0">
    <CardHeader>
      <div class="flex items-center justify-between">
        <CardTitle class="flex items-center text-lg">
          <Car class="mr-2 h-5 w-5 text-orange-600" />
          Vehículos Asociados ({{ vehicles.length }})
        </CardTitle>
        <Button size="sm" variant="outline" @click="handleAddVehicle">
          <Plus class="mr-2 h-4 w-4" />
          Agregar Vehículo
        </Button>
      </div>
    </CardHeader>
    <CardContent>
      <div class="space-y-4">
        <div
          v-for="vehicle in vehicles"
          :key="vehicle.id"
          class="rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600">
                  <Car class="h-5 w-5 text-white" />
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ vehicle.make }} {{ vehicle.model }} {{ vehicle.year }}
                    <span v-if="vehicle.nickname" class="text-sm text-gray-500">({{ vehicle.nickname }})</span>
                  </h3>
                  <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                    <span class="flex items-center space-x-1">
                      <MapPin class="h-3 w-3" />
                      <span>{{ vehicle.license_plate }}</span>
                    </span>
                    <span v-if="vehicle.color" class="flex items-center space-x-1">
                      <Palette class="h-3 w-3" />
                      <span>{{ vehicle.color }}</span>
                    </span>
                    <span v-if="vehicle.vin" class="flex items-center space-x-1">
                      <FileText class="h-3 w-3" />
                      <span class="font-mono">{{ vehicle.vin }}</span>
                    </span>
                  </div>
                </div>
              </div>

              <div class="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Estado</p>
                  <Badge :class="getVehicleStatusBadge(vehicle.status).class" class="mt-1">
                    {{ getVehicleStatusBadge(vehicle.status).text }}
                  </Badge>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Configuración</p>
                  <div class="mt-1 flex items-center space-x-1">
                    <CheckCircle2 v-if="vehicle.is_configured" class="h-4 w-4 text-green-500" />
                    <AlertCircle v-else class="h-4 w-4 text-yellow-500" />
                    <span class="text-sm">
                      {{ vehicle.is_configured ? 'Configurado' : 'Pendiente' }}
                    </span>
                  </div>
                </div>
                <div v-if="vehicle.sensors_count !== undefined">
                  <p class="text-xs text-gray-500 dark:text-gray-400">Sensores</p>
                  <div class="mt-1 flex items-center space-x-1">
                    <Gauge class="h-4 w-4 text-blue-500" />
                    <span class="text-sm">
                      {{ vehicle.active_sensors_count || 0 }}/{{ vehicle.sensors_count || 0 }}
                    </span>
                  </div>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Última Lectura</p>
                  <div class="mt-1 flex items-center space-x-1">
                    <Activity class="h-4 w-4 text-gray-400" />
                    <span class="text-sm">
                      {{ formatLastReading(vehicle.last_reading_at) }}
                    </span>
                  </div>
                </div>
              </div>

              <div v-if="vehicle.protocol" class="mt-3 flex items-center space-x-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Protocolo:</span>
                <Badge variant="secondary">{{ vehicle.protocol }}</Badge>
                <span v-if="vehicle.auto_detected" class="flex items-center space-x-1 text-green-600">
                  <CheckCircle2 class="h-3 w-3" />
                  <span class="text-xs">Auto-detectado</span>
                </span>
              </div>
            </div>

            <div class="flex items-center space-x-2">
              <Link :href="route('clients.devices.vehicles.show', [client.id, device.id, vehicle.id])">
                <Button size="sm" variant="ghost">
                  <Eye class="h-4 w-4" />
                </Button>
              </Link>
              <Button size="sm" variant="ghost" @click="handleEditVehicle(vehicle.id)">
                <Edit class="h-4 w-4" />
              </Button>
              <SimpleDropdown align="right">
                <template #trigger>
                  <Button size="sm" variant="ghost">
                    <MoreVertical class="h-4 w-4" />
                  </Button>
                </template>
                <Link
                  :href="route('clients.devices.vehicles.show', [client.id, device.id, vehicle.id])"
                  class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                  <Eye class="mr-2 h-4 w-4" />
                  Ver Detalles
                </Link>
                <button 
                  @click="handleEditVehicle(vehicle.id)"
                  class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                  <Edit class="mr-2 h-4 w-4" />
                  Editar Vehículo
                </button>
                <button 
                  @click="handleConfigureSensors(vehicle.id)"
                  class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                  <Gauge class="mr-2 h-4 w-4" />
                  Configurar Sensores
                </button>
                <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                <button 
                  @click="handleDeleteVehicle(vehicle.id)"
                  class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                >
                  <Trash2 class="mr-2 h-4 w-4" />
                  Desvincular Vehículo
                </button>
              </SimpleDropdown>
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>

  <!-- Mensaje cuando no hay vehículos -->
  <Card v-else>
    <CardHeader>
      <CardTitle class="flex items-center text-lg">
        <Car class="mr-2 h-5 w-5 text-orange-600" />
        Vehículos Asociados
      </CardTitle>
    </CardHeader>
    <CardContent>
      <div class="text-center py-6">
        <Car class="mx-auto h-12 w-12 text-gray-400" />
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
          No hay vehículos asociados
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Este dispositivo aún no tiene vehículos vinculados.
        </p>
        <div class="mt-4">
          <Button @click="handleAddVehicle">
            <Plus class="mr-2 h-4 w-4" />
            Agregar Primer Vehículo
          </Button>
        </div>
      </div>
    </CardContent>
  </Card>
</template>