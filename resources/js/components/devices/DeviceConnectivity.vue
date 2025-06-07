<script setup lang="ts">
import { computed } from 'vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  Wifi,
  WifiOff,
  AlertCircle
} from 'lucide-vue-next'

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
}

interface Props {
  device: Device
}

const props = defineProps<Props>()

const isOnline = computed(() => {
  if (!props.device.last_ping) return false
  
  const lastPing = new Date(props.device.last_ping)
  const now = new Date()
  const diffMinutes = (now.getTime() - lastPing.getTime()) / (1000 * 60)
  
  return diffMinutes < 10 // Consideramos online si el último ping fue hace menos de 10 minutos
})
</script>

<template>
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
</template>