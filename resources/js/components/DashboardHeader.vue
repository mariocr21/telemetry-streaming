<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';

// Tipos requeridos para props
interface Device {
    id: number;
    device_name: string;
    status: string;
    active_vehicle: {
        vin: string | null;
        make?: string | null;
        model?: string | null;
    } | null;
}

interface ConnectionStatusDisplay {
    text: string;
    color: 'green' | 'yellow' | 'red' | 'cyan' | string;
    icon: 'live' | 'online' | 'warning' | 'offline' | 'disconnected' | string;
    description: string;
}

// Props esperados
const props = defineProps<{
    selectedDevice: Device | null;
    displayConnectionStatus?: ConnectionStatusDisplay | null;
}>();

// Eventos emitidos
defineEmits(['open-modal']);

// Hook i18n
const { t } = useI18n();

// Función para obtener la clase del indicador de estado
const getStatusDotClass = (): string => {
    if (!props.displayConnectionStatus) {
        return 'bg-gray-500';
    }
    
    switch (props.displayConnectionStatus.color) {
        case 'green':
            return 'bg-green-500 shadow-green-500/50';
        case 'yellow':
            return 'bg-yellow-500 shadow-yellow-500/50';
        case 'red':
            return 'bg-red-500 shadow-red-500/50';
        case 'cyan':
            return 'bg-cyan-500 shadow-cyan-500/50';
        default:
            return 'bg-gray-500';
    }
};

// Función para verificar si está en modo "live" (tiempo real)
const isLive = (): boolean => {
    return props.displayConnectionStatus?.icon === 'live';
};
</script>

<template>
    <div
        class="header-container"
        :class="{ 'header-live': isLive() }"
    >
        <!-- Info del dispositivo -->
        <div class="device-info">
            <p class="device-name">
                {{ t('deviceLabel') }}
                <span class="device-name-value">
                    {{ selectedDevice?.device_name || t('noneSelected') }}
                </span>
            </p>
            <p class="vehicle-info">
                {{ t('activeVehicleLabel') }}
                <span class="vehicle-vin">
                    {{ selectedDevice?.active_vehicle?.vin || t('none') }}
                </span>
                <!-- Mostrar marca/modelo si está disponible -->
                <span v-if="selectedDevice?.active_vehicle?.make" class="vehicle-details">
                    · {{ selectedDevice.active_vehicle.make }} {{ selectedDevice.active_vehicle.model || '' }}
                </span>
            </p>
        </div>

        <!-- Status y botón -->
        <div class="actions-container">
            <!-- Estado de conexión -->
            <div class="status-display">
                <span class="status-label">{{ t('telemetryStatus') }}</span>
                <div class="status-indicator">
                    <!-- Punto de estado con animación para "live" -->
                    <div class="status-dot-wrapper">
                        <div
                            class="status-dot"
                            :class="getStatusDotClass()"
                        ></div>
                        <!-- Ping animation cuando está en vivo -->
                        <div
                            v-if="isLive()"
                            class="status-ping"
                        ></div>
                    </div>
                    
                    <!-- Texto de estado -->
                    <span class="status-text">
                        {{ displayConnectionStatus?.text || t('none') }}
                    </span>
                </div>
                
                <!-- Descripción adicional (solo desktop) -->
                <span v-if="displayConnectionStatus?.description" class="status-description">
                    {{ displayConnectionStatus.description }}
                </span>
            </div>

            <!-- Botón de configuración -->
            <button
                @click="$emit('open-modal')"
                class="settings-button"
                :title="t('selectDeviceButton')"
            >
                <svg class="settings-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.527.272 1.096.417 1.71.398h.001z"
                    ></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </button>
        </div>
    </div>
</template>

<style scoped>
/* ===== CONTAINER ===== */
.header-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    background: rgb(17, 24, 39);
    border: 1px solid rgba(6, 182, 212, 0.3);
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 10px rgba(6, 182, 212, 0.1);
    transition: all 0.3s ease;
}

@media (min-width: 640px) {
    .header-container {
        padding: 16px;
        border-radius: 16px;
    }
}

/* Estado "live" - borde más brillante */
.header-live {
    border-color: rgba(34, 197, 94, 0.5);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 15px rgba(34, 197, 94, 0.15);
}

/* ===== DEVICE INFO ===== */
.device-info {
    flex-grow: 1;
    min-width: 0;
    max-width: 200px;
}

