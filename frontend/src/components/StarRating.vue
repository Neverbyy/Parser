<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    /** Оценка от 1 до 5. `null` — оценки нет. */
    value: number | null
    size?: number
  }>(),
  { size: 16 },
)

const STARS = [1, 2, 3, 4, 5]

const rounded = computed(() => (props.value === null ? 0 : Math.round(props.value)))

const label = computed(() =>
  props.value === null ? 'Без оценки' : `Оценка ${props.value} из 5`,
)
</script>

<template>
  <span class="stars" role="img" :aria-label="label">
    <svg
      v-for="star in STARS"
      :key="star"
      class="stars__item"
      :class="{ 'stars__item--filled': star <= rounded }"
      :width="props.size"
      :height="props.size"
      viewBox="0 0 20 20"
      aria-hidden="true"
    >
      <path
        d="M10 1.6l2.47 5.28 5.53.77-4 4.06.95 5.69L10 14.7l-4.95 2.7.95-5.69-4-4.06 5.53-.77z"
      />
    </svg>
  </span>
</template>

<style scoped>
.stars {
  display: inline-flex;
  gap: 2px;
  line-height: 0;
}

.stars__item {
  fill: var(--border-strong);
}

.stars__item--filled {
  fill: var(--star);
}
</style>
