<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  ArrowLeft,
  Car,
  Save,
  AlertCircle,
  Info,
  CheckCircle2
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface Vehicle {
  id: number
  make?: string
  model?: string
  year?: number
  license_plate?: string
  color?: string
  nickname?: string
  vin?: string
  auto_detected: boolean
  is_configured: boolean
  status: boolean
}

interface Device {
  id: number
  device_name: string
  mac_address: string
  status: string
}

interface Client {
  id: number
  full_name: string
  email: string
}

interface Props {
  client: Client
  device: Device
  vehicle: Vehicle
  available_years: number[]
  common_makes: string[]
}

const props = defineProps<Props>()
const page = usePage()

// Formulario
const form = useForm({
  make: props.vehicle.make || '',
  model: props.vehicle.model || '',
  year: props.vehicle.year || new Date().getFullYear(),
  license_plate: props.vehicle.license_plate || '',
  color: props.vehicle.color || '',
  nickname: props.vehicle.nickname || '',
  vin: props.vehicle.vin || '',
})

// Computadas
const errors = computed(() => page.props.errors as Record<string, string>)

const vehicleDisplayName = computed(() => {
  if (props.vehicle.nickname) {
    return `"${props.vehicle.nickname}"`
  }

  const parts = []
  if (props.vehicle.make) parts.push(props.vehicle.make)
  if (props.vehicle.model) parts.push(props.vehicle.model)
  if (props.vehicle.year) parts.push(props.vehicle.year.toString())
  return parts.length > 0 ? parts.join(' ') : 'Vehículo'
})

