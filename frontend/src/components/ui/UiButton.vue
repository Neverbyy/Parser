<script setup lang="ts">
import UiSpinner from './UiSpinner.vue'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary'
    type?: 'button' | 'submit'
    loading?: boolean
    disabled?: boolean
    block?: boolean
  }>(),
  {
    variant: 'primary',
    type: 'button',
    loading: false,
    disabled: false,
    block: false,
  },
)
</script>

<template>
  <button
    :type="props.type"
    :class="['btn', `btn--${props.variant}`, { 'btn--block': props.block }]"
    :disabled="props.disabled || props.loading"
    :aria-busy="props.loading"
  >
    <UiSpinner v-if="props.loading" :size="14" />
    <slot />
  </button>
</template>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 18px;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  font-weight: 550;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    opacity 0.15s ease;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.btn--block {
  width: 100%;
}

.btn--primary {
  background: var(--accent);
  color: #fff;
}

.btn--primary:hover:not(:disabled) {
  background: var(--accent-hover);
}

.btn--secondary {
  background: var(--surface);
  border-color: var(--border-strong);
  color: var(--text);
}

.btn--secondary:hover:not(:disabled) {
  background: var(--surface-muted);
}

/* В тёмной теме акцент светлый, поэтому текст на нём должен быть тёмным. */
@media (prefers-color-scheme: dark) {
  .btn--primary {
    color: #14161a;
  }
}
</style>
