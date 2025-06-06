<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
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
  Smartphone,
  Save,
  X,
  Cpu,
  Hash,
  Wifi,
  Settings
} from 'lucide-vue-next'
import type { DeviceCreateProps, BreadcrumbItem } from '@/types'

const props = defineProps<DeviceCreateProps>()

const form = useForm({
  device_inventory_id: '',
  device_name: '',
  mac_address: '',
  device_config: ''
})

const selectedDevice = ref<any>(null)

const selectDevice = (device: any) => {
  selectedDevice.value = device
  form.device_inventory_id = device.id
  
  // Auto-llenar el nombre del dispositivo
  if (!form.device_name) {
    form.device_name = `${device.model} - ${props.client.full_name}`
  }
}

const formatMacAddress = (value: string) => {
  // Remover caracteres no válidos y convertir a mayúsculas
  const cleaned = value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase()
  
  // Añadir dos puntos cada 2 caracteres
  const formatted = cleaned.replace(/(.{2})/g, '$1:').slice(0, -1)
  
  // Limitar a 17 caracteres (formato XX:XX:XX:XX:XX:XX)
  return formatted.slice(0, 17)
}

const handleMacInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const formatted = formatMacAddress(target.value)
  form.mac_address = formatted
}

const submit = () => {
  form.post(route('clients.devices.store', props.client.id))
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clients' },
  { title: props.client.full_name, href: `/clients/${props.client.id}` },
  { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
  { title: 'Nuevo Dispositivo', href: `/clients/${props.client.id}/devices/create` },
]
</script>

<template>
  <Head :title="`Nuevo Dispositivo - ${client.full_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.devices.index', client.id)">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver a Dispositivos
            </Button>
          </Link>
          
          <div class="flex items-center space-x-4">
            <div class="p-3 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
              <Smartphone class="h-8 w-8 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                Nuevo Dispositivo
              </h1>
              <p class="text-gray-600 dark:text-gray-400 mt-1">
                Registra un nuevo dispositivo para {{ client.full_name }}
              </p>
            </div>
          </div>
        </div>
        
        <div class="flex items-center space-x-3">
          <Link :href="route('clients.devices.index', client.id)">
            <Button variant="outline">
              <X class="mr-2 h-4 w-4" />
              Cancelar
            </Button>
          </Link>
          
          <Button 
            @click="submit" 
            :disabled="form.processing"
            class="bg-blue-600 hover:bg-blue-700"
          >
            <Save class="mr-2 h-4 w-4" />
            {{ form.processing ? 'Guardando...' : 'Guardar Dispositivo' }}
          </Button>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <form @submit.prevent="submit" class="space-y-6">
          
          <!-- Seleccionar Dispositivo del Inventario -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Cpu class="mr-2 h-5 w-5 text-blue-600" />
                Seleccionar Dispositivo del Inventario
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div v-if="availableDevices.length === 0" class="text-center py-8">
                <div class="text-gray-500 dark:text-gray-400">
                  <Smartphone class="mx-auto h-12 w-12 mb-4 opacity-50" />
                  <h3 class="text-lg font-medium mb-2">No hay dispositivos disponibles</h3>
                  <p class="mb-4">
                    No se encontraron dispositivos disponibles en el inventario.
                  </p>
                  <Button variant="outline" disabled>
                    Contactar Administrador
                  </Button>
                </div>
              </div>
              
              <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                  v-for="device in availableDevices"
                  :key="device.id"
                  @click="selectDevice(device)"
                  :class="[
                    'p-4 border-2 rounded-lg cursor-pointer transition-all hover:shadow-md',
                    selectedDevice?.id === device.id
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                      : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'
                  ]"
                >
                  <div class="flex items-start space-x-3">
                    <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg">
                      <Cpu class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                      <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">
                        {{ device.model }}
                      </h4>
                      <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <div>SN: {{ device.serial_number }}</div>
                        <div>HW: {{ device.hardware_version }}</div>
                        <div>FW: {{ device.firmware_version }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div v-if="form.errors.device_inventory_id" class="mt-2 text-sm text-red-600">
                {{ form.errors.device_inventory_id }}
              </div>
            </CardContent>
          </Card>

          <!-- Configuración del Dispositivo -->
          <Card v-if="selectedDevice">
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Settings class="mr-2 h-5 w-5 text-green-600" />
                Configuración del Dispositivo
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Nombre del Dispositivo -->
                <div>
                  <Label for="device_name">Nombre del Dispositivo *</Label>
                  <Input
                    id="device_name"
                    v-model="form.device_name"
                    placeholder="Ej: Rastreador Principal - Juan Pérez"
                    :class="{ 'border-red-500 ring-red-500': form.errors.device_name }"
                    required
                  />
                  <p class="mt-1 text-sm text-gray-500">
                    Nombre descriptivo para identificar este dispositivo
                  </p>
                  <div v-if="form.errors.device_name" class="mt-1 text-sm text-red-600">
                    {{ form.errors.device_name }}
                  </div>
                </div>

                <!-- Dirección MAC -->
                <div>
                  <Label for="mac_address">Dirección MAC *</Label>
                  <div class="relative">
                    <Hash class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                      id="mac_address"
                      v-model="form.mac_address"
                      @input="handleMacInput"
                      placeholder="XX:XX:XX:XX:XX:XX"
                      class="pl-10 font-mono"
                      :class="{ 'border-red-500 ring-red-500': form.errors.mac_address }"
                      maxlength="17"
                    />
                  </div>
                  <p class="mt-1 text-sm text-gray-500">
                    Dirección MAC única del dispositivo
                  </p>
                  <div v-if="form.errors.mac_address" class="mt-1 text-sm text-red-600">
                    {{ form.errors.mac_address }}
                  </div>
                </div>
              </div>

              <!-- Configuración Adicional -->
              <div>
                <Label for="device_config">Configuración Adicional (JSON)</Label>
                <Textarea
                  id="device_config"
                  v-model="form.device_config"
                  placeholder='{"frequency": 30, "timeout": 300, "debug": false}'
                  :rows="4"
                  :class="{ 'border-red-500 ring-red-500': form.errors.device_config }"
                />
                <p class="mt-1 text-sm text-gray-500">
                  Configuración avanzada en formato JSON (opcional)
                </p>
                <div v-if="form.errors.device_config" class="mt-1 text-sm text-red-600">
                  {{ form.errors.device_config }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Resumen del Dispositivo Seleccionado -->
          <Card v-if="selectedDevice" class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
            <CardHeader>
              <CardTitle class="flex items-center text-lg text-blue-800 dark:text-blue-200">
                <Wifi class="mr-2 h-5 w-5" />
                Resumen del Dispositivo
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    Información del Hardware
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Modelo:</span> {{ selectedDevice.model }}</div>
                    <div><span class="font-medium">Número de Serie:</span> {{ selectedDevice.serial_number }}</div>
                    <div><span class="font-medium">Versión de Hardware:</span> {{ selectedDevice.hardware_version }}</div>
                    <div><span class="font-medium">Versión de Firmware:</span> {{ selectedDevice.firmware_version }}</div>
                  </div>
                </div>
                
                <div>
                  <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    Configuración del Cliente
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Cliente:</span> {{ client.full_name }}</div>
                    <div><span class="font-medium">Nombre del Dispositivo:</span> {{ form.device_name || 'Sin definir' }}</div>
                    <div><span class="font-medium">Dirección MAC:</span> {{ form.mac_address || 'Sin definir' }}</div>
                    <div><span class="font-medium">Estado Inicial:</span> Pendiente</div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Botones de Acción -->
          <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('clients.devices.index', client.id)">
              <Button variant="outline" type="button">
                <X class="mr-2 h-4 w-4" />
                Cancelar
              </Button>
            </Link>
            
            <Button 
              type="submit" 
              :disabled="form.processing || !selectedDevice"
              class="bg-blue-600 hover:bg-blue-700"
            >
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? 'Registrando...' : 'Registrar Dispositivo' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>