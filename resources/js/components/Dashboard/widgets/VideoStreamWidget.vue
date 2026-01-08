<script setup lang="ts">
/**
 * VideoStreamWidget.vue
 * 
 * Displays a live video stream from MediaMTX server via WebRTC.
 * Supports fullscreen mode and connection status indicator.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Maximize2, Minimize2, Video, VideoOff, X, RefreshCw } from 'lucide-vue-next';

interface Props {
    streamBaseUrl?: string;
    channelId?: string;
    label?: string;
    autoplay?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    streamBaseUrl: 'https://stream.neurona.xyz',
    channelId: 'movil1',
    label: 'Cámara 1',
    autoplay: true,
});

// State
const isFullscreen = ref(false);
const isConnected = ref(false);
const isLoading = ref(true);
const hasError = ref(false);
const iframeRef = ref<HTMLIFrameElement | null>(null);

// Computed
const streamUrl = computed(() => {
    if (!props.channelId || !props.channelId.trim()) return '';
    // Normalize base URL (remove trailing slashes)
    const baseUrl = props.streamBaseUrl.replace(/\/+$/, '');
    const channel = props.channelId.trim();
    return `${baseUrl}/${channel}`;
});

const hasValidChannel = computed(() => {
    return props.channelId && props.channelId.trim() !== '';
});

// Methods
function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value;
    
    if (isFullscreen.value) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function closeFullscreen() {
    isFullscreen.value = false;
    document.body.style.overflow = '';
}

function handleIframeLoad() {
    isLoading.value = false;
    isConnected.value = true;
    hasError.value = false;
}

function handleIframeError() {
    isLoading.value = false;
    isConnected.value = false;
    hasError.value = true;
}

function reloadStream() {
    isLoading.value = true;
    hasError.value = false;
    
    if (iframeRef.value) {
        const currentSrc = iframeRef.value.src;
        iframeRef.value.src = '';
        setTimeout(() => {
            if (iframeRef.value) {
                iframeRef.value.src = currentSrc;
            }
        }, 100);
    }
}

// Cleanup on unmount
onUnmounted(() => {
    document.body.style.overflow = '';
});

// Handle escape key
function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && isFullscreen.value) {
        closeFullscreen();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div class="video-stream-widget relative w-full h-full rounded-lg overflow-hidden bg-slate-900 border border-slate-700">
        
        <!-- No Channel Configured -->
        <div v-if="!hasValidChannel" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-800/90 text-center p-4">
            <VideoOff class="w-12 h-12 text-slate-500 mb-3" />
            <span class="text-sm font-medium text-slate-400">Sin Canal Configurado</span>
            <span class="text-xs text-slate-500 mt-1">Configure el ID de canal en las propiedades</span>
        </div>
        
        <!-- Stream Container -->
        <template v-else>
            <!-- Loading Overlay -->
            <div v-if="isLoading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-slate-900/80 backdrop-blur-sm">
                <div class="w-10 h-10 border-3 border-cyan-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                <span class="text-sm text-slate-400">Conectando...</span>
            </div>
            
            <!-- Error Overlay -->
            <div v-if="hasError" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-slate-900/90 text-center p-4">
                <VideoOff class="w-12 h-12 text-red-500 mb-3" />
                <span class="text-sm font-medium text-red-400">Stream No Disponible</span>
                <button 
                    @click="reloadStream"
                    class="mt-3 flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-sm text-white transition-colors"
                >
                    <RefreshCw class="w-4 h-4" />
                    Reintentar
                </button>
            </div>
            
            <!-- Header Bar -->
            <div class="absolute top-0 left-0 right-0 z-20 flex items-center justify-between px-3 py-2 bg-gradient-to-b from-slate-900/90 to-transparent">
                <div class="flex items-center gap-2">
                    <div 
                        class="w-2 h-2 rounded-full"
                        :class="isConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500'"
                    ></div>
                    <span class="text-xs font-semibold text-white">{{ label }}</span>
                </div>
                
                <div class="flex items-center gap-1">
                    <button 
                        @click="reloadStream"
                        class="p-1.5 rounded hover:bg-white/10 transition-colors"
                        title="Recargar stream"
                    >
                        <RefreshCw class="w-3.5 h-3.5 text-white/70" />
                    </button>
                    <button 
                        @click="toggleFullscreen"
                        class="p-1.5 rounded hover:bg-white/10 transition-colors"
                        title="Pantalla completa"
                    >
                        <Maximize2 class="w-3.5 h-3.5 text-white/70" />
                    </button>
                </div>
            </div>
            
            <!-- Video iframe -->
            <iframe
                ref="iframeRef"
                :src="streamUrl"
                class="w-full h-full border-0"
                allow="autoplay; fullscreen"
                @load="handleIframeLoad"
                @error="handleIframeError"
            ></iframe>
        </template>
        
        <!-- Fullscreen Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div 
                    v-if="isFullscreen" 
                    class="fixed inset-0 z-[9999] bg-black flex flex-col"
                >
                    <!-- Fullscreen Header -->
                    <div class="flex items-center justify-between px-4 py-3 bg-slate-900/90 border-b border-slate-700">
                        <div class="flex items-center gap-3">
                            <Video class="w-5 h-5 text-cyan-500" />
                            <span class="text-lg font-bold text-white">{{ label }}</span>
                            <span class="text-sm text-slate-400">({{ channelId }})</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <button 
                                @click="reloadStream"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-sm text-white transition-colors"
                            >
                                <RefreshCw class="w-4 h-4" />
                                Recargar
                            </button>
                            <button 
                                @click="closeFullscreen"
                                class="p-2 rounded-lg bg-slate-700 hover:bg-red-600 text-white transition-colors"
                            >
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    
                    <!-- Fullscreen Video -->
                    <div class="flex-1 relative">
                        <iframe
                            :src="streamUrl"
                            class="absolute inset-0 w-full h-full border-0"
                            allow="autoplay; fullscreen"
                        ></iframe>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.border-3 {
    border-width: 3px;
}
</style>
