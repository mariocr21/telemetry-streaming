<!-- ExportSensorDataModal.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Download, Calendar, FileSpreadsheet, Loader2, X } from 'lucide-vue-next'
import { format } from 'date-fns'

interface Sensor {
  id: number
  sensor: {
    id: number
    pid: string
    name: string
    description?: string
    category: string
    unit: string
  }
}

interface Props {
  open: boolean
  sensors: Sensor[]
  vehicleId: number
  clientId: number
  deviceId: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

// Estado del formulario
const selectedSensors = ref<number[]>([])
const dateRange = ref<'day' | 'range'>('day')
const singleDate = ref(format(new Date(), 'yyyy-MM-dd'))
const startDate = ref(format(new Date(), 'yyyy-MM-dd'))
const endDate = ref(format(new Date(), 'yyyy-MM-dd'))
const isExporting = ref(false)

// Computed
const allSelected = computed(() => 
  selectedSensors.value.length === props.sensors.length
)

// Métodos
const toggleAll = () => {
  if (allSelected.value) {
    selectedSensors.value = []
  } else {
    selectedSensors.value = props.sensors.map(s => s.id)
  }
}

const toggleSensor = (sensorId: number) => {
  const index = selectedSensors.value.indexOf(sensorId)
  if (index > -1) {
    selectedSensors.value.splice(index, 1)
  } else {
    selectedSensors.value.push(sensorId)
  }
}

const exportData = async () => {
  if (selectedSensors.value.length === 0) {
    alert('Selecciona al menos un sensor')
    return
  }

  isExporting.value = true

  try {
    const params = new URLSearchParams({
      vehicle_sensor_ids: selectedSensors.value.join(','),
      date_range_type: dateRange.value,
    })

    if (dateRange.value === 'day') {
      params.append('date', singleDate.value)
    } else {
      params.append('start_date', startDate.value)
      params.append('end_date', endDate.value)
    }

    const response = await fetch(
      `/clients/${props.clientId}/devices/${props.deviceId}/vehicles/${props.vehicleId}/export-sensor-data?${params}`,
      {
        method: 'GET',
        headers: {
          'Accept': 'text/csv',
        },
      }
    )

    if (!response.ok) throw new Error('Error al exportar datos')

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `sensores_${format(new Date(), 'yyyyMMdd_HHmmss')}.csv`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)

    // Cerrar modal
    emit('update:open', false)
    resetForm()
  } catch (error) {
    console.error('Error exportando datos:', error)
    alert('Error al exportar los datos')
  } finally {
    isExporting.value = false
  }
}

const resetForm = () => {
  selectedSensors.value = []
  dateRange.value = 'day'
  singleDate.value = format(new Date(), 'yyyy-MM-dd')
  startDate.value = format(new Date(), 'yyyy-MM-dd')
  endDate.value = format(new Date(), 'yyyy-MM-dd')
}

const closeModal = () => {
  emit('update:open', false)
  resetForm()
}

// Agrupar sensores por categoría
const sensorsByCategory = computed(() => {
  const groups: Record<string, Sensor[]> = {}
  
  props.sensors.forEach(sensor => {
    const category = sensor.sensor.category || 'general'
    if (!groups[category]) {
      groups[category] = []
    }
    groups[category].push(sensor)
  })
  
  return groups
})
</script>

<template>
  <!-- Modal Overlay -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 bg-black/50 dark:bg-black/70"
        @click="closeModal"
      />
    </Transition>

    <!-- Modal Content -->
    <Transition
      enter-active-class="transition-all duration-200"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition-all duration-200"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="closeModal"
      >
        <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto bg-white dark:bg-gray-900 rounded-lg shadow-xl">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
            <div class="flex items-center space-x-2">
              <FileSpreadsheet class="h-5 w-5 text-green-600" />
              <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                Exportar Datos de Sensores
              </h2>
            </div>
            <button
              @click="closeModal"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="space-y-6 px-6 py-4">
            <!-- Selección de Fecha -->
            <div class="space-y-3">
              <label class="text-base font-semibold text-gray-900 dark:text-gray-100">
                Período de Exportación
              </label>
              
              <div class="space-y-2">
                <label class="flex items-center space-x-3 cursor-pointer">
                  <input
                    v-model="dateRange"
                    type="radio"
                    value="day"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Un día específico</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                  <input
                    v-model="dateRange"
                    type="radio"
                    value="range"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Rango de fechas</span>
                </label>
              </div>

              <!-- Día específico -->
              <div v-if="dateRange === 'day'" class="ml-6">
                <div class="flex items-center space-x-2">
                  <Calendar class="h-4 w-4 text-gray-500" />
                  <input
                    v-model="singleDate"
                    type="date"
                    :max="format(new Date(), 'yyyy-MM-dd')"
                    class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  />
                </div>
              </div>

              <!-- Rango de fechas -->
              <div v-else class="ml-6 space-y-2">
                <div class="flex items-center space-x-2">
                  <label class="w-24 text-sm text-gray-700 dark:text-gray-300">Desde:</label>
                  <input
                    v-model="startDate"
                    type="date"
                    :max="endDate"
                    class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  />
                </div>
                <div class="flex items-center space-x-2">
                  <label class="w-24 text-sm text-gray-700 dark:text-gray-300">Hasta:</label>
                  <input
                    v-model="endDate"
                    type="date"
                    :min="startDate"
                    :max="format(new Date(), 'yyyy-MM-dd')"
                    class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>

            <!-- Selección de Sensores -->
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-base font-semibold text-gray-900 dark:text-gray-100">
                  Sensores a Exportar
                  <span class="ml-2 text-sm font-normal text-gray-500">
                    ({{ selectedSensors.length }} seleccionados)
                  </span>
                </label>
                
                <Button
                  @click="toggleAll"
                  variant="outline"
                  size="sm"
                  type="button"
                >
                  {{ allSelected ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                </Button>
              </div>

              <!-- Lista de sensores por categoría -->
              <div class="border rounded-lg max-h-96 overflow-y-auto dark:border-gray-700">
                <div
                  v-for="(sensors, category) in sensorsByCategory"
                  :key="category"
                  class="border-b last:border-b-0 dark:border-gray-700"
                >
                  <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 font-medium text-sm capitalize text-gray-900 dark:text-gray-100 sticky top-0">
                    {{ category }}
                  </div>
                  
                  <div class="divide-y dark:divide-gray-700">
                    <label
                      v-for="sensor in sensors"
                      :key="sensor.id"
                      class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        :checked="selectedSensors.includes(sensor.id)"
                        @change="toggleSensor(sensor.id)"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600"
                      />
                      <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-900 dark:text-gray-100">
                          {{ sensor.sensor.name }}
                          <span class="ml-2 text-xs text-gray-500 font-mono">
                            {{ sensor.sensor.pid }}
                          </span>
                        </p>
                        <p v-if="sensor.sensor.description" class="text-xs text-gray-500 truncate">
                          {{ sensor.sensor.description }}
                        </p>
                      </div>
                      <span class="text-xs text-gray-500">
                        {{ sensor.sensor.unit }}
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              <p v-if="selectedSensors.length === 0" class="text-sm text-amber-600 dark:text-amber-400">
                Selecciona al menos un sensor para exportar
              </p>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end space-x-3 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
            <Button @click="closeModal" variant="outline" type="button">
              Cancelar
            </Button>
            <Button
              @click="exportData"
              :disabled="selectedSensors.length === 0 || isExporting"
              type="button"
            >
              <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
              <Download v-else class="mr-2 h-4 w-4" />
              {{ isExporting ? 'Exportando...' : 'Exportar CSV' }}
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>