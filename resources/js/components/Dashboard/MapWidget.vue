<template>
  <div class="relative rounded-xl border border-slate-700/50 overflow-hidden bg-slate-900/50">
    
    <div class="absolute top-4 left-4 right-4 z-30 flex items-center justify-between pointer-events-none">
      <div class="flex items-center space-x-3 pointer-events-auto">
        
        <div class="bg-slate-900/90 backdrop-blur-sm rounded-lg px-3 py-2 border border-slate-600/50 shadow-lg">
          <div class="flex items-center space-x-2">
            <div class="w-2 h-2 rounded-full" :class="gpsStatusColor"></div>
            <span class="text-sm font-medium text-white">{{ gpsStatusText }}</span>
          </div>
        </div>
        
        <div v-if="hasValidGpsData" class="bg-slate-900/90 backdrop-blur-sm rounded-lg px-3 py-2 border border-slate-600/50 shadow-lg">
          <div class="flex items-center space-x-4 text-xs text-slate-300">
            <span>📍 {{ formatCoordinate(currentPosition?.lat) }}, {{ formatCoordinate(currentPosition?.lng) }}</span>
            <span v-if="currentSpeed !== null">🚗 {{ currentSpeed }} km/h</span>
            <span v-if="currentAltitude !== null">⛰️ {{ currentAltitude }}m</span>
            <span v-if="currentHeading !== null">🧭 {{ formatHeading(currentHeading) }}</span>
          </div>
        </div>
      </div>

      <div class="flex items-center space-x-2 pointer-events-auto">
        
        <!-- Toggle Layers Dropdown -->
        <div class="relative">
          <div v-if="showLayerMenu" @click="showLayerMenu = false" class="fixed inset-0 z-40 cursor-default"></div>

          <button 
            @click="showLayerMenu = !showLayerMenu"
            class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 transition-colors text-white"
            title="Cambiar vista de mapa"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 7m0 13V7"></path>
            </svg>
          </button>
          
          <!-- Dropdown Menu -->
          <div v-if="showLayerMenu" class="absolute right-0 top-full mt-2 w-32 bg-slate-900/95 backdrop-blur-md rounded-lg border border-slate-600/50 shadow-xl overflow-hidden z-50">
            <button @click="changeMapLayer('dark')" class="w-full text-left px-3 py-2 text-xs hover:bg-slate-800 text-slate-300 flex items-center" :class="{'text-cyan-400 font-bold': activeLayer === 'dark'}">
              <span class="w-2 h-2 rounded-full bg-slate-800 border border-slate-600 mr-2"></span> Oscuro
            </button>
            <button @click="changeMapLayer('light')" class="w-full text-left px-3 py-2 text-xs hover:bg-slate-800 text-slate-300 flex items-center" :class="{'text-cyan-400 font-bold': activeLayer === 'light'}">
              <span class="w-2 h-2 rounded-full bg-slate-200 border border-slate-400 mr-2"></span> Claro
            </button>
            <button @click="changeMapLayer('satellite')" class="w-full text-left px-3 py-2 text-xs hover:bg-slate-800 text-slate-300 flex items-center" :class="{'text-cyan-400 font-bold': activeLayer === 'satellite'}">
              <span class="w-2 h-2 rounded-full bg-green-800 border border-green-600 mr-2"></span> Satélite
            </button>
          </div>
        </div>

        <button 
          @click="centerOnVehicle" 
          :disabled="!hasValidGpsData"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-cyan-400"
          title="Centrar en vehículo"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

        <button 
          @click="clearTrail" 
          :disabled="trail.length === 0"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-white"
          title="Limpiar rastro"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
          </svg>
        </button>

        <button 
          @click="fitToTrail" 
          :disabled="trail.length < 2"
          class="bg-slate-900/90 backdrop-blur-sm rounded-lg p-2 border border-slate-600/50 hover:bg-slate-800/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-white"
          title="Ajustar vista al recorrido"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
          </svg>
        </button>
      </div>
    </div>

    <div id="vehicle-map" class="w-full h-full"></div>

    <div v-if="isLoading" class="absolute inset-0 bg-slate-900/75 flex items-center justify-center z-40">
      <div class="text-center">
        <svg class="animate-spin h-8 w-8 text-cyan-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-sm text-slate-300">Cargando mapa...</p>
      </div>
    </div>

    <div v-if="!isLoading && !hasValidGpsData" class="absolute inset-0 bg-slate-900/75 flex items-center justify-center z-40">
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

// Tipos
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

