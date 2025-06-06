<!-- SimpleDropdown.vue - Alternativa más simple -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

interface Props {
  align?: 'left' | 'right'
}

const props = withDefaults(defineProps<Props>(), {
  align: 'right'
})

const isOpen = ref(false)
const dropdownRef = ref<HTMLElement>()

const toggle = () => {
  isOpen.value = !isOpen.value
}

const close = () => {
  isOpen.value = false
}

const handleClickOutside = (event: MouseEvent) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

defineExpose({ close })
</script>

<template>
  <div ref="dropdownRef" class="relative inline-block text-left">
    <!-- Trigger -->
    <div @click="toggle">
      <slot name="trigger" />
    </div>

    <!-- Menu -->
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute z-50 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-gray-700',
          align === 'right' ? 'right-0' : 'left-0'
        ]"
      >
        <div class="py-1" role="menu" @click="close">
          <slot />
        </div>
      </div>
    </Transition>
  </div>
</template>