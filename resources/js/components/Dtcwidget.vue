<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface DTCCode {
    id: number;
    code: string;
    description: string;
    severity: 'high' | 'medium' | 'low' | 'unknown';
    detected_at: string;
}

const props = defineProps<{
    dtcCodes: DTCCode[];
    isRealTimeActive: boolean;
}>();

// Estado interno
const isExpanded = ref(false);
const animateDTC = ref(false);

// Severidades y sus colores/íconos
const severityMap = {
    high: { color: 'red-500', icon: '⚠️' },
    medium: { color: 'amber-500', icon: '⚡' },
    low: { color: 'blue-500', icon: 'ℹ️' },
    unknown: { color: 'gray-500', icon: '❓' },
};

// Computados
const hasCodes = computed(() => props.dtcCodes.length > 0);

const sortedDTCCodes = computed(() => {
    return [...props.dtcCodes].sort((a, b) => {
        // Primero por severidad
        const severityOrder = { high: 1, medium: 2, low: 3, unknown: 4 };
        const severityDiff = severityOrder[a.severity] - severityOrder[b.severity];

        if (severityDiff !== 0) return severityDiff;

        // Luego por fecha (más reciente primero)
        return new Date(b.detected_at).getTime() - new Date(a.detected_at).getTime();
    });
});

const highPriorityCodes = computed(() => {
    return sortedDTCCodes.value.filter((code) => code.severity === 'high');
});

const hasHighPriorityCodes = computed(() => highPriorityCodes.value.length > 0);

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString();
};

// Observadores
watch(
    () => props.dtcCodes.length,
    (newCount, oldCount) => {
        if (newCount > oldCount && oldCount !== 0) {
            // Se agregó un nuevo DTC
            animateDTC.value = true;
            setTimeout(() => {
                animateDTC.value = false;
            }, 2000);
        }
    },
);

// Métodos
const toggleExpand = () => {
    isExpanded.value = !isExpanded.value;
};
</script>

<template>
    <div
        class="dtc-widget relative overflow-hidden rounded-xl border"
        :class="[
            animateDTC ? 'shadow-glow animate-pulse border-red-500/80 bg-red-500/30' : 'border-red-500/30 bg-red-500/10',
            isExpanded ? 'h-auto' : 'h-auto',
            !hasCodes ? 'border-green-500/30 bg-green-500/10' : '',
        ]"
    >
        <!-- Encabezado -->
        <div class="flex cursor-pointer items-center justify-between px-6 py-4" @click="toggleExpand">
            <div class="flex items-center">
                <div :class="hasCodes ? 'animate-pulse text-red-500' : 'text-green-500'">
                    <span v-if="hasCodes" class="text-lg">⚠️</span>
                    <span v-else class="text-lg">✅</span>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-semibold" :class="hasCodes ? 'text-red-400' : 'text-green-400'">
                        {{ hasCodes ? 'Códigos de Error (DTC)' : 'Sistema sin fallos' }}
                    </h3>
                    <p class="text-xs text-slate-400">
                        {{
                            hasCodes
                                ? `${props.dtcCodes.length} código${props.dtcCodes.length !== 1 ? 's' : ''} detectado${props.dtcCodes.length !== 1 ? 's' : ''}`
                                : 'No hay códigos DTC activos'
                        }}
                    </p>
                </div>
            </div>
            <div v-if="hasCodes" class="text-red-500">
                <span v-if="isExpanded">▲</span>
                <span v-else>▼</span>
            </div>
        </div>

        <!-- Lista de Códigos (Expandible) -->
        <div v-if="hasCodes && isExpanded" class="px-6 pb-4">
            <div class="space-y-3">
                <div
                    v-for="code in sortedDTCCodes"
                    :key="code.code"
                    class="rounded-lg border p-3"
                    :class="`border-${severityMap[code.severity].color}/30 bg-${severityMap[code.severity].color}/10`"
                >
                    <div class="flex items-start">
                        <div :class="`text-${severityMap[code.severity].color} mr-2 text-lg`">
                            {{ severityMap[code.severity].icon }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="font-mono text-sm font-bold" :class="`text-${severityMap[code.severity].color}`">
                                    {{ code.code }}
                                </h4>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs uppercase"
                                    :class="`bg-${severityMap[code.severity].color}/20 text-${severityMap[code.severity].color}`"
                                >
                                    {{ code.severity }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-200">{{ code.description }}</p>
                            <p class="mt-1 text-xs text-slate-400">Detectado: {{ formatDate(code.detected_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notificación cuando está colapsado pero hay códigos -->
        <div
            v-if="hasCodes && !isExpanded && hasHighPriorityCodes"
            class="alert-bar mb-1 bg-red-500 px-6 py-2 text-center text-xs font-semibold text-white"
        >
            <span class="animate-pulse">⚠️ {{ highPriorityCodes.length }} código(s) crítico(s) detectado(s)</span>
        </div>
    </div>
</template>

<style scoped>
.shadow-glow {
    box-shadow: 0 0 15px 5px rgba(239, 68, 68, 0.3);
}
</style>
