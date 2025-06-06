<!-- DropdownMenu.vue -->
<script setup lang="ts">
import { ref, provide } from 'vue'

const isOpen = ref(false)

const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const closeDropdown = () => {
  isOpen.value = false
}

provide('dropdown', {
  isOpen,
  toggleDropdown,
  closeDropdown
})
</script>

<template>
  <div class="relative inline-block text-left">
    <slot />
  </div>
</template>