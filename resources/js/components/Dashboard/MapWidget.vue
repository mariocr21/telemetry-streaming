<template>
  <div class="w-[65%] relative bg-slate-900/50 rounded-xl border border-slate-700/50 overflow-hidden">
    
    <!-- Header del Mapa -->
    <div class="absolute top-4 left-4 right-4 z-[1000] flex items-center justify-between pointer-events-none">
      <div class="flex items-center space-x-3 pointer-events-auto">
        <div class="bg-slate-900/90 backdrop-blur-sm rounded-lg px-3 py-2 border border-slate-600/50">
          <div class="flex items-center space-x-2">
            <div class="w-2 h-2 rounded-full" :class="gpsStatusColor"></div>
            <span class="text-sm font-medium text-white">{{ gpsStatusText }}</span>
          </div>
        </div>
        
        <div v-if="hasValidGpsData" class="bg-slate-900/90 backdrop-blur-sm rounded-lg px-3 py-2 border border-slate-600/50">
          <div class="flex items-center space-x-4 text-xs text-slate-300">
            <span>📍 {{ formatCoordinate(currentPosition?.lat) }}, {{ formatCoordinate(currentPosition?.lng) }}</span>
            <span v-if="currentSpeed !== null">🚗 {{ currentSpeed }} km/h</span>
            <span v-if="currentAltitude !== null">⛰️ {{ currentAltitude }}m</span>
            <span v-if="currentHeading !== null">🧭 {{ formatHeading(currentHeading) }}</span>
          </div>
        </div>
      </div>

      <!-- Controles del Mapa -->
      <div class="flex items-center space-x-2 pointer-events-auto">
        <button 
          @click="centerOnVehicle" 
          :disabled="!hasValidGpsData"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          title="Centrar en vehículo"
        >
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
        </button>
        
        <button 
          @click="toggleTracking" 
          :disabled="!hasValidGpsData"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          :title="isTracking ? 'Desactivar seguimiento' : 'Activar seguimiento'"
        >
          <svg class="w-4 h-4" :class="isTracking ? 'text-green-400' : 'text-white'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
          </svg>
        </button>

        <!-- Botón para limpiar rastro -->
        <button 
          @click="clearTrail" 
          :disabled="trail.length === 0"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          title="Limpiar rastro"
        >
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
          </svg>
        </button>

        <!-- Botón para ajustar vista -->
        <button 
          @click="fitToTrail" 
          :disabled="trail.length < 2"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          title="Ajustar vista al recorrido"
        >
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- Contenedor del Mapa -->
    <div id="vehicle-map" class="w-full h-full min-h-[500px]"></div>

    <!-- Overlay de carga -->
    <div v-if="isLoading" class="absolute inset-0 bg-slate-900/75 flex items-center justify-center z-20">
      <div class="text-center">
        <svg class="animate-spin h-8 w-8 text-cyan-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-sm text-slate-300">Cargando mapa...</p>
      </div>
    </div>

    <!-- Sin datos GPS -->
    <div v-if="!isLoading && !hasValidGpsData" class="absolute inset-0 bg-slate-900/75 flex items-center justify-center z-20">
      <div class="text-center max-w-sm">
        <div class="text-4xl text-slate-500 mb-4">🗺️</div>
        <h3 class="text-lg font-semibold text-slate-300 mb-2">Sin Datos GPS</h3>
        <p class="text-sm text-slate-400">
          Esperando coordenadas del vehículo...<br>
          Asegúrate de que el dispositivo tenga señal GPS.
        </p>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Types
interface Position {
  lat: number
  lng: number
}

interface Vehicle {
  id: number
  make: string
  model: string
  nickname?: string
}

interface ConnectionStatus {
  is_online: boolean
  status: string
}

// Props
const props = defineProps<{
  selectedVehicle: Vehicle | null | any
  isLoading: boolean
  isRealTimeActive: boolean
  connectionStatus: ConnectionStatus | null
}>()

// State
const map = ref<L.Map | null>(null)
const vehicleMarker = ref<L.Marker | null>(null)
const trailPolyline = ref<L.Polyline | null>(null)
const currentPosition = ref<Position | null>(null)
const currentSpeed = ref<number | null>(null)
const currentAltitude = ref<number | null>(null)
const currentHeading = ref<number | null>(null)
const isTracking = ref(true)
const trail = ref<Position[]>([])
const maxTrailPoints = 100

// GPS sensor PIDs
const GPS_PIDS = {
  LAT: 'lat',
  LNG: 'lng',
  SPEED: 'vel_kmh',
  ALTITUDE: 'alt_m',
  HEADING: 'rumbo'
}

// Computed
const hasValidGpsData = computed(() => {
  return currentPosition.value && 
         currentPosition.value.lat !== null && 
         currentPosition.value.lng !== null &&
         Math.abs(currentPosition.value.lat) <= 90 &&
         Math.abs(currentPosition.value.lng) <= 180
})

