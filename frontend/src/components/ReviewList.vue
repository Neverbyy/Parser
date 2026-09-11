<script setup lang="ts">
import { computed, toRef } from 'vue'

import PaginationNav from '@/components/PaginationNav.vue'
import ReviewCard from '@/components/ReviewCard.vue'
import UiAlert from '@/components/ui/UiAlert.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { useReviewsQuery } from '@/queries/reviews'

const props = defineProps<{
  /** Запрашивать отзывы только когда разбор завершён. */
  enabled: boolean
}>()

const page = defineModel<number>('page', { required: true })

const { data, error, isPending, isFetching, isError, refetch } = useReviewsQuery(
  page,
  toRef(props, 'enabled'),
)

const reviews = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

/** Первая загрузка: показывать скелет, а не пустой экран. */
const isInitialLoading = computed(() => props.enabled && isPending.value)

/** Листание: данные предыдущей страницы ещё на экране, но уже неактуальны. */
const isSwitchingPage = computed(() => isFetching.value && !isPending.value)

const SKELETON_ROWS = 5

function changePage(next: number): void {
  page.value = next
  // Листать вниз от середины списка неудобно — возвращаем к началу.
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div class="reviews">
    <UiAlert v-if="isError" variant="error" title="Не удалось загрузить отзывы">
      {{ error?.message }}
      <template #action>
        <UiButton variant="secondary" @click="refetch()">Повторить</UiButton>
      </template>
    </UiAlert>

    <div v-else-if="isInitialLoading" class="skeletons" aria-hidden="true">
      <div v-for="row in SKELETON_ROWS" :key="row" class="skeleton">
        <div class="skeleton__avatar" />
        <div class="skeleton__lines">
          <div class="skeleton__line skeleton__line--short" />
          <div class="skeleton__line" />
          <div class="skeleton__line skeleton__line--medium" />
        </div>
      </div>
    </div>

    <p v-else-if="reviews.length === 0" class="reviews__empty">
      Отзывов пока нет.
    </p>

    <template v-else>
      <div class="reviews__list" :class="{ 'reviews__list--switching': isSwitchingPage }">
        <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />
      </div>

      <PaginationNav
        v-if="meta && meta.last_page > 1"
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        :total="meta.total"
        :from="meta.from"
        :to="meta.to"
        :disabled="isSwitchingPage"
        @update:page="changePage"
      />
    </template>
  </div>
</template>

<style scoped>
.reviews {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.reviews__list {
  transition: opacity 0.15s ease;
}

/* Пока едет следующая страница, предыдущая приглушается — видно, что идёт работа. */
.reviews__list--switching {
  opacity: 0.5;
}

.reviews__empty {
  padding: 24px 0;
  text-align: center;
  color: var(--text-muted);
}

.skeletons {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.skeleton {
  display: flex;
  gap: 12px;
}

.skeleton__avatar {
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--surface-muted);
  animation: pulse 1.4s ease-in-out infinite;
}

.skeleton__lines {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
}

.skeleton__line {
  height: 10px;
  border-radius: 4px;
  background: var(--surface-muted);
  animation: pulse 1.4s ease-in-out infinite;
}

.skeleton__line--short {
  width: 30%;
}

.skeleton__line--medium {
  width: 70%;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.45;
  }
}
</style>
