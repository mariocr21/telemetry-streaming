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
import { 
  ArrowLeft,
  Smartphone,
  Edit,
  Trash2,
  MoreVertical,
  Cpu,
  Hash,
  Wifi,
  WifiOff,
  Calendar,
  Clock,
  Car,
  Zap,
  Settings,
  CheckCircle2,
  AlertCircle,
  Copy,
  ExternalLink
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
  vehicle?: Vehicle
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

const isOnline = computed(() => {
  if (!props.device.last_ping) return false
  
  const lastPing = new Date(props.device.last_ping)
  const now = new Date()
  const diffMinutes = (now.getTime() - lastPing.getTime()) / (1000 * 60)
  
  return diffMinutes < 10 // Consideramos online si el último ping fue hace menos de 10 minutos
})

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

        <!-- Contenido principal -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <!-- Columna principal -->
          <div class="space-y-6 lg:col-span-2">
            <!-- Información del Dispositivo -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Smartphone class="mr-2 h-5 w-5 text-blue-600" />
                  Información del Dispositivo
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Nombre del Dispositivo</h4>
                      <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ device.device_name }}
                      </p>
                    </div>

                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Dirección MAC</h4>
                      <div class="flex items-center space-x-2">
                        <Hash class="h-4 w-4 text-gray-400" />
                        <span class="font-mono font-medium text-gray-900 dark:text-gray-100">
                          {{ device.mac_address }}
                        </span>
                        <button
                          @click="copyToClipboard(device.mac_address, 'mac')"
                          class="rounded p-1 text-gray-400 hover:text-gray-600"
                          title="Copiar MAC"
                        >
                          <Copy class="h-3 w-3" />
                        </button>
                      </div>
                    </div>
                  </div>

                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Estado</h4>
                      <Badge :class="getStatusBadge(device.status).class" class="text-base px-3 py-1">
                        {{ getStatusBadge(device.status).text }}
                      </Badge>
                    </div>

                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Cliente</h4>
                      <Link
                        :href="route('clients.show', client.id)"
                        class="flex items-center space-x-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                      >
                        <span class="font-medium">{{ client.full_name }}</span>
                        <ExternalLink class="h-3 w-3" />
                      </Link>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Información del Hardware -->
            <Card v-if="device.device_inventory">
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Cpu class="mr-2 h-5 w-5 text-purple-600" />
                  Información del Hardware
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Modelo</h4>
                      <p class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ device.device_inventory.model }}
                      </p>
                    </div>

                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Número de Serie</h4>
                      <p class="font-mono text-gray-900 dark:text-gray-100">
                        {{ device.device_inventory.serial_number }}
                      </p>
                    </div>

                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">UUID del Dispositivo</h4>
                      <p class="font-mono text-sm text-gray-900 dark:text-gray-100">
                        {{ device.device_inventory.device_uuid }}
                      </p>
                    </div>
                  </div>

                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Versión de Hardware</h4>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ device.device_inventory.hardware_version }}
                      </p>
                    </div>

                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Versión de Firmware</h4>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ device.device_inventory.firmware_version }}
                      </p>
                    </div>

                    <div v-if="device.device_inventory.manufactured_date">
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Fabricación</h4>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ new Date(device.device_inventory.manufactured_date).toLocaleDateString('es-ES') }}
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Estado de Conectividad -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Wifi class="mr-2 h-5 w-5 text-green-600" />
                  Estado de Conectividad
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Estado de Conexión</h4>
                      <div class="flex items-center space-x-2">
                        <div v-if="isOnline" class="flex items-center space-x-2 text-green-600">
                          <Wifi class="h-5 w-5" />
                          <span class="font-medium">En línea</span>
                        </div>
                        <div v-else class="flex items-center space-x-2 text-gray-400">
                          <WifiOff class="h-5 w-5" />
                          <span class="font-medium">Desconectado</span>
                        </div>
                      </div>
                    </div>

                    <div v-if="device.last_ping">
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Último Ping</h4>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ new Date(device.last_ping).toLocaleString('es-ES') }}
                      </p>
                    </div>
                  </div>

                  <div class="space-y-4">
                    <div v-if="device.activated_at">
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Activación</h4>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ new Date(device.activated_at).toLocaleString('es-ES') }}
                      </p>
                    </div>

                    <div v-if="device.status === 'pending'">
                      <div class="rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
                        <div class="flex items-center space-x-2">
                          <AlertCircle class="h-5 w-5 text-yellow-600" />
                          <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            El dispositivo está pendiente de activación
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Vehículo Asociado -->
            <Card v-if="device.vehicle">
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Car class="mr-2 h-5 w-5 text-orange-600" />
                  Vehículo Asociado
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Marca y Modelo</h4>
                      <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ device.vehicle.make }} {{ device.vehicle.model }}
                      </p>
                    </div>

                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Año</h4>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ device.vehicle.year }}
                      </p>
                    </div>
                  </div>

                  <div class="space-y-4">
                    <div>
                      <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Placa</h4>
                      <p class="font-mono font-medium text-gray-900 dark:text-gray-100">
                        {{ device.vehicle.license_plate }}
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

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
                  @click="copyToClipboard(device.mac_address, 'mac')"
                  class="w-full justify-start"
                  variant="outline"
                >
                  <Copy class="mr-2 h-4 w-4" />
                  Copiar MAC
                </Button>

                <Button
                  v-if="device.status === 'pending' || device.status === 'inactive'"
                  @click="activateDevice"
                  class="w-full justify-start text-green-600 hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-900/20"
                  variant="outline"
                >
                  <Zap class="mr-2 h-4 w-4" />
                  Activar Dispositivo
                </Button>

                <Button
                  v-if="device.status === 'active'"
                  @click="deactivateDevice"
                  class="w-full justify-start text-orange-600 hover:bg-orange-50 hover:text-orange-700 dark:hover:bg-orange-900/20"
                  variant="outline"
                >
                  <Settings class="mr-2 h-4 w-4" />
                  Desactivar Dispositivo
                </Button>

                <div v-if="device.can?.delete" class="border-t border-gray-200 pt-3 dark:border-gray-700">
                  <Button
                    @click="deleteDevice"
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
                    <span class="text-sm">Vehículo asignado</span>
                    <CheckCircle2 v-if="device.vehicle" class="h-4 w-4 text-green-500" />
                    <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                  </div>

                  <div class="flex items-center justify-between">
                    <span class="text-sm">Configuración avanzada</span>
                    <CheckCircle2 v-if="device.device_config" class="h-4 w-4 text-green-500" />
                    <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>