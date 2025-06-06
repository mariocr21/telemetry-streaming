<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Badge from '@/components/ui/Badge.vue'
import { 
  ArrowLeft,
  Smartphone,
  Save,
  X,
  Hash,
  Settings,
  Cpu,
  AlertTriangle
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface DeviceInventory {
  id: number
  serial_number: string
  device_uuid: string
  model: string
  hardware_version: string
  firmware_version: string
}

interface Device {
  id: number
  device_inventory_id: number
  device_name: string
  mac_address: string
  status: string
  device_config?: any
  device_inventory?: DeviceInventory
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

const form = useForm({
  device_name: props.device.device_name,
  mac_address: props.device.mac_address,
  status: props.device.status,
  device_config: props.device.device_config ? JSON.stringify(props.device.device_config, null, 2) : ''
})

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

const validateJson = () => {
  if (!form.device_config.trim()) return true
  
  try {
    JSON.parse(form.device_config)
    return true
  } catch {
    return false
  }
}

const submit = () => {
  if (!validateJson()) {
    alert('La configuración JSON no es válida')
    return
  }
  
  form.put(route('clients.devices.update', [props.client.id, props.device.id]))
}

const getStatusBadge = (status: string) => {
  const badges = {
    pending: { text: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' },
    active: { text: 'Activo', class: 'bg-green-100 text-green-800' },
    inactive: { text: 'Inactivo', class: 'bg-red-100 text-red-800' },
    maintenance: { text: 'Mantenimiento', class: 'bg-orange-100 text-orange-800' },
    retired: { text: 'Retirado', class: 'bg-gray-100 text-gray-800' }
  }
  return badges[status as keyof typeof badges] || badges.pending
}

const statusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'active', label: 'Activo' },
  { value: 'inactive', label: 'Inactivo' },
  { value: 'maintenance', label: 'Mantenimiento' },
  { value: 'retired', label: 'Retirado' }
]

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clients' },
  { title: props.client.full_name, href: `/clients/${props.client.id}` },
  { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
  { title: props.device.device_name, href: `/clients/${props.client.id}/devices/${props.device.id}` },
  { title: 'Editar', href: `/clients/${props.client.id}/devices/${props.device.id}/edit` }
]
</script>

<template>
  <Head :title="`Editar ${device.device_name} - ${client.full_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.devices.show', [client.id, device.id])">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver al Dispositivo
            </Button>
          </Link>
          
          <div class="flex items-center space-x-4">
            <div class="p-3 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
              <Smartphone class="h-8 w-8 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                Editar Dispositivo
              </h1>
              <p class="text-gray-600 dark:text-gray-400 mt-1">
                Modificar la configuración de {{ device.device_name }}
              </p>
            </div>
          </div>
        </div>
        
        <div class="flex items-center space-x-3">
          <Link :href="route('clients.devices.show', [client.id, device.id])">
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
            {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
          </Button>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <form @submit.prevent="submit" class="space-y-6">
          
          <!-- Información del Hardware (Solo lectura) -->
          <Card v-if="device.device_inventory" class="bg-gray-50 dark:bg-gray-900/50 border-gray-200 dark:border-gray-700">
            <CardHeader>
              <CardTitle class="flex items-center text-lg text-gray-700 dark:text-gray-300">
                <Cpu class="mr-2 h-5 w-5" />
                Información del Hardware (Solo lectura)
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <Label class="text-gray-500">Modelo</Label>
                  <p class="font-semibold text-gray-900 dark:text-gray-100 mt-1">
                    {{ device.device_inventory.model }}
                  </p>
                </div>
                <div>
                  <Label class="text-gray-500">Número de Serie</Label>
                  <p class="font-mono text-gray-900 dark:text-gray-100 mt-1">
                    {{ device.device_inventory.serial_number }}
                  </p>
                </div>
                <div>
                  <Label class="text-gray-500">Versión de Hardware</Label>
                  <p class="font-medium text-gray-900 dark:text-gray-100 mt-1">
                    {{ device.device_inventory.hardware_version }}
                  </p>
                </div>
              </div>
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                  <AlertTriangle class="h-4 w-4" />
                  <span>El hardware asociado no se puede modificar desde aquí.</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Configuración del Dispositivo -->
          <Card>
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

              <!-- Estado del Dispositivo -->
              <div>
                <Label for="status">Estado del Dispositivo *</Label>
                <select
                  id="status"
                  v-model="form.status"
                  :class="[
                    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                    { 'border-red-500 ring-red-500': form.errors.status }
                  ]"
                >
                  <option value="">Seleccionar estado</option>
                  <option 
                    v-for="option in statusOptions" 
                    :key="option.value" 
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
                <p class="mt-1 text-sm text-gray-500">
                  Estado actual del dispositivo en el sistema
                </p>
                <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                  {{ form.errors.status }}
                </div>
              </div>

              <!-- Configuración Adicional -->
              <div>
                <Label for="device_config">Configuración Adicional (JSON)</Label>
                <Textarea
                  id="device_config"
                  v-model="form.device_config"
                  placeholder='{"frequency": 30, "timeout": 300, "debug": false}'
                  :rows="6"
                  :class="[
                    'font-mono',
                    { 'border-red-500 ring-red-500': form.errors.device_config || !validateJson() }
                  ]"
                />
                <p class="mt-1 text-sm text-gray-500">
                  Configuración avanzada en formato JSON (opcional)
                </p>
                <div v-if="!validateJson() && form.device_config.trim()" class="mt-1 text-sm text-red-600">
                  La configuración JSON no es válida
                </div>
                <div v-if="form.errors.device_config" class="mt-1 text-sm text-red-600">
                  {{ form.errors.device_config }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Vista Previa de Cambios -->
          <Card class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
            <CardHeader>
              <CardTitle class="flex items-center text-lg text-blue-800 dark:text-blue-200">
                <Settings class="mr-2 h-5 w-5" />
                Vista Previa de Cambios
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    Valores Actuales
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Nombre:</span> {{ device.device_name }}</div>
                    <div><span class="font-medium">MAC:</span> {{ device.mac_address }}</div>
                    <div class="flex items-center space-x-2">
                      <span class="font-medium">Estado:</span>
                      <Badge :class="getStatusBadge(device.status).class" class="text-xs">
                        {{ getStatusBadge(device.status).text }}
                      </Badge>
                    </div>
                    <div><span class="font-medium">Configuración:</span> {{ device.device_config ? 'Configurado' : 'Sin configurar' }}</div>
                  </div>
                </div>
                
                <div>
                  <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    Nuevos Valores
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Nombre:</span> {{ form.device_name || 'Sin definir' }}</div>
                    <div><span class="font-medium">MAC:</span> {{ form.mac_address || 'Sin definir' }}</div>
                    <div class="flex items-center space-x-2">
                      <span class="font-medium">Estado:</span>
                      <Badge :class="getStatusBadge(form.status).class" class="text-xs">
                        {{ getStatusBadge(form.status).text }}
                      </Badge>
                    </div>
                    <div><span class="font-medium">Configuración:</span> {{ form.device_config.trim() ? 'Configurado' : 'Sin configurar' }}</div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Información del Cliente -->
          <Card class="bg-gray-50 dark:bg-gray-900/50">
            <CardHeader>
              <CardTitle class="text-lg text-gray-700 dark:text-gray-300">
                Información del Cliente
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="flex items-center space-x-4">
                <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                  <span class="text-white font-semibold">
                    {{ client.full_name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ client.full_name }}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ client.email }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Botones de Acción -->
          <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('clients.devices.show', [client.id, device.id])">
              <Button variant="outline" type="button">
                <X class="mr-2 h-4 w-4" />
                Cancelar
              </Button>
            </Link>
            
            <Button 
              type="submit" 
              :disabled="form.processing || !validateJson()"
              class="bg-blue-600 hover:bg-blue-700"
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