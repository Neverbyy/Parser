<script setup lang="ts">
import { ref, watch } from 'vue'

import UiButton from '@/components/ui/UiButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { validateOrganizationUrl } from '@/utils/validation'

const props = withDefaults(
  defineProps<{
    /** Уже сохранённая ссылка — подставляется в поле при открытии страницы. */
    savedUrl?: string | null
    saving: boolean
    /** Ошибка от сервера: валидация 422 или сбой запроса. */
    serverError?: string | null
  }>(),
  { savedUrl: null, serverError: null },
)

const emit = defineEmits<{ submit: [url: string] }>()

const url = ref(props.savedUrl ?? '')
const clientError = ref<string | null>(null)

// Ссылка приезжает асинхронно, после загрузки организации.
watch(
  () => props.savedUrl,
  (saved) => {
    if (saved && !url.value) {
      url.value = saved
    }
  },
)

// Как только человек начал править поле, прежняя ошибка перестаёт быть правдой.
watch(url, () => {
  clientError.value = null
})

function submit(): void {
  const error = validateOrganizationUrl(url.value)

  if (error) {
    clientError.value = error

    return
  }

  emit('submit', url.value.trim())
}
</script>

<template>
  <form class="form" novalidate @submit.prevent="submit">
    <UiInput
      v-model="url"
      label="Ссылка на карточку организации"
      type="url"
      placeholder="https://yandex.ru/maps/org/mu-mu/1145449555/"
      hint="Подойдёт любой адрес карточки: со слагом и без, с вкладкой отзывов, с других зеркал Яндекса и короткая ссылка «Поделиться»."
      autocomplete="url"
      :disabled="props.saving"
      :error="clientError ?? props.serverError"
    />

    <UiButton type="submit" :loading="props.saving">
      {{ props.saving ? 'Загружаем отзывы…' : 'Сохранить и загрузить' }}
    </UiButton>

    <p v-if="props.saving" class="form__notice">
      Парсер обходит страницы отзывов — это может занять до минуты.
    </p>
  </form>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 14px;
}

.form :deep(.field) {
  width: 100%;
}

.form__notice {
  font-size: 13px;
  color: var(--text-muted);
}
</style>