const getStatusBadge = (status: boolean) => {
  return status
    ? { text: 'Activo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }
    : { text: 'Inactivo', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }
}

const getDetectionBadge = (autoDetected: boolean) => {
  return autoDetected
    ? { text: 'Auto-detectado', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }
    : { text: 'Manual', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }
}

// Métodos
const submit = () => {
  form.put(route('clients.devices.vehicles.update', [props.client.id, props.device.id, props.vehicle.id]), {
    onSuccess: () => {
      // Redirigirá automáticamente al show del vehículo
    }
  })
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clients' },
  { title: props.client.full_name, href: `/clients/${props.client.id}` },
  { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
  { title: props.device.device_name, href: `/clients/${props.client.id}/devices/${props.device.id}` },
  { title: 'Vehículos', href: `/clients/${props.client.id}/devices/${props.device.id}/vehicles` },
  { title: vehicleDisplayName.value, href: `/clients/${props.client.id}/devices/${props.device.id}/vehicles/${props.vehicle.id}` },
  { title: 'Editar', href: '#' }
]
</script>

<template>
  <Head :title="`Editar ${vehicleDisplayName} - ${device.device_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.devices.vehicles.show', [client.id, device.id, vehicle.id])">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver al Vehículo
            </Button>
          </Link>

          <div class="flex items-center space-x-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 shadow-lg">
              <Car class="h-8 w-8 text-white" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                Editar {{ vehicleDisplayName }}
              </h1>
              <div class="mt-2 flex items-center space-x-4">
                <Badge :class="getStatusBadge(vehicle.status).class">
                  {{ getStatusBadge(vehicle.status).text }}
                </Badge>
                <Badge :class="getDetectionBadge(vehicle.auto_detected).class">
                  {{ getDetectionBadge(vehicle.auto_detected).text }}
                </Badge>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Estado del vehículo -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center text-lg">
              <Info class="mr-2 h-5 w-5 text-blue-600" />
              Estado Actual
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Estado</p>
                <Badge :class="getStatusBadge(vehicle.status).class" class="mt-1">
                  {{ getStatusBadge(vehicle.status).text }}
                </Badge>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tipo de Registro</p>
                <Badge :class="getDetectionBadge(vehicle.auto_detected).class" class="mt-1">
                  {{ getDetectionBadge(vehicle.auto_detected).text }}
                </Badge>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Configuración</p>
                <div class="mt-1 flex items-center space-x-2">
                  <CheckCircle2 v-if="vehicle.is_configured" class="h-4 w-4 text-green-500" />
                  <AlertCircle v-else class="h-4 w-4 text-yellow-500" />
                  <span class="text-sm">
                    {{ vehicle.is_configured ? 'Configurado' : 'Pendiente' }}
                  </span>
                </div>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dispositivo</p>
                <Link
                  :href="route('clients.devices.show', [client.id, device.id])"
                  class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                >
                  {{ device.device_name }}
                </Link>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Alerta para vehículos auto-detectados -->
        <div v-if="vehicle.auto_detected" class="rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
          <div class="flex">
            <AlertCircle class="h-5 w-5 flex-shrink-0 text-yellow-400" />
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                Vehículo Auto-detectado
              </h3>
              <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p>
                  Este vehículo fue registrado automáticamente por el dispositivo OBD2. 
                  Puedes completar o corregir la información, pero algunos datos como el VIN 
                  pueden ser sobrescritos automáticamente si el dispositivo los detecta nuevamente.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="submit">
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Car class="mr-2 h-5 w-5 text-orange-600" />
                Información del Vehículo
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              
              <!-- Información básica -->
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <label for="make" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Marca *
                  </label>
                  <select
                    id="make"
                    v-model="form.make"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    :class="{ 'border-red-500': errors.make }"
                  >
                    <option value="">Selecciona una marca</option>
                    <option v-for="make in common_makes" :key="make" :value="make">
                      {{ make }}
                    </option>
                    <option value="other">Otra (escribir en modelo)</option>
                  </select>
                  <p v-if="errors.make" class="mt-1 text-sm text-red-600">{{ errors.make }}</p>
                </div>

                <div>
                  <label for="model" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Modelo *
                  </label>
                  <input
                    id="model"
                    v-model="form.model"
                    type="text"
                    placeholder="Ej: Civic, Corolla, F-150"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    :class="{ 'border-red-500': errors.model }"
                  />
                  <p v-if="errors.model" class="mt-1 text-sm text-red-600">{{ errors.model }}</p>
                  <p v-if="form.make === 'other'" class="mt-1 text-xs text-gray-500">
                    Si seleccionaste "Otra" en marca, incluye la marca aquí: "Marca Modelo"
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Año *
                  </label>
                  <select
                    id="year"
                    v-model="form.year"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    :class="{ 'border-red-500': errors.year }"
                  >
                    <option v-for="year in available_years" :key="year" :value="year">
                      {{ year }}
                    </option>
                  </select>
                  <p v-if="errors.year" class="mt-1 text-sm text-red-600">{{ errors.year }}</p>
                </div>

                <div>
                  <label for="license_plate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Placa
                  </label>
                  <input
                    id="license_plate"
                    v-model="form.license_plate"
                    type="text"
                    placeholder="Ej: ABC-123"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    :class="{ 'border-red-500': errors.license_plate }"
                  />
                  <p v-if="errors.license_plate" class="mt-1 text-sm text-red-600">{{ errors.license_plate }}</p>
                </div>
              </div>

              <!-- Información adicional -->
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Color
                  </label>
                  <input
                    id="color"
                    v-model="form.color"
                    type="text"
                    placeholder="Ej: Blanco, Negro, Azul"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    :class="{ 'border-red-500': errors.color }"
                  />
                  <p v-if="errors.color" class="mt-1 text-sm text-red-600">{{ errors.color }}</p>
                </div>

                <div>
                  <label for="nickname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Apodo / Nickname
                  </label>
                  <input
                    id="nickname"
                    v-model="form.nickname"
                    type="text"
                    placeholder="Ej: Mi auto, El rápido"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    :class="{ 'border-red-500': errors.nickname }"
                  />
                  <p v-if="errors.nickname" class="mt-1 text-sm text-red-600">{{ errors.nickname }}</p>
                  <p class="mt-1 text-xs text-gray-500">
                    Nombre personalizado para identificar fácilmente tu vehículo
                  </p>
                </div>
              </div>

              <!-- VIN -->
              <div>
                <label for="vin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  VIN (Número de Identificación Vehicular)
                </label>
                <input
                  id="vin"
                  v-model="form.vin"
                  type="text"
                  placeholder="17 caracteres - Ej: 1HGBH41JXMN109186"
                  maxlength="17"
                  class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                  :class="{ 'border-red-500': errors.vin }"
                  :disabled="!!(vehicle.auto_detected && vehicle.vin)"
                />
                <p v-if="errors.vin" class="mt-1 text-sm text-red-600">{{ errors.vin }}</p>
                <p v-if="vehicle.auto_detected && vehicle.vin" class="mt-1 text-xs text-yellow-600">
                  El VIN fue detectado automáticamente y no puede ser modificado manualmente.
                </p>
                <p v-else class="mt-1 text-xs text-gray-500">
                  Opcional. El VIN permite una identificación única del vehículo.
                </p>
              </div>

              <!-- Nota informativa -->
              <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <div class="flex">
                  <Info class="h-5 w-5 flex-shrink-0 text-blue-400" />
                  <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                      Información sobre la edición
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                      <ul class="list-disc space-y-1 pl-5">
                        <li>Los cambios se guardarán inmediatamente.</li>
                        <li v-if="vehicle.auto_detected">
                          Para vehículos auto-detectados, algunos datos pueden ser actualizados automáticamente por el dispositivo.
                        </li>
                        <li>Solo los campos marcados con (*) son obligatorios.</li>
                        <li>El estado del vehículo se puede cambiar desde las acciones del menú principal.</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Botones de acción -->
          <div class="flex justify-end space-x-4">
            <Link :href="route('clients.devices.vehicles.show', [client.id, device.id, vehicle.id])">
              <Button variant="outline" type="button">
                Cancelar
              </Button>
            </Link>
            
            <Button type="submit" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>