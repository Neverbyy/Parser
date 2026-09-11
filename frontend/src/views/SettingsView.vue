<script setup lang="ts">
import { computed, ref } from 'vue'

import AppHeader from '@/components/AppHeader.vue'
import OrganizationForm from '@/components/OrganizationForm.vue'
import OrganizationSummary from '@/components/OrganizationSummary.vue'
import ReviewList from '@/components/ReviewList.vue'
import UiAlert from '@/components/ui/UiAlert.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSpinner from '@/components/ui/UiSpinner.vue'
import {
  isInProgress,
  useOrganizationQuery,
  useRefreshOrganization,
  useSaveOrganization,
} from '@/queries/organization'

const page = ref(1)

const {
  data: organization,
  error: loadError,
  isPending: isLoadingOrganization,
  isError: hasLoadError,
  refetch: reloadOrganization,
} = useOrganizationQuery()

const { mutate: save, isPending: isSaving, error: saveError } = useSaveOrganization()
const { mutate: refresh, isPending: isRefreshing } = useRefreshOrganization()

const status = computed(() => organization.value?.status)
const isReady = computed(() => status.value === 'ready')
const isParsing = computed(() => isInProgress(status.value))
const hasFailed = computed(() => status.value === 'failed')

/**
 * Ошибку валидации ссылки показываем под полем, всё остальное — как есть.
 */
const formError = computed(() => {
  const error = saveError.value

  if (!error) {
    return null
  }

  return error.errors?.url?.[0] ?? error.message
})

function handleSave(url: string): void {
  save(url, { onSuccess: () => (page.value = 1) })
}

function handleRefresh(): void {
  refresh(undefined, { onSuccess: () => (page.value = 1) })
}
</script>

<template>
  <AppHeader />

  <main class="page">
    <UiCard
      title="Ссылка на организацию"
      description="Вставьте адрес карточки в Яндекс.Картах — мы соберём рейтинг и все доступные отзывы."
    >
      <OrganizationForm
        :saved-url="organization?.url"
        :saving="isSaving"
        :server-error="formError"
        @submit="handleSave"
      />
    </UiCard>

    <!-- Не удалось прочитать сохранённую организацию (сеть, сервер лёг). -->
    <UiAlert v-if="hasLoadError" variant="error" title="Не удалось загрузить данные">
      {{ loadError?.message }}
      <template #action>
        <UiButton variant="secondary" @click="reloadOrganization()">Повторить</UiButton>
      </template>
    </UiAlert>

    <div v-else-if="isLoadingOrganization" class="page__loading">
      <UiSpinner :size="18" />
      <span>Загружаем сохранённые настройки…</span>
    </div>

    <template v-else-if="organization">
      <!-- Разбор идёт в фоне: это состояние видно только при очереди. -->
      <UiAlert v-if="isParsing" variant="info">
        <span class="page__parsing">
          <UiSpinner :size="14" />
          <!-- Бэкенд обновляет счётчик по ходу обхода, так что прогресс
               виден ещё до того, как разбор закончится. -->
          <template v-if="organization.fetched_reviews_count > 0">
            Собираем отзывы — уже {{ organization.fetched_reviews_count }}…
          </template>
          <template v-else> Собираем отзывы, это может занять до минуты… </template>
        </span>
      </UiAlert>

      <UiAlert v-else-if="hasFailed" variant="error" title="Не получилось собрать отзывы">
        {{ organization.error_message ?? 'Причина неизвестна.' }}
        <template #action>
          <UiButton variant="secondary" :loading="isRefreshing" @click="handleRefresh">
            Попробовать снова
          </UiButton>
        </template>
      </UiAlert>

      <UiCard v-if="isReady">
        <OrganizationSummary
          :organization="organization"
          :refreshing="isRefreshing"
          @refresh="handleRefresh"
        />
      </UiCard>

      <UiCard v-if="isReady" title="Отзывы" description="По 50 отзывов на странице.">
        <ReviewList v-model:page="page" :enabled="isReady" />
      </UiCard>
    </template>

    <!-- Ссылку ещё не сохраняли. -->
    <p v-else class="page__hint">
      Пока ничего не сохранено. Вставьте ссылку выше, чтобы увидеть рейтинг и отзывы.
    </p>
  </main>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: var(--page-width);
  margin: 0 auto;
  padding: 24px;
}

.page__loading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 24px;
  color: var(--text-muted);
}

.page__parsing {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.page__hint {
  padding: 8px 4px;
  color: var(--text-muted);
}
</style>
