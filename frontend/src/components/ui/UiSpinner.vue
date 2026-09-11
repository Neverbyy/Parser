<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Размер в пикселях. */
    size?: number
  }>(),
  { size: 16 },
)
</script>

<template>
  <span
    class="spinner"
    :style="{ width: `${size}px`, height: `${size}px` }"
    role="status"
    aria-label="Загрузка"
  />
</template>

<style scoped>
.spinner {
  display: inline-block;
  flex: none;
  border: 2px solid currentcolor;
  border-right-color: transparent;
  border-radius: 50%;
  opacity: 0.7;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
