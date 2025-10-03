<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { 
  ArrowLeft,
  Package,
  Save,
  X,
  Hash,
  Cpu,
  Settings,
  Calendar,
  FileText,
  AlertCircle
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface Device {
  id: number
  serial_number: string
  device_uuid: string
  model: string
  hardware_version: string | null
  firmware_version: string | null
  status: 'available' | 'sold' | 'maintenance' | 'retired'
  manufactured_date: string | null
  sold_date: string | null
  notes: string | null
}

const props = defineProps<{
  device: Device
}>()

const form = useForm({
  model: props.device.model,
  hardware_version: props.device.hardware_version || '',
  firmware_version: props.device.firmware_version || '',
  status: props.device.status,
  manufactured_date: props.device.manufactured_date || '',
  sold_date: props.device.sold_date || '',
  notes: props.device.notes || ''
})

const submit = () => {
  form.put(route('device-inventory.update', props.device.id))
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Inventario de Dispositivos', href: '/device-inventory' },
  { title: props.device.serial_number, href: `/device-inventory/${props.device.id}` },
  { title: 'Editar', href: `/device-inventory/${props.device.id}/edit` },
]
</script>

<template>
  <Head :title="`Editar Dispositivo ${device.serial_number}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Link :href="route('device-inventory.show', device.id)">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver al Dispositivo
            </Button>
          </Link>
          
          <div class="flex items-center space-x-4">
            <div class="p-3 bg-purple-100 dark:bg-purple-900/50 rounded-lg">
              <Package class="h-8 w-8 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                Editar Dispositivo
              </h1>
              <p class="text-gray-600 dark:text-gray-400 mt-1">
                {{ device.serial_number }}
              </p>
            </div>
          </div>
        </div>
        
        <div class="flex items-center space-x-3">
          <Link :href="route('device-inventory.show', device.id)">
            <Button variant="outline">
              <X class="mr-2 h-4 w-4" />
              Cancelar
            </Button>
          </Link>
          
          <Button 
            @click="submit" 
            :disabled="form.processing"
            class="bg-purple-600 hover:bg-purple-700"
          >
            <Save class="mr-2 h-4 w-4" />
            {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
          </Button>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <form @submit.prevent="submit" class="space-y-6">
          
          <!-- Información de Identificación (Solo Lectura) -->
          <Card class="bg-gray-50 dark:bg-gray-800/50">
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Hash class="mr-2 h-5 w-5 text-gray-600" />
                Información de Identificación
                <span class="ml-3 text-xs font-normal text-gray-500 bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">
                  Solo lectura
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="flex items-start space-x-2 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <AlertCircle class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                <p class="text-sm text-blue-800 dark:text-blue-200">
                  El número de serie y el UUID del dispositivo no pueden modificarse por seguridad e integridad del sistema.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Número de Serie (Solo Lectura) -->
                <div>
                  <Label for="serial_number">Número de Serie</Label>
                  <Input
                    id="serial_number"
                    :value="device.serial_number"
                    disabled
                    class="bg-gray-100 dark:bg-gray-700 cursor-not-allowed"
                  />
                  <p class="mt-1 text-sm text-gray-500">
                    Número de serie único del dispositivo
                  </p>
                </div>

                <!-- UUID del Dispositivo (Solo Lectura) -->
                <div>
                  <Label for="device_uuid">UUID del Dispositivo</Label>
                  <Input
                    id="device_uuid"
                    :value="device.device_uuid"
                    disabled
                    class="bg-gray-100 dark:bg-gray-700 cursor-not-allowed"
                  />
                  <p class="mt-1 text-sm text-gray-500">
                    Identificador único para la API
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Información Básica -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Package class="mr-2 h-5 w-5 text-purple-600" />
                Información Básica
              </CardTitle>
            </CardHeader>
            <CardContent>
              <!-- Modelo -->
              <div>
                <Label for="model">Modelo del Dispositivo *</Label>
                <Input
                  id="model"
                  v-model="form.model"
                  placeholder="Ej: OBD-Pro-X1, Tracker-GPS-V2"
                  :class="{ 'border-red-500 ring-red-500': form.errors.model }"
                  required
                />
                <p class="mt-1 text-sm text-gray-500">
                  Modelo comercial del dispositivo
                </p>
                <div v-if="form.errors.model" class="mt-1 text-sm text-red-600">
                  {{ form.errors.model }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Información Técnica -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Cpu class="mr-2 h-5 w-5 text-blue-600" />
                Información Técnica
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Versión de Hardware -->
                <div>
                  <Label for="hardware_version">Versión de Hardware</Label>
                  <div class="relative">
                    <Cpu class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                      id="hardware_version"
                      v-model="form.hardware_version"
                      placeholder="v1.0, v2.1, etc."
                      class="pl-10"
                      :class="{ 'border-red-500 ring-red-500': form.errors.hardware_version }"
                    />
                  </div>
                  <div v-if="form.errors.hardware_version" class="mt-1 text-sm text-red-600">
                    {{ form.errors.hardware_version }}
                  </div>
                </div>

                <!-- Versión de Firmware -->
                <div>
                  <Label for="firmware_version">Versión de Firmware</Label>
                  <div class="relative">
                    <Settings class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                      id="firmware_version"
                      v-model="form.firmware_version"
                      placeholder="1.0.0, 2.1.3, etc."
                      class="pl-10"
                      :class="{ 'border-red-500 ring-red-500': form.errors.firmware_version }"
                    />
                  </div>
                  <div v-if="form.errors.firmware_version" class="mt-1 text-sm text-red-600">
                    {{ form.errors.firmware_version }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Estado y Fechas -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Calendar class="mr-2 h-5 w-5 text-green-600" />
                Estado y Fechas
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Estado -->
                <div>
                  <Label for="status">Estado *</Label>
                  <select
                    id="status"
                    v-model="form.status"
                    :class="[
                      'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                      { 'border-red-500 ring-red-500': form.errors.status }
                    ]"
                    required
                  >
                    <option value="available">Disponible</option>
                    <option value="sold">Vendido</option>
                    <option value="maintenance">Mantenimiento</option>
                    <option value="retired">Retirado</option>
                  </select>
                  <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                    {{ form.errors.status }}
                  </div>
                </div>

                <!-- Fecha de Fabricación -->
                <div>
                  <Label for="manufactured_date">Fecha de Fabricación</Label>
                  <Input
                    id="manufactured_date"
                    v-model="form.manufactured_date"
                    type="date"
                    :class="{ 'border-red-500 ring-red-500': form.errors.manufactured_date }"
                  />
                  <div v-if="form.errors.manufactured_date" class="mt-1 text-sm text-red-600">
                    {{ form.errors.manufactured_date }}
                  </div>
                </div>

                <!-- Fecha de Venta -->
                <div>
                  <Label for="sold_date">Fecha de Venta</Label>
                  <Input
                    id="sold_date"
                    v-model="form.sold_date"
                    type="date"
                    :class="{ 'border-red-500 ring-red-500': form.errors.sold_date }"
                    :disabled="form.status !== 'sold'"
                  />
                  <p class="mt-1 text-sm text-gray-500">
                    Solo aplica si el estado es "Vendido"
                  </p>
                  <div v-if="form.errors.sold_date" class="mt-1 text-sm text-red-600">
                    {{ form.errors.sold_date }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Notas -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <FileText class="mr-2 h-5 w-5 text-orange-600" />
                Notas Adicionales
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div>
                <Label for="notes">Notas</Label>
                <Textarea
                  id="notes"
                  v-model="form.notes"
                  placeholder="Información adicional sobre el dispositivo..."
                  :rows="4"
                  :class="{ 'border-red-500 ring-red-500': form.errors.notes }"
                />
                <p class="mt-1 text-sm text-gray-500">
                  Información adicional, observaciones o comentarios sobre el dispositivo
                </p>
                <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                  {{ form.errors.notes }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Resumen de Cambios -->
          <Card class="bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800">
            <CardHeader>
              <CardTitle class="flex items-center text-lg text-purple-800 dark:text-purple-200">
                <Package class="mr-2 h-5 w-5" />
                Resumen del Dispositivo
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="font-semibold text-purple-800 dark:text-purple-200 mb-2">
                    Identificación
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Serie:</span> {{ device.serial_number }}</div>
                    <div><span class="font-medium">UUID:</span> {{ device.device_uuid }}</div>
                    <div><span class="font-medium">Modelo:</span> {{ form.model || 'Sin definir' }}</div>
                  </div>
                </div>
                
                <div>
                  <h4 class="font-semibold text-purple-800 dark:text-purple-200 mb-2">
                    Estado y Versiones
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Estado:</span> {{ form.status === 'available' ? 'Disponible' : form.status === 'sold' ? 'Vendido' : form.status === 'maintenance' ? 'Mantenimiento' : 'Retirado' }}</div>
                    <div><span class="font-medium">Hardware:</span> {{ form.hardware_version || 'No especificado' }}</div>
                    <div><span class="font-medium">Firmware:</span> {{ form.firmware_version || 'No especificado' }}</div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Botones de Acción -->
          <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('device-inventory.show', device.id)">
              <Button variant="outline" type="button">
                <X class="mr-2 h-4 w-4" />
                Cancelar
              </Button>
            </Link>
            
            <Button 
              type="submit" 
              :disabled="form.processing"
              class="bg-purple-600 hover:bg-purple-700"
            >
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>