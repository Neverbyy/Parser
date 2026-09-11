import type { Organization, Paginated, Review } from '@/types/api'

/** Готовая организация: числа взяты с реальной карточки «Му-Му». */
export function makeOrganization(overrides: Partial<Organization> = {}): Organization {
  return {
    id: 1,
    yandex_id: '1145449555',
    url: 'https://yandex.ru/maps/org/1145449555/reviews/',
    name: 'Му-Му',
    address: 'ул. Коровий Вал, 1, Москва',
    rating: 4.3,
    ratings_count: 3081,
    reviews_count: 1391,
    fetched_reviews_count: 600,
    status: 'ready',
    error_message: null,
    parsed_at: '2026-09-11T18:57:12+00:00',
    ...overrides,
  }
}

export function makeReview(overrides: Partial<Review> = {}): Review {
  return {
    id: 1,
    author_name: 'Кучеров Евгений',
    author_avatar_url: null,
    rating: 2,
    text: 'Стало очень дорого и многое невкусно.',
    published_at: '2026-09-08T23:35:36.482Z',
    ...overrides,
  }
}

/** Страница отзывов с метаданными Laravel-пагинации. */
export function makeReviewsPage(
  reviews: Review[],
  meta: Partial<Paginated<Review>['meta']> = {},
): Paginated<Review> {
  return {
    data: reviews,
    meta: {
      current_page: 1,
      from: 1,
      last_page: 12,
      per_page: 50,
      to: 50,
      total: 600,
      ...meta,
    },
  }
}
