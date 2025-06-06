<script setup lang="ts">
import { inject, onMounted, onUnmounted } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  align?: 'start' | 'center' | 'end'
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  align: 'center'
})

const dropdown = inject('dropdown') as {
  isOpen: any
  toggleDropdown: () => void
  closeDropdown: () => void
}

const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement
  if (!target.closest('.relative.inline-block')) {
    dropdown.closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const alignmentClasses = {
  start: 'left-0',
  center: 'left-1/2 transform -translate-x-1/2',
  end: 'right-0'
}
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-100"
    enter-from-class="transform opacity-0 scale-95"
    enter-to-class="transform opacity-100 scale-100"
    leave-active-class="transition ease-in duration-75"
    leave-from-class="transform opacity-100 scale-100"
    leave-to-class="transform opacity-0 scale-95"
  >
    <div
      v-if="dropdown.isOpen"
      :class="cn(
        'absolute z-50 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-gray-700',
        alignmentClasses[align],
        props.class
      )"
    >
      <div class="py-1">
        <slot />
      </div>
    </div>
  </Transition>
</template>
