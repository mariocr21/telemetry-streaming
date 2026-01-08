<script setup lang="ts">
/**
 * GearScaleWidget.vue
 * 
 * Widget exclusivo de Neurona Telemetry que muestra la marcha actual
 * junto con una escala visual de las marchas disponibles.
 * 
 * Estilo: Neurona Premium
 */
import { computed } from 'vue';

interface Props {
    value: number;
    // Rango de marchas hacia adelante (ej. 1 a 6)
    maxGear?: number;
    label?: string;
    // Si mostrar R y N en la escala
    showReverse?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    maxGear: 6,
    label: 'GEAR',
    showReverse: true,
});

const currentGearLabel = computed(() => {
    const val = Math.round(props.value);
    if (val === 0) return 'N';
    if (val < 0) return 'R';
    return val.toString();
});

// Generamos el array de la escala
const scaleItems = computed(() => {
    const items = [];
    if (props.showReverse) items.push({ value: -1, label: 'R' });
    items.push({ value: 0, label: 'N' });
    for (let i = 1; i <= props.maxGear; i++) {
        items.push({ value: i, label: i.toString() });
    }
    return items;
});

const activeIndex = computed(() => {
    const val = Math.round(props.value);
    return scaleItems.value.findIndex(item => item.value === val);
});

// Colores por tipo de marcha
const gearColor = computed(() => {
    const val = Math.round(props.value);
    if (val === 0) return 'var(--neurona-primary)'; // N - Verde
    if (val < 0) return 'var(--zone-critical)';     // R - Rojo
    // Shift light logic simu: High gears = warn? No, normal white/cyan
    if (val >= props.maxGear) return 'var(--neurona-gold)'; // Max gear
    return 'white';
});

// Offset para centrar la escala en la marcha actual
// Queremos que el item activo esté siempre en el centro si es posible, 
// o simplemente mostrar la tira completa si cabe.
// Para este diseño, mostraremos la tira completa horizontal.
</script>

<template>
    <div class="gear-scale-widget w-full h-full flex flex-col items-center justify-center p-4 relative overflow-hidden">
        <!-- Background accents -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900/50 to-slate-900/80 pointer-events-none"></div>
        <div class="absolute top-0 w-full h-px bg-gradient-to-r from-transparent via-[var(--neurona-primary-dim)] to-transparent opacity-30"></div>
        
        <!-- Main Gear Display -->
        <div class="relative z-10 flex flex-col items-center justify-center mb-2">
            <!-- Arrows indicators -->
            <div class="flex items-center gap-6">
                <div class="w-4 h-4 text-slate-600 transition-colors"
                     :class="{ 'text-[var(--neurona-accent)] animate-pulse': activeIndex > 0 && activeIndex < scaleItems.length - 1 }">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </div>

                <span 
                    class="text-[4rem] font-black leading-none tabular-nums font-mono tracking-tighter"
                    :style="{ 
                        color: gearColor,
                        textShadow: `0 0 30px ${gearColor}66`
                    }"
                >
                    {{ currentGearLabel }}
                </span>

                <div class="w-4 h-4 text-slate-600 transition-colors"
                     :class="{ 'text-[var(--neurona-accent)] animate-pulse': activeIndex < scaleItems.length - 1 }">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                </div>
            </div>
            
            <span class="text-[10px] uppercase tracking-[0.2em] text-slate-500 font-bold mt-1">
                {{ label }}
            </span>
        </div>

        <!-- Linear Scale -->
        <div class="relative z-10 w-full max-w-[80%] mt-2">
            <!-- Scale line -->
            <div class="absolute top-1/2 left-0 w-full h-0.5 bg-slate-800 -translate-y-1/2"></div>
            
            <div class="flex justify-between items-center relative">
                <div 
                    v-for="item in scaleItems" 
                    :key="item.value"
                    class="relative flex flex-col items-center group"
                >
                    <!-- Marker -->
                    <div 
                        class="w-1.5 h-1.5 rounded-full mb-2 transition-all duration-300"
                        :class="[
                            Math.round(value) === item.value 
                                ? 'bg-[var(--neurona-primary)] w-2.5 h-2.5 shadow-[0_0_10px_var(--neurona-primary)]' 
                                : 'bg-slate-700'
                        ]"
                    ></div>
                    
                    <!-- Label -->
                    <span 
                        class="text-[10px] font-mono font-bold transition-colors duration-300"
                        :class="[
                            Math.round(value) === item.value
                                ? 'text-white scale-110' 
                                : 'text-slate-600'
                        ]"
                    >
                        {{ item.label }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gear-scale-widget {
    background: var(--neurona-bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}
</style>
