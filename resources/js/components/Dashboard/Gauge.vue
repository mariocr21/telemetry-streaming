<template>
  <div class="gauge flex flex-col h-48">
    <!-- Contenedor del SVG, ocupa todo el espacio disponible y alinea al fondo -->
    <div class="flex-1 flex items-end justify-center">
      <svg viewBox="-50 -10 100 60" class="w-full max-w-xs">
        <!-- Dial semicircular -->
        <path
          d="M -40 0 A 40 40 0 0 1 40 0"
          class="gauge-bg"
        />
        <!-- Aguja -->
        <line
          :transform="`rotate(${angle} 0 0)`"
          x1="0" y1="0"
          x2="0" y2="-35"
          class="gauge-needle"
        />
        <!-- Centro de la aguja -->
        <circle cx="0" cy="0" r="3" class="gauge-center" />
      </svg>
    </div>

    <!-- Texto inferior -->
    <div class="mt-2 text-center">
      <div class="text-xs font-semibold uppercase text-slate-300">{{ title }}</div>
      <div class="font-mono text-2xl font-bold text-cyan-400">
        {{ displayValue }} <span class="text-xs">{{ unit }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title: string
  value: number
  min?: number
  max?: number
  unit?: string
}

const props = defineProps<Props>()

const min = computed(() => props.min ?? 0)
const max = computed(() => props.max ?? 100)
const pct = computed(() => {
  const raw = (props.value - min.value) / (max.value - min.value)
  return Math.min(Math.max(raw, 0), 1)
})
const angle = computed(() => -90 + pct.value * 180)
const displayValue = computed(() => Math.round(props.value))
const unit = computed(() => props.unit ?? '')
const title = computed(() => props.title)
</script>

<style scoped>
.gauge-bg {
  fill: none;
  stroke: rgba(100,100,100,0.4);
  stroke-width: 4;
}
.gauge-needle {
  stroke: #38bdf8;
  stroke-width: 2;
  stroke-linecap: round;
}
.gauge-center {
  fill: #38bdf8;
}
</style>
