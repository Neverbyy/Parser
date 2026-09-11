<script setup lang="ts">
import { useId } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    type?: 'text' | 'email' | 'password' | 'url'
    placeholder?: string
    hint?: string
    error?: string | null
    disabled?: boolean
    autocomplete?: string
    required?: boolean
  }>(),
  {
    type: 'text',
    placeholder: '',
    hint: '',
    error: null,
    disabled: false,
    autocomplete: undefined,
    required: false,
  },
)

const model = defineModel<string>({ required: true })

const id = useId()
const describedBy = `${id}-description`
</script>

<template>
  <div class="field">
    <label class="field__label" :for="id">{{ props.label }}</label>

    <input
      :id="id"
      v-model="model"
      class="field__input"
      :class="{ 'field__input--invalid': props.error }"
      :type="props.type"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :autocomplete="props.autocomplete"
      :required="props.required"
      :aria-invalid="Boolean(props.error)"
      :aria-describedby="props.error || props.hint ? describedBy : undefined"
    />

    <!-- Ошибка вытесняет подсказку: два сообщения под полем сразу только мешают. -->
    <p v-if="props.error" :id="describedBy" class="field__error">{{ props.error }}</p>
    <p v-else-if="props.hint" :id="describedBy" class="field__hint">{{ props.hint }}</p>
  </div>
</template>

<style scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field__label {
  font-size: 13px;
  font-weight: 550;
  color: var(--text-muted);
}

.field__input {
  width: 100%;
  padding: 10px 12px;
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.field__input::placeholder {
  color: var(--text-subtle);
}

.field__input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}

.field__input:disabled {
  background: var(--surface-muted);
  cursor: not-allowed;
}

.field__input--invalid {
  border-color: var(--danger);
}

.field__input--invalid:focus {
  box-shadow: 0 0 0 3px var(--danger-soft);
}

.field__error {
  font-size: 13px;
  color: var(--danger);
}

.field__hint {
  font-size: 13px;
  color: var(--text-subtle);
}
</style>
