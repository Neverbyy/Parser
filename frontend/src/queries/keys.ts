/**
 * Ключи кеша TanStack Query в одном месте — чтобы инвалидация не
 * расходилась с чтением из-за опечатки в массиве.
 */
export const queryKeys = {
  organization: ['organization'] as const,
  reviews: ['organization', 'reviews'] as const,
  reviewsPage: (page: number) => ['organization', 'reviews', page] as const,
}
