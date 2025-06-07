<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  Smartphone,
  Hash,
  Copy,
  ExternalLink
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
}

interface Client {
  id: number
  full_name: string
  email: string
}

interface Props {
  device: Device
  client: Client
}

const props = defineProps<Props>()

const emit = defineEmits<{
  copyToClipboard: [text: string, type: string]
}>()

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

const handleCopyToClipboard = (text: string, type: string) => {
  emit('copyToClipboard', text, type)
}
</script>

<template>
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
                @click="handleCopyToClipboard(device.mac_address, 'mac')"
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
</template>