import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { fetchOrganization, refreshOrganization, saveOrganization } from '@/api/organization'
import { fetchReviews } from '@/api/reviews'
import { makeOrganization, makeReview, makeReviewsPage } from '@/test/fixtures'
import { mountWithApp } from '@/test/mount'

import SettingsView from './SettingsView.vue'

vi.mock('@/api/organization', () => ({
  fetchOrganization: vi.fn(),
  saveOrganization: vi.fn(),
  refreshOrganization: vi.fn(),
}))

vi.mock('@/api/reviews', () => ({
  fetchReviews: vi.fn(),
  REVIEWS_PER_PAGE: 50,
}))

vi.mock('@/api/auth', () => ({
  login: vi.fn(),
  logout: vi.fn(),
  fetchCurrentUser: vi.fn(),
}))

const fetchOrganizationMock = vi.mocked(fetchOrganization)
const saveOrganizationMock = vi.mocked(saveOrganization)
const refreshOrganizationMock = vi.mocked(refreshOrganization)
const fetchReviewsMock = vi.mocked(fetchReviews)

async function mountSettings() {
  const mounted = await mountWithApp(SettingsView)
  await flushPromises()

  return mounted
}

describe('SettingsView', () => {
  beforeEach(() => {
    fetchOrganizationMock.mockReset()
    saveOrganizationMock.mockReset()
    refreshOrganizationMock.mockReset()
    fetchReviewsMock.mockReset()

    fetchReviewsMock.mockResolvedValue(makeReviewsPage([makeReview()]))
  })

  describe('когда ссылка ещё не сохранена', () => {
    it('предлагает вставить ссылку', async () => {
      fetchOrganizationMock.mockResolvedValue(null)

      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('Пока ничего не сохранено')
    })

    it('не ходит за отзывами', async () => {
      fetchOrganizationMock.mockResolvedValue(null)

      await mountSettings()

      expect(fetchReviewsMock).not.toHaveBeenCalled()
    })
  })

  describe('валидация ссылки', () => {
    it('не отправляет запрос при ссылке не на Яндекс.Карты', async () => {
      fetchOrganizationMock.mockResolvedValue(null)

      const { wrapper } = await mountSettings()
      await wrapper.find('input[type="url"]').setValue('https://maps.google.com/place/1')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(saveOrganizationMock).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Ссылка должна вести на Яндекс.Карты.')
    })

    it('отправляет корректную ссылку', async () => {
      fetchOrganizationMock.mockResolvedValue(null)
      saveOrganizationMock.mockResolvedValue(makeOrganization())

      const { wrapper } = await mountSettings()
      await wrapper.find('input[type="url"]').setValue('https://yandex.ru/maps/org/1145449555/')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(saveOrganizationMock).toHaveBeenCalledWith('https://yandex.ru/maps/org/1145449555/')
    })

    it('показывает ошибку валидации с сервера', async () => {
      fetchOrganizationMock.mockResolvedValue(null)
      saveOrganizationMock.mockRejectedValue({
        message: 'Проверьте данные.',
        status: 422,
        errors: { url: ['По этой ссылке не нашлось карточки организации.'] },
      })

      const { wrapper } = await mountSettings()
      await wrapper.find('input[type="url"]').setValue('https://yandex.ru/maps/org/1145449555/')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).toContain('По этой ссылке не нашлось карточки организации.')
    })
  })

  describe('когда организация разобрана', () => {
    beforeEach(() => {
      fetchOrganizationMock.mockResolvedValue(makeOrganization())
    })

    it('показывает средний рейтинг', async () => {
      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('4.3')
    })

    it('разделяет количество оценок и количество отзывов', async () => {
      const { wrapper } = await mountSettings()
      const text = wrapper.text().replace(/\s| /g, '')

      expect(text).toContain('Оценок3081')
      expect(text).toContain('Отзывов1391')
    })

    it('объясняет, почему загружено меньше, чем всего отзывов', async () => {
      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('Яндекс отдаёт не больше 600')
    })

    it('показывает автора, дату, текст и оценку отзыва', async () => {
      const { wrapper } = await mountSettings()
      const text = wrapper.text()

      expect(text).toContain('Кучеров Евгений')
      expect(text).toContain('Стало очень дорого и многое невкусно.')
      expect(text).toContain('2026')
      expect(wrapper.find('[aria-label="Оценка 2 из 5"]').exists()).toBe(true)
    })

    it('подписывает отзыв без автора как анонимный', async () => {
      fetchReviewsMock.mockResolvedValue(makeReviewsPage([makeReview({ author_name: null })]))

      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('Аноним')
    })

    it('запрашивает первую страницу отзывов', async () => {
      await mountSettings()

      expect(fetchReviewsMock).toHaveBeenCalledWith(1)
    })

    it('по клику на следующую страницу запрашивает вторую', async () => {
      const { wrapper } = await mountSettings()

      await wrapper.find('[aria-label="Следующая страница"]').trigger('click')
      await flushPromises()

      expect(fetchReviewsMock).toHaveBeenLastCalledWith(2)
    })

    it('показывает диапазон и общее число отзывов', async () => {
      const { wrapper } = await mountSettings()
      const text = wrapper.text().replace(/\s| /g, '')

      expect(text).toContain('Показаны1–50из600')
    })

    it('перечитывает данные по кнопке обновления', async () => {
      refreshOrganizationMock.mockResolvedValue(makeOrganization({ rating: 4.4 }))

      const { wrapper } = await mountSettings()
      const refreshButton = wrapper
        .findAll('button')
        .find((button) => button.text().includes('Обновить данные'))

      await refreshButton?.trigger('click')
      await flushPromises()

      expect(refreshOrganizationMock).toHaveBeenCalled()
    })
  })

  describe('когда разбор не удался', () => {
    it('показывает причину', async () => {
      fetchOrganizationMock.mockResolvedValue(
        makeOrganization({
          status: 'failed',
          error_message: 'Яндекс запросил проверку на робота. Подождите немного.',
          fetched_reviews_count: 0,
        }),
      )

      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('Яндекс запросил проверку на робота')
    })

    it('не показывает отзывы', async () => {
      fetchOrganizationMock.mockResolvedValue(
        makeOrganization({ status: 'failed', error_message: 'Страница недоступна.' }),
      )

      await mountSettings()

      expect(fetchReviewsMock).not.toHaveBeenCalled()
    })
  })

  describe('когда разбор ещё идёт', () => {
    it('показывает индикатор ожидания', async () => {
      fetchOrganizationMock.mockResolvedValue(makeOrganization({ status: 'parsing' }))

      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('Собираем отзывы')
    })
  })

  describe('когда бэкенд недоступен', () => {
    it('показывает ошибку и кнопку повтора', async () => {
      fetchOrganizationMock.mockRejectedValue({
        message: 'Не удалось связаться с сервером. Проверьте, запущен ли бэкенд.',
        status: 0,
      })

      const { wrapper } = await mountSettings()

      expect(wrapper.text()).toContain('Не удалось связаться с сервером')
      expect(wrapper.text()).toContain('Повторить')
    })
  })
})
