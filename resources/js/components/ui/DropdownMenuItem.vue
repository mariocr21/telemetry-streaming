<script setup lang="ts">
import { inject } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  asChild?: boolean
  class?: string
}

const props = defineProps<Props>()

const dropdown = inject('dropdown') as {
  isOpen: any
  toggleDropdown: () => void
  closeDropdown: () => void
}

const handleClick = () => {
  dropdown.closeDropdown()
}
</script>

<template>
  <component
    :is="asChild ? 'div' : 'button'"
    :class="cn(
      'block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white',
      props.class
    )"
    @click="handleClick"
  >
    <slot />
  </component>
</template>