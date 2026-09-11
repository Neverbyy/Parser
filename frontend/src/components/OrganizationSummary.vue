<script setup lang="ts">
import { computed } from 'vue'

import StarRating from '@/components/StarRating.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { formatDate, formatNumber, formatRating, pluralize } from '@/utils/format'

import type { Organization } from '@/types/api'

const props = defineProps<{
  organization: Organization
  refreshing: boolean
}>()

const emit = defineEmits<{ refresh: [] }>()

const ratingsLabel = computed(() =>
  pluralize(props.organization.ratings_count ?? 0, ['оценка', 'оценки', 'оценок']),
)

const reviewsLabel = computed(() =>
  pluralize(props.organization.reviews_count ?? 0, ['отзыв', 'отзыва', 'отзывов']),
)

/**
 * Яндекс отдаёт наружу максимум 600 отзывов. Если у организации их больше,
 * честно объясняем разницу, иначе цифры выглядят как ошибка.
 */
const isTruncated = computed(() => {
  const total = props.organization.reviews_count

  return total !== null && props.organization.fetched_reviews_count < total
})
</script>

<template>
  <section class="summary">
    <header class="summary__header">
      <div class="summary__identity">
        <h2 class="summary__name">{{ organization.name ?? 'Организация' }}</h2>
        <p v-if="organization.address" class="summary__address">{{ organization.address }}</p>
        <a class="summary__link" :href="organization.url" target="_blank" rel="noopener noreferrer">
          Открыть в Яндекс.Картах
        </a>
      </div>

      <UiButton variant="secondary" :loading="refreshing" @click="emit('refresh')">
        Обновить данные
      </UiButton>
    </header>

    <dl class="stats">
      <div class="stat stat--accent">
        <dt class="stat__label">Средний рейтинг</dt>
        <dd class="stat__value">
          {{ formatRating(organization.rating) }}
          <StarRating :value="organization.rating" :size="15" />
        </dd>
      </div>

      <div class="stat">
        <dt class="stat__label">Оценок</dt>
        <dd class="stat__value">{{ formatNumber(organization.ratings_count) }}</dd>
        <dd class="stat__note">{{ ratingsLabel }} всего</dd>
      </div>

      <div class="stat">
        <dt class="stat__label">Отзывов</dt>
        <dd class="stat__value">{{ formatNumber(organization.reviews_count) }}</dd>
        <dd class="stat__note">{{ reviewsLabel }} с текстом</dd>
      </div>

      <div class="stat">
        <dt class="stat__label">Загружено</dt>
        <dd class="stat__value">{{ formatNumber(organization.fetched_reviews_count) }}</dd>
        <dd class="stat__note">
          <template v-if="isTruncated">Яндекс отдаёт не больше 600</template>
          <template v-else>все доступные</template>
        </dd>
      </div>
    </dl>

    <p v-if="organization.parsed_at" class="summary__updated">
      Обновлено {{ formatDate(organization.parsed_at) }}
    </p>
  </section>
</template>

<style scoped>
.summary {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.summary__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.summary__identity {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.summary__name {
  font-size: 20px;
}

.summary__address {
  font-size: 14px;
  color: var(--text-muted);
}

.summary__link {
  align-self: flex-start;
  margin-top: 2px;
  font-size: 13px;
  color: var(--accent);
  text-decoration: none;
}

.summary__link:hover {
  text-decoration: underline;
}

.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin: 0;
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px;
  background: var(--surface-muted);
  border-radius: var(--radius-sm);
}

.stat--accent {
  background: var(--accent-soft);
}

.stat__label {
  font-size: 13px;
  color: var(--text-muted);
}

.stat__value {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 24px;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.stat__note {
  margin: 0;
  font-size: 12px;
  color: var(--text-subtle);
}

.summary__updated {
  font-size: 13px;
  color: var(--text-subtle);
}
</style>