@media (min-width: 480px) {
    .device-info {
        max-width: 280px;
    }
}

@media (min-width: 640px) {
    .device-info {
        max-width: 350px;
    }
}

@media (min-width: 768px) {
    .device-info {
        max-width: 450px;
    }
}

.device-name {
    font-size: 12px;
    font-weight: 700;
    color: rgb(34, 211, 238);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (min-width: 640px) {
    .device-name {
        font-size: 14px;
    }
}

.device-name-value {
    margin-left: 4px;
    font-weight: 400;
    color: rgb(229, 231, 235);
}

.vehicle-info {
    margin-top: 2px;
    font-size: 11px;
    color: rgb(156, 163, 175);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (min-width: 640px) {
    .vehicle-info {
        font-size: 12px;
        margin-top: 4px;
    }
}

.vehicle-vin {
    margin-left: 4px;
    font-weight: 300;
}

.vehicle-details {
    color: rgb(107, 114, 128);
    display: none;
}

@media (min-width: 640px) {
    .vehicle-details {
        display: inline;
    }
}

/* ===== ACTIONS CONTAINER ===== */
.actions-container {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

@media (min-width: 640px) {
    .actions-container {
        gap: 16px;
    }
}

/* ===== STATUS DISPLAY ===== */
.status-display {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.status-label {
    font-size: 10px;
    font-weight: 600;
    color: rgb(209, 213, 219);
    display: none;
}

@media (min-width: 480px) {
    .status-label {
        display: block;
    }
}

@media (min-width: 640px) {
    .status-label {
        font-size: 11px;
    }
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ===== STATUS DOT ===== */
.status-dot-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    box-shadow: 0 0 6px currentColor;
    transition: all 0.3s ease;
}

@media (min-width: 640px) {
    .status-dot {
        width: 12px;
        height: 12px;
    }
}

/* Ping animation */
.status-ping {
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgb(34, 197, 94);
    opacity: 0.75;
    animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@media (min-width: 640px) {
    .status-ping {
        width: 12px;
        height: 12px;
    }
}

@keyframes ping {
    0% {
        transform: scale(1);
        opacity: 0.75;
    }
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

/* ===== STATUS TEXT ===== */
.status-text {
    font-size: 11px;
    font-weight: 500;
    color: rgb(229, 231, 235);
}

@media (min-width: 640px) {
    .status-text {
        font-size: 12px;
    }
}

.status-description {
    font-size: 9px;
    color: rgb(107, 114, 128);
    display: none;
    max-width: 150px;
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (min-width: 768px) {
    .status-description {
        display: block;
    }
}

@media (min-width: 1024px) {
    .status-description {
        max-width: 200px;
    }
}

/* ===== SETTINGS BUTTON ===== */
.settings-button {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    background: rgb(31, 41, 55);
    border: 1px solid rgba(6, 182, 212, 0.3);
    border-radius: 50%;
    color: rgb(34, 211, 238);
    cursor: pointer;
    transition: all 0.2s ease;
}

@media (min-width: 640px) {
    .settings-button {
        padding: 10px;
    }
}

.settings-button:hover {
    background: rgb(55, 65, 81);
    border-color: rgba(6, 182, 212, 0.5);
    transform: rotate(45deg);
}

.settings-button:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgb(17, 24, 39), 0 0 0 4px rgb(6, 182, 212);
}

.settings-button:active {
    transform: rotate(45deg) scale(0.95);
}

.settings-icon {
    width: 18px;
    height: 18px;
}

@media (min-width: 640px) {
    .settings-icon {
        width: 20px;
        height: 20px;
    }
}

/* ===== COLOR CLASSES ===== */
.bg-green-500 {
    background-color: rgb(34, 197, 94);
}

.shadow-green-500\/50 {
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
}

.bg-yellow-500 {
    background-color: rgb(234, 179, 8);
}

.shadow-yellow-500\/50 {
    box-shadow: 0 0 8px rgba(234, 179, 8, 0.5);
}

.bg-red-500 {
    background-color: rgb(239, 68, 68);
}

.shadow-red-500\/50 {
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
}

.bg-cyan-500 {
    background-color: rgb(6, 182, 212);
}

.shadow-cyan-500\/50 {
    box-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
}

.bg-gray-500 {
    background-color: rgb(107, 114, 128);
}
</style>