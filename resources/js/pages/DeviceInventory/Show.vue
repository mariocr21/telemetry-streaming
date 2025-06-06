<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
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
  Edit, 
  Trash2, 
  MoreVertical,
  Copy,
  CheckCircle2,
  Package,
  Hash,
  Cpu,
  Settings,
  Calendar,
  FileText,
  Users,
  Eye,
  Car,
  Building,
  Mail
} from 'lucide-vue-next'
import type { DeviceInventoryShowProps, BreadcrumbItem } from '@/types'

const props = defineProps<DeviceInventoryShowProps>()
const page = usePage()

const deleteDevice = () => {
  if (props.device.client_devices && props.device.client_devices.length > 0) {
    alert('No se puede eliminar este dispositivo porque está asignado a clientes.')
    return
  }
  
  if (confirm(`¿Estás seguro de que deseas eliminar el dispositivo ${props.device.serial_number}?`)) {
    router.delete(route('device-inventory.destroy', props.device.id), {
      onSuccess: () => {
        router.visit(route('device-inventory.index'))
      }
    })
  }
}

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    // Aquí podrías mostrar una notificación
  } catch (err) {
    console.error('Error al copiar:', err)
  }
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
  const updatedDate = new Date(props.device.updated_at?? props.device.created_at)
  const now = new Date()
  const diffDays = Math.ceil(Math.abs(now.getTime() - updatedDate.getTime()) / (1000 * 60 * 60 * 24))
  
  if (diffDays === 0) return 'Hoy'
  if (diffDays === 1) return 'Ayer'
  if (diffDays < 7) return `Hace ${diffDays} días`
  
  return updatedDate.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
})

const flashMessage = computed(() => {
  const flash = page.props.flash as any
  return flash?.message
})

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Inventario de Dispositivos', href: '/device-inventory' },
  { title: props.device.serial_number, href: `/device-inventory/${props.device.id}` },
]
</script>

