<script setup lang="ts">
withDefaults(defineProps<{ title?: string; description?: string }>(), {
  title: '',
  description: '',
})
</script>

<template>
  <section class="card">
    <header v-if="title || description || $slots.header" class="card__header">
      <div class="card__heading">
        <h2 v-if="title" class="card__title">{{ title }}</h2>
        <p v-if="description" class="card__description">{{ description }}</p>
      </div>
      <slot name="header" />
    </header>

    <slot />
  </section>
</template>

<style scoped>
.card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
}

.card__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.card__heading {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.card__title {
  font-size: 17px;
}

.card__description {
  font-size: 14px;
  color: var(--text-muted);
}
</style>
