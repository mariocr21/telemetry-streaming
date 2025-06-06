<script setup lang="ts">
import { inject } from 'vue'

interface Props {
  asChild?: boolean
}

const props = defineProps<Props>()

const dropdown = inject('dropdown') as {
  isOpen: any
  toggleDropdown: () => void
  closeDropdown: () => void
}
</script>

<template>
  <div @click="dropdown.toggleDropdown">
    <slot />
  </div>
</template>