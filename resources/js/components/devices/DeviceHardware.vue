<script setup lang="ts">
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { Cpu } from 'lucide-vue-next'

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

interface Props {
  deviceInventory: DeviceInventory
}

const props = defineProps<Props>()
</script>

<template>
  <Card>
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
              {{ deviceInventory.model }}
            </p>
          </div>

          <div>
            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Número de Serie</h4>
            <p class="font-mono text-gray-900 dark:text-gray-100">
              {{ deviceInventory.serial_number }}
            </p>
          </div>

          <div>
            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">UUID del Dispositivo</h4>
            <p class="font-mono text-sm text-gray-900 dark:text-gray-100">
              {{ deviceInventory.device_uuid }}
            </p>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Versión de Hardware</h4>
            <p class="font-medium text-gray-900 dark:text-gray-100">
              {{ deviceInventory.hardware_version }}
            </p>
          </div>

          <div>
            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Versión de Firmware</h4>
            <p class="font-medium text-gray-900 dark:text-gray-100">
              {{ deviceInventory.firmware_version }}
            </p>
          </div>

          <div v-if="deviceInventory.manufactured_date">
            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Fabricación</h4>
            <p class="font-medium text-gray-900 dark:text-gray-100">
              {{ new Date(deviceInventory.manufactured_date).toLocaleDateString('es-ES') }}
            </p>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>