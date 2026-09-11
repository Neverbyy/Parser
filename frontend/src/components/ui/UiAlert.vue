<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    variant?: 'error' | 'warning' | 'info' | 'success'
    title?: string
  }>(),
  { variant: 'info', title: '' },
)
</script>

<template>
  <div
    :class="['alert', `alert--${props.variant}`]"
    :role="props.variant === 'error' ? 'alert' : 'status'"
  >
    <div class="alert__body">
      <p v-if="props.title" class="alert__title">{{ props.title }}</p>
      <div class="alert__text"><slot /></div>
    </div>

    <div v-if="$slots.action" class="alert__action">
      <slot name="action" />
    </div>
  </div>
</template>

<style scoped>
.alert {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  font-size: 14px;
}

.alert__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.alert__title {
  font-weight: 600;
}

.alert__text {
  overflow-wrap: anywhere;
}

.alert--error {
  background: var(--danger-soft);
  border-color: var(--danger-border);
  color: var(--danger);
}

.alert--warning {
  background: var(--warning-soft);
  border-color: var(--warning-border);
  color: var(--warning);
}

.alert--info {
  background: var(--accent-soft);
  border-color: transparent;
  color: var(--accent);
}

.alert--success {
  background: var(--success-soft);
  border-color: transparent;
  color: var(--success);
}
</style>