// Props - Made optional with defaults for use in DynamicDashboard
const props = withDefaults(defineProps<{
  selectedVehicle?: any
  isLoading?: boolean
  isRealTimeActive?: boolean
  connectionStatus?: { is_online: boolean; status: string } | null
  latitude?: number
  latitude?: number
  longitude?: number
  heading?: number
  defaultLayer?: 'dark' | 'light' | 'satellite'
}>(), {
  selectedVehicle: null,
  isLoading: false,
  isRealTimeActive: false,
  connectionStatus: null,
  latitude: 0,
  longitude: 0,
  heading: 0,
  defaultLayer: 'dark',
})

// Estado
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

// Control de Capas
const showLayerMenu = ref(false)
const activeLayer = ref<'dark' | 'light' | 'satellite'>('dark')
const currentTileLayer = ref<L.TileLayer | null>(null)

const TILE_LAYERS = {
  dark: {
    url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
    attribution: '© CartoDB, © OpenStreetMap contributors'
  },
  light: {
    url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
    attribution: '© CartoDB, © OpenStreetMap contributors'
  },
  satellite: {
    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    attribution: 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
  }
}

const changeMapLayer = (layerType: 'dark' | 'light' | 'satellite') => {
  activeLayer.value = layerType
  showLayerMenu.value = false
  
  if (!map.value) return

  // Remover capa anterior
  if (currentTileLayer.value) {
    map.value.removeLayer(currentTileLayer.value)
  }

  // Agregar nueva capa
  const layerConfig = TILE_LAYERS[layerType];
  currentTileLayer.value = L.tileLayer(layerConfig.url, {
    attribution: layerConfig.attribution,
    maxZoom: 19
  }).addTo(map.value)
}

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

// Métodos
const initializeMap = async () => {
  await nextTick()
  
  if (map.value) {
    map.value.remove()
  }

  const container = document.getElementById('vehicle-map')
  if (!container) {
    console.error('❌ Contenedor del mapa no encontrado')
    return
  }

  if (container.offsetWidth === 0 || container.offsetHeight === 0) {
    setTimeout(() => initializeMap(), 100)
    return
  }

  const defaultLat = 19.4326
  const defaultLng = -99.1332
  
  map.value = L.map('vehicle-map', {
    center: [defaultLat, defaultLng],
    zoom: 13,
    zoomControl: true,
    attributionControl: true,
    preferCanvas: false
  })

  // Inicializar con la capa configurada
  changeMapLayer(props.defaultLayer)

  setTimeout(() => {
    if (map.value) {
      map.value.invalidateSize()
    }
  }, 200)

  console.log('🗺️ Mapa inicializado')
}

