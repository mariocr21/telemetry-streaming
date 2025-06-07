<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  ArrowLeft,
  Car,
  Save,
  AlertCircle,
  Info
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

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
  available_years: number[]
  common_makes: string[]
}

const props = defineProps<Props>()
const page = usePage()

// Formulario
const form = useForm({
  make: '',
  model: '',
  year: new Date().getFullYear(),
  license_plate: '',
  color: '',
  nickname: '',
  vin: '',
})

// Computadas
const errors = computed(() => page.props.errors as Record<string, string>)

// Métodos
const submit = () => {
  form.post(route('clients.devices.vehicles.store', [props.client.id, props.device.id]), {
    onSuccess: () => {
      // Redirigirá automáticamente al show del vehículo creado
    }
  })
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clients' },
  { title: props.client.full_name, href: `/clients/${props.client.id}` },
  { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
  { title: props.device.device_name, href: `/clients/${props.client.id}/devices/${props.device.id}` },
  { title: 'Vehículos', href: `/clients/${props.client.id}/devices/${props.device.id}/vehicles` },
  { title: 'Nuevo Vehículo', href: '#' }
]
</script>

<template>
  <Head :title="`Nuevo Vehículo - ${device.device_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.devices.vehicles.index', [client.id, device.id])">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver a Vehículos
            </Button>
          </Link>

          <div class="flex items-center space-x-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 shadow-lg">
              <Car class="h-8 w-8 text-white" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                Nuevo Vehículo
              </h1>
              <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Registrar manualmente un vehículo para el dispositivo {{ device.device_name }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Información del dispositivo -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center text-lg">
              <Info class="mr-2 h-5 w-5 text-blue-600" />
              Información del Dispositivo
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Nombre del Dispositivo</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ device.device_name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dirección MAC</p>
                <p class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ device.mac_address }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Estado</p>
                <p class="text-sm text-gray-900 dark:text-gray-100">{{ device.status }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

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
                />
                <p v-if="errors.vin" class="mt-1 text-sm text-red-600">{{ errors.vin }}</p>
                <p class="mt-1 text-xs text-gray-500">
                  Opcional. El VIN permite una identificación única del vehículo y puede ser detectado automáticamente por el dispositivo OBD2.
                </p>
              </div>

              <!-- Nota informativa -->
              <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <div class="flex">
                  <AlertCircle class="h-5 w-5 flex-shrink-0 text-blue-400" />
                  <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                      Información importante
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                      <ul class="list-disc space-y-1 pl-5">
                        <li>Este vehículo será registrado manualmente y marcado como configurado.</li>
                        <li>Los sensores se sincronizarán automáticamente cuando el dispositivo detecte los PIDs soportados.</li>
                        <li>Puedes editar esta información en cualquier momento.</li>
                        <li>Solo los campos marcados con (*) son obligatorios.</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Botones de acción -->
          <div class="flex justify-end space-x-4">
            <Link :href="route('clients.devices.vehicles.index', [client.id, device.id])">
              <Button variant="outline" type="button">
                Cancelar
              </Button>
            </Link>
            
            <Button type="submit" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? 'Guardando...' : 'Guardar Vehículo' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>