<template>
  <Head :title="`${device.serial_number} - Inventario`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Header -->
    <template #header>
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        <div class="flex items-center space-x-4">
          <Link :href="route('device-inventory.index')">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver al Inventario
            </Button>
          </Link>
          
          <div class="flex items-center space-x-4">
            <div class="h-16 w-16 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-lg">
              <Package class="h-8 w-8 text-white" />
            </div>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                {{ device.serial_number }}
              </h1>
              <div class="flex items-center space-x-4 mt-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  En inventario desde {{ deviceAge }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  • Actualizado {{ lastUpdated }}
                </span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
          <Link 
            v-if="device.can?.update"
            :href="route('device-inventory.edit', device.id)"
          >
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
              :href="route('device-inventory.edit', device.id)" 
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Edit class="mr-2 h-4 w-4" />
              Editar Dispositivo
            </Link>
            
            <button 
              @click="copyToClipboard(device.serial_number)"
              class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Copy class="mr-2 h-4 w-4" />
              Copiar Número de Serie
            </button>
            
            <button 
              @click="copyToClipboard(device.device_uuid)"
              class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Copy class="mr-2 h-4 w-4" />
              Copiar UUID
            </button>
            
            <div class="border-t border-gray-100 dark:border-gray-700"></div>
            
            <button 
              v-if="device.can?.delete"
              @click="deleteDevice"
              class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Eliminar Dispositivo
            </button>
          </SimpleDropdown>
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

        <!-- Dashboard de estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Estado -->
          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-3 bg-purple-50 dark:bg-purple-900/50 rounded-lg">
                  <Package class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Estado</p>
                  <Badge :class="getStatusBadge(device.status).class" class="mt-1">
                    {{ getStatusBadge(device.status).text }}
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Asignaciones -->
          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-3 bg-blue-50 dark:bg-blue-900/50 rounded-lg">
                  <Users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clientes</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ device.client_devices?.length || 0 }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Vehículos -->
          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-3 bg-green-50 dark:bg-green-900/50 rounded-lg">
                  <Car class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Vehículos</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ device.client_devices?.filter(cd => cd.vehicle).length || 0 }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Tiempo en inventario -->
          <Card class="border border-gray-200 dark:border-gray-700">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-3 bg-orange-50 dark:bg-orange-900/50 rounded-lg">
                  <Calendar class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">En inventario</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ deviceAge }}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Contenido principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Columna principal -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Información del Dispositivo -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Hash class="mr-2 h-5 w-5 text-purple-600" />
                  Información del Dispositivo
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-4">
                    <div>
                      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                        Número de Serie
                      </h4>
                      <div class="flex items-center space-x-2">
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 font-mono">
                          {{ device.serial_number }}
                        </p>
                        <button
                          @click="copyToClipboard(device.serial_number)"
                          class="p-1 text-gray-400 hover:text-gray-600 rounded"
                          title="Copiar número de serie"
                        >
                          <Copy class="h-3 w-3" />
                        </button>
                      </div>
                    </div>
                    
                    <div>
                      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                        UUID del Dispositivo
                      </h4>
                      <div class="flex items-center space-x-2">
                        <Hash class="h-4 w-4 text-gray-400" />
                        <p class="font-mono text-gray-900 dark:text-gray-100">
                          {{ device.device_uuid }}
                        </p>
                        <button
                          @click="copyToClipboard(device.device_uuid)"
                          class="p-1 text-gray-400 hover:text-gray-600 rounded"
                          title="Copiar UUID"
                        >
                          <Copy class="h-3 w-3" />
                        </button>
                      </div>
                    </div>
                  </div>
                  
                  <div class="space-y-4">
                    <div>
                      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                        Modelo
                      </h4>
                      <div class="flex items-center space-x-2">
                        <Cpu class="h-4 w-4 text-gray-400" />
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                          {{ device.model }}
                        </p>
                      </div>
                    </div>

                    <div>
                      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                        Estado
                      </h4>
                      <Badge :class="getStatusBadge(device.status).class" class="text-base px-3 py-1">
                        {{ getStatusBadge(device.status).text }}
                      </Badge>
                    </div>
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
              <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                      Versión de Hardware
                    </h4>
                    <div v-if="device.hardware_version" class="flex items-center space-x-2">
                      <Settings class="h-4 w-4 text-gray-400" />
                      <p class="font-medium text-gray-900 dark:text-gray-100">{{ device.hardware_version }}</p>
                    </div>
                    <p v-else class="text-gray-400 italic">No especificado</p>
                  </div>
                  
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                      Versión de Firmware
                    </h4>
                    <div v-if="device.firmware_version" class="flex items-center space-x-2">
                      <Cpu class="h-4 w-4 text-gray-400" />
                      <p class="font-medium text-gray-900 dark:text-gray-100">{{ device.firmware_version }}</p>
                    </div>
                    <p v-else class="text-gray-400 italic">No especificado</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Fechas Importantes -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Calendar class="mr-2 h-5 w-5 text-green-600" />
                  Fechas Importantes
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div v-if="device.manufactured_date">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                      Fecha de Fabricación
                    </h4>
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                      {{ new Date(device.manufactured_date).toLocaleDateString('es-ES', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                      }) }}
                    </p>
                  </div>
                  
                  <div v-if="device.sold_date">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                      Fecha de Venta
                    </h4>
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                      {{ new Date(device.sold_date).toLocaleDateString('es-ES', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                      }) }}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Notas -->
            <Card v-if="device.notes">
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <FileText class="mr-2 h-5 w-5 text-orange-600" />
                  Notas
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                  <p class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ device.notes }}</p>
                </div>
              </CardContent>
            </Card>

            <!-- Asignaciones a Clientes -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center text-lg">
                  <Users class="mr-2 h-5 w-5 text-blue-600" />
                  Asignaciones a Clientes ({{ device.client_devices?.length || 0 }})
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div v-if="device.client_devices && device.client_devices.length > 0">
                  <div class="space-y-4">
                    <div 
                      v-for="clientDevice in device.client_devices" 
                      :key="clientDevice.id"
                      class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                      <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-md">
                          <Users class="h-6 w-6 text-white" />
                        </div>
                        
                        <div>
                          <div class="flex items-center space-x-2">
                            <h4 class="font-medium text-gray-900 dark:text-gray-100">
                              {{ clientDevice.device_name }}
                            </h4>
                            <Badge 
                              :class="clientDevice.status === 'active' ? 'bg-green-100 text-green-800' : 
                                     clientDevice.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                     'bg-red-100 text-red-800'"
                              class="text-xs"
                            >
                              {{ clientDevice.status === 'active' ? 'Activo' : 
                                 clientDevice.status === 'pending' ? 'Pendiente' : 'Inactivo' }}
                            </Badge>
                          </div>
                          
                          <div v-if="clientDevice.client" class="flex items-center space-x-4 mt-1">
                            <div class="flex items-center space-x-1">
                              <Building class="h-3 w-3 text-gray-400" />
                              <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ clientDevice.client.full_name }}
                              </span>
                            </div>
                            <div class="flex items-center space-x-1">
                              <Mail class="h-3 w-3 text-gray-400" />
                              <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ clientDevice.client.email }}
                              </span>
                            </div>
                          </div>
                          
                          <div v-if="clientDevice.vehicle" class="flex items-center space-x-1 mt-1">
                            <Car class="h-3 w-3 text-gray-400" />
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                              {{ clientDevice.vehicle.nickname || `${clientDevice.vehicle.make} ${clientDevice.vehicle.model}` }}
                              <span v-if="clientDevice.vehicle.license_plate" class="ml-1">
                                ({{ clientDevice.vehicle.license_plate }})
                              </span>
                            </span>
                          </div>
                        </div>
                      </div>

                      <div class="flex items-center space-x-2">
                        <Link 
                          v-if="clientDevice.client"
                          :href="route('clients.show', clientDevice.client.id)"
                          class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/50 rounded-lg transition-all"
                          title="Ver cliente"
                        >
                          <Eye class="h-4 w-4" />
                        </Link>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Estado vacío -->
                <div v-else class="text-center py-8">
                  <Users class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                  <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                    Sin asignaciones
                  </h3>
                  <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Este dispositivo aún no está asignado a ningún cliente.
                  </p>
                </div>
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
                <Link 
                  v-if="device.can?.update"
                  :href="route('device-inventory.edit', device.id)"
                >
                  <Button class="w-full justify-start" variant="outline">
                    <Edit class="mr-2 h-4 w-4" />
                    Editar Dispositivo
                  </Button>
                </Link>
                
                <Button 
                  @click="copyToClipboard(device.serial_number)"
                  class="w-full justify-start" 
                  variant="outline"
                >
                  <Copy class="mr-2 h-4 w-4" />
                  Copiar Número de Serie
                </Button>
                
                <Button 
                  @click="copyToClipboard(device.device_uuid)"
                  class="w-full justify-start" 
                  variant="outline"
                >
                  <Hash class="mr-2 h-4 w-4" />
                  Copiar UUID
                </Button>
                
                <div v-if="device.can?.delete" class="pt-3 border-t border-gray-200 dark:border-gray-700">
                  <Button 
                    @click="deleteDevice"
                    class="w-full justify-start text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20" 
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
                  <Settings class="mr-2 h-5 w-5 text-gray-600" />
                  Información del Sistema
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div>
                  <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                    ID del Dispositivo
                  </h4>
                  <p class="font-mono text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                    #{{ device.id }}
                  </p>
                </div>
                
                <div>
                  <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                    Fecha de Registro
                  </h4>
                  <div class="flex items-center space-x-2">
                    <Calendar class="h-4 w-4 text-gray-400" />
                    <div>
                      <p class="font-medium">{{ new Date(device.created_at).toLocaleDateString('es-ES', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                      }) }}</p>
                      <p class="text-sm text-gray-500">{{ new Date(device.created_at).toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                      }) }}</p>
                    </div>
                  </div>
                </div>
                
                <div>
                  <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                    Última Actualización
                  </h4>
                  <div class="flex items-center space-x-2">
                    <Settings class="h-4 w-4 text-gray-400" />
                    <div>
                      <p class="font-medium">{{ lastUpdated }}</p>
                      <p class="text-sm text-gray-500">{{ new Date(device.updated_at).toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                      }) }}</p>
                    </div>
                  </div>
                </div>
                
                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Tiempo en inventario</span>
                    <Badge variant="secondary">{{ deviceAge }}</Badge>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Resumen de Estado -->
            <Card>
              <CardHeader>
                <CardTitle class="text-lg">Resumen de Estado</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-sm">Información básica</span>
                    <CheckCircle2 class="h-4 w-4 text-green-500" />
                  </div>
                  
                  <div class="flex items-center justify-between">
                    <span class="text-sm">Versiones técnicas</span>
                    <CheckCircle2 v-if="device.hardware_version || device.firmware_version" class="h-4 w-4 text-green-500" />
                    <span v-else class="h-4 w-4 text-gray-400">-</span>
                  </div>
                  
                  <div class="flex items-center justify-between">
                    <span class="text-sm">Fechas de fabricación</span>
                    <CheckCircle2 v-if="device.manufactured_date" class="h-4 w-4 text-green-500" />
                    <span v-else class="h-4 w-4 text-gray-400">-</span>
                  </div>
                  
                  <div class="flex items-center justify-between">
                    <span class="text-sm">Asignaciones activas</span>
                    <CheckCircle2 v-if="device.client_devices && device.client_devices.length > 0" class="h-4 w-4 text-green-500" />
                    <span v-else class="h-4 w-4 text-gray-400">-</span>
                  </div>
                  
                  <div class="flex items-center justify-between">
                    <span class="text-sm">Notas documentadas</span>
                    <CheckCircle2 v-if="device.notes" class="h-4 w-4 text-green-500" />
                    <span v-else class="h-4 w-4 text-gray-400">-</span>
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