const createVehicleIcon = () => {
  const iconSize = 32
  // Aseguramos que la rotación es un número válido. Usamos 0 si es nulo.
  let rotation = currentHeading.value === null ? 0 : currentHeading.value 
  
  // Color NEURONA: #0ea5e9 (cyan-500)
  const cyanColor = '#0ea5e9' 
  
  // Crear SVG del vehículo con rotación
  const svgIcon = `
    <svg width="${iconSize}" height="${iconSize}" viewBox="0 0 24 24" style="transform: rotate(${rotation}deg); transition: transform 0.5s;">
      <circle cx="12" cy="12" r="11" fill="#1e293b" stroke="${cyanColor}" stroke-width="2"/>
      <path d="M12 2L16 8H8L12 2Z" fill="${cyanColor}"/>
      <circle cx="12" cy="12" r="2" fill="${cyanColor}"/>
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
    vehicleMarker.value = L.marker([lat, lng], {
      icon: createVehicleIcon(),
      // Usar 'true' para que la aguja siga el rumbo
      rotationAngle: currentHeading.value !== null ? currentHeading.value : 0, 
      rotationOrigin: 'center center' 
    }).addTo(map.value)
    
    // Configurar el contenido del popup
    const popupContent = `
      <div class="text-center text-sm">
        <h3 class="font-semibold text-white">${props.selectedVehicle?.make || 'Vehículo'} ${props.selectedVehicle?.model || 'Desconocido'}</h3>
        ${props.selectedVehicle?.nickname ? `<p class="text-xs text-gray-400">(${props.selectedVehicle.nickname})</p>` : ''}
      </div>
    `
    vehicleMarker.value.bindPopup(popupContent, { className: 'custom-popup-leaflet' })
    
    map.value.setView([lat, lng], 16)
  } else {
    vehicleMarker.value.setLatLng([lat, lng])
    vehicleMarker.value.setIcon(createVehicleIcon()) // Actualiza el icono y la rotación si es necesario
    
    // Tracking automático si está activo
    if (isTracking.value) {
      map.value.panTo([lat, lng])
    }
  }

  updateTrail(newPosition)
}

const updateTrail = (position: Position) => {
  if (!map.value) return

  trail.value.push(position)
  
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
        color: '#0ea5e9', // Color NEURONA
        weight: 4,
        opacity: 0.9,
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
  }
}

const toggleTracking = () => {
  isTracking.value = !isTracking.value
  if (isTracking.value) {
    centerOnVehicle()
  }
}

const clearTrail = () => {
  trail.value = []
  if (trailPolyline.value && map.value) {
    map.value.removeLayer(trailPolyline.value)
    trailPolyline.value = null
  }
}

const fitToTrail = () => {
  if (!map.value || trail.value.length < 2) return
  
  const latLngs = trail.value.map(p => L.latLng(p.lat, p.lng))
  const bounds = L.latLngBounds(latLngs)
  
  map.value.fitBounds(bounds, {
    padding: [30, 30] // Añadir padding
  })
}

const formatCoordinate = (coord: number | null | undefined): string => {
  if (coord === null || coord === undefined || isNaN(coord)) return '---'
  return Number(coord).toFixed(6).toString()
}

const formatHeading = (heading: number | null): string => {
  if (heading === null || isNaN(heading)) return '---'
  
  const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']
  const index = Math.round(heading / 45) % 8
  return `${Number(heading).toFixed(0)}° ${directions[index]}`
}

const resizeMap = () => {
  if (map.value) {
    map.value.invalidateSize()
  }
}

const updateGpsData = (sensorReadings: Record<string, number>) => {
  // Asegurarse de que los valores sean números válidos
  const lat = sensorReadings[GPS_PIDS.LAT]
  const lng = sensorReadings[GPS_PIDS.LNG]
  
  // Extraer el resto de los datos
  currentSpeed.value = sensorReadings[GPS_PIDS.SPEED] || null
  currentAltitude.value = sensorReadings[GPS_PIDS.ALTITUDE] || null
  currentHeading.value = sensorReadings[GPS_PIDS.HEADING] || null

  // Actualizar posición solo si las coordenadas son válidas
  if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
    updateVehiclePosition(lat, lng)
  }
}

// Exponer método para uso del componente padre
defineExpose({
  updateGpsData,
  resizeMap
})

// Watchers
watch(() => props.selectedVehicle, () => {
  // Resetear el estado del mapa al cambiar de vehículo
  clearTrail()
  if (vehicleMarker.value && map.value) {
    map.value.removeLayer(vehicleMarker.value)
    vehicleMarker.value = null
  }
  currentPosition.value = null
  currentSpeed.value = null
  currentAltitude.value = null
  currentHeading.value = null
  // Re-inicializar el mapa para asegurar el centrado si es necesario, aunque `initializeMap` se encarga
})

// Ciclo de vida
onMounted(() => {
  // Inicializar mapa un poco después de montar para que el contenedor tenga dimensiones
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

/* Estilos para el marcador del vehículo (usando el SVG) */
.vehicle-marker {
  background: transparent !important;
  border: none !important;
  /* Asegurar que el marcador esté siempre por encima de polylines/tiles */
  z-index: 600 !important; 
}

/* Asegurar que el mapa tenga dimensiones correctas */
#vehicle-map {
  width: 100% !important;
  height: 100% !important;
  min-height: 500px !important;
  /* El mapa en sí debe tener un z-index bajo */
  z-index: 10; 
}

/* Estilos para Leaflet que coinciden con la UI oscura */
.leaflet-container {
  background: #0f172a; /* Slate-950 */
  font-family: inherit;
  border-radius: 12px;
}

/* Estilos de Popup personalizados */
.leaflet-popup-content-wrapper {
  background: rgba(15, 23, 42, 0.95) !important;
  color: white !important;
  border-radius: 8px !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}

.leaflet-popup-tip {
  background: rgba(15, 23, 42, 0.95) !important;
}

/* Mejorar los botones de control */
.leaflet-control-zoom a {
  background: rgba(15, 23, 42, 0.9) !important;
  color: white !important;
  border-color: rgba(71, 85, 105, 0.5) !important;
}

.leaflet-control-zoom a:hover {
  background: rgba(30, 41, 59, 0.9) !important;
}

/* Asegurar que los controles de Leaflet no se superpongan accidentalmente */
.leaflet-control-container {
    z-index: 30; /* Misma capa que el header del mapa para evitar colisión con el modal */
}
</style>