const gpsStatusColor = computed(() => {
  if (!hasValidGpsData.value) return 'bg-red-500'
  if (props.isRealTimeActive) return 'bg-green-500 animate-pulse'
  if (props.connectionStatus?.is_online) return 'bg-yellow-500'
  return 'bg-red-500'
})

const gpsStatusText = computed(() => {
  if (!hasValidGpsData.value) return 'Sin GPS'
  if (props.isRealTimeActive) return 'GPS En Vivo'
  if (props.connectionStatus?.is_online) return 'GPS Online'
  return 'GPS Offline'
})

// Methods
const initializeMap = async () => {
  await nextTick()
  
  if (map.value) {
    map.value.remove()
  }

  // Esperar a que el contenedor esté completamente renderizado
  const container = document.getElementById('vehicle-map')
  if (!container) {
    console.error('❌ Contenedor del mapa no encontrado')
    return
  }

  // Asegurar que el contenedor tenga dimensiones
  if (container.offsetWidth === 0 || container.offsetHeight === 0) {
    console.warn('⚠️ Contenedor sin dimensiones, esperando...')
    setTimeout(() => initializeMap(), 100)
    return
  }

  // Inicializar mapa centrado en México (default)
  const defaultLat = 19.4326
  const defaultLng = -99.1332
  
  map.value = L.map('vehicle-map', {
    center: [defaultLat, defaultLng],
    zoom: 13,
    zoomControl: true,
    attributionControl: true,
    preferCanvas: false
  })

  // Agregar capa de OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map.value)

  // Forzar recálculo del tamaño después de inicializar
  setTimeout(() => {
    if (map.value) {
      map.value.invalidateSize()
      console.log('🗺️ Mapa redimensionado')
    }
  }, 200)

  console.log('🗺️ Mapa inicializado')
}

const createVehicleIcon = () => {
  const iconSize = 32
  let rotation = currentHeading.value || 0
  
  // Crear SVG del vehículo con rotación
  const svgIcon = `
    <svg width="${iconSize}" height="${iconSize}" viewBox="0 0 24 24" style="transform: rotate(${rotation}deg)">
      <circle cx="12" cy="12" r="11" fill="#1e293b" stroke="#0ea5e9" stroke-width="2"/>
      <path d="M12 2L16 8H8L12 2Z" fill="#0ea5e9"/>
      <circle cx="12" cy="12" r="2" fill="#0ea5e9"/>
    </svg>
  `
  
  return L.divIcon({
    html: svgIcon,
    className: 'vehicle-marker',
    iconSize: [iconSize, iconSize],
    iconAnchor: [iconSize/2, iconSize/2]
  })
}

const updateVehiclePosition = (lat: number, lng: number) => {
  if (!map.value || !lat || !lng) return

  const newPosition: Position = { lat, lng }
  currentPosition.value = newPosition

  if (!vehicleMarker.value) {
    // Crear marcador inicial
    vehicleMarker.value = L.marker([lat, lng], {
      icon: createVehicleIcon()
    }).addTo(map.value)
    
    // Popup con información del vehículo
    const popupContent = `
      <div class="text-center">
        <h3 class="font-semibold">${props.selectedVehicle?.make} ${props.selectedVehicle?.model}</h3>
        ${props.selectedVehicle?.nickname ? `<p class="text-sm text-gray-600">${props.selectedVehicle.nickname}</p>` : ''}
      </div>
    `
    vehicleMarker.value.bindPopup(popupContent)
    
    // Centrar mapa en primera posición
    map.value.setView([lat, lng], 16)
  } else {
    // Actualizar posición existente
    vehicleMarker.value.setLatLng([lat, lng])
    vehicleMarker.value.setIcon(createVehicleIcon())
    
    // Seguimiento automático si está activo
    if (isTracking.value) {
      map.value.panTo([lat, lng])
    }
  }

  // Actualizar trail
  updateTrail(newPosition)
  
  console.log('📍 Posición del vehículo actualizada:', { lat, lng })
}

const updateTrail = (position: Position) => {
  if (!map.value) return

  // Agregar nueva posición al trail
  trail.value.push(position)
  
  // Limitar número de puntos en el trail
  if (trail.value.length > maxTrailPoints) {
    trail.value.shift()
  }

  // Actualizar polyline del trail
  if (trailPolyline.value) {
    map.value.removeLayer(trailPolyline.value)
  }

  if (trail.value.length > 1) {
    trailPolyline.value = L.polyline(
      trail.value.map(p => [p.lat, p.lng]),
      {
        color: '#0ea5e9',
        weight: 3,
        opacity: 0.7,
        smoothFactor: 1
      }
    ).addTo(map.value)
  }
}

const centerOnVehicle = () => {
  if (map.value && hasValidGpsData.value && currentPosition.value) {
    map.value.setView([currentPosition.value.lat, currentPosition.value.lng], 16, {
      animate: true,
      duration: 0.5
    })
    console.log('🎯 Centrado en vehículo:', currentPosition.value)
  }
}

