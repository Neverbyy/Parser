import { vi } from 'vitest'

// jsdom не реализует прокрутку, а список отзывов вызывает её при смене страницы.
window.scrollTo = vi.fn()