const toggleTracking = () => {
  isTracking.value = !isTracking.value
  if (isTracking.value) {
    centerOnVehicle()
  }
  console.log('🎯 Seguimiento:', isTracking.value ? 'ACTIVADO' : 'DESACTIVADO')
}

const clearTrail = () => {
  trail.value = []
  if (trailPolyline.value && map.value) {
    map.value.removeLayer(trailPolyline.value)
    trailPolyline.value = null
  }
  console.log('🧹 Rastro limpiado')
}

const fitToTrail = () => {
  if (!map.value || trail.value.length < 2) return
  
  const group = new L.FeatureGroup()
  
  // Agregar todos los puntos del trail al grupo
  trail.value.forEach(point => {
    group.addLayer(L.marker([point.lat, point.lng]))
  })
  
  // Ajustar la vista para mostrar todo el trail
  map.value.fitBounds(group.getBounds(), {
    padding: [20, 20]
  })
  
  console.log('🗺️ Vista ajustada al recorrido completo')
}

const formatCoordinate = (coord: number | null | undefined): string => {
  if (coord === null || coord === undefined) return '---'
  return Number(coord).toFixed(6).toString()
}

const formatHeading = (heading: number | null): string => {
  if (heading === null) return '---'
  
  const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']
  const index = Math.round(heading / 45) % 8
  return `${Number(heading).toFixed(1)}° ${directions[index]}`
}

// Función para redimensionar el mapa manualmente
const resizeMap = () => {
  if (map.value) {
    map.value.invalidateSize()
    console.log('🔄 Mapa redimensionado manualmente')
  }
}

// Función para actualizar datos GPS desde el componente padre
const updateGpsData = (sensorReadings: Record<string, number>) => {
  // Actualizar coordenadas
  const lat = sensorReadings[GPS_PIDS.LAT]
  const lng = sensorReadings[GPS_PIDS.LNG]
  
  if (lat && lng) {
    updateVehiclePosition(lat, lng)
  }

  // Actualizar otros datos GPS
  currentSpeed.value = sensorReadings[GPS_PIDS.SPEED] || null
  currentAltitude.value = sensorReadings[GPS_PIDS.ALTITUDE] || null
  currentHeading.value = sensorReadings[GPS_PIDS.HEADING] || null
}

// Exponer método para uso del componente padre
defineExpose({
  updateGpsData,
  resizeMap
})

// Watchers
watch(() => props.selectedVehicle, () => {
  // Limpiar trail cuando cambie de vehículo
  trail.value = []
  if (trailPolyline.value && map.value) {
    map.value.removeLayer(trailPolyline.value)
    trailPolyline.value = null
  }
  if (vehicleMarker.value && map.value) {
    map.value.removeLayer(vehicleMarker.value)
    vehicleMarker.value = null
  }
  currentPosition.value = null
})

// Lifecycle
onMounted(() => {
  // Esperar a que el layout esté completamente cargado
  setTimeout(() => {
    initializeMap()
  }, 100)
})

onUnmounted(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})
</script>

<style>
/* Importar estilos de Leaflet */
@import 'leaflet/dist/leaflet.css';

/* Estilos para el marcador del vehículo */
.vehicle-marker {
  background: transparent !important;
  border: none !important;
}

/* Asegurar que el mapa tenga dimensiones correctas */
#vehicle-map {
  width: 100% !important;
  height: 100% !important;
  min-height: 500px !important;
}

/* Asegurar que los controles sean clickeables */
.pointer-events-none {
  pointer-events: none;
}

.pointer-events-auto {
  pointer-events: auto;
}

/* Estilos para Leaflet */
.leaflet-container {
  background: #0f172a;
  font-family: inherit;
}

.leaflet-control-attribution {
  background: rgba(15, 23, 42, 0.8) !important;
  color: #94a3b8 !important;
}

.leaflet-control-zoom a {
  background: rgba(15, 23, 42, 0.9) !important;
  color: white !important;
  border-color: rgba(71, 85, 105, 0.5) !important;
}

.leaflet-control-zoom a:hover {
  background: rgba(30, 41, 59, 0.9) !important;
}

.leaflet-popup-content-wrapper {
  background: rgba(15, 23, 42, 0.95) !important;
  color: white !important;
  border-radius: 8px !important;
}

.leaflet-popup-tip {
  background: rgba(15, 23, 42, 0.95) !important;
}

/* Fix para los iconos de zoom que pueden no aparecer */
.leaflet-control-zoom-in:after {
  content: '+';
}

.leaflet-control-zoom-out:after {
  content: '−';
}

/* Mejorar los botones de control */
button:not(:disabled):hover {
  transform: scale(1.05);
}

button:not(:disabled):active {
  transform: scale(0.95);
}
</style>