import { describe, expect, it } from 'vitest'

import { validateOrganizationUrl } from './validation'

describe('validateOrganizationUrl', () => {
  it.each([
    ['со слагом', 'https://yandex.ru/maps/org/yandeks/1124715036/'],
    ['без слага', 'https://yandex.ru/maps/org/1124715036/'],
    ['вкладка отзывов', 'https://yandex.ru/maps/org/yandeks/1124715036/reviews/'],
    ['с query-хвостом', 'https://yandex.ru/maps/org/yandeks/1124715036/?ll=37.58%2C55.73&z=17'],
    ['турецкое зеркало', 'https://yandex.com.tr/harita/org/1124715036/'],
    ['казахское зеркало', 'https://yandex.kz/maps/org/yandeks/1124715036/'],
    ['профиль организации', 'https://yandex.ru/profile/1124715036'],
    ['поисковая выдача с oid', 'https://yandex.ru/maps/213/moscow/search/?oid=1124715036&ol=biz'],
    ['короткая ссылка', 'https://yandex.ru/maps/-/CDxxxxxx'],
    ['без протокола', 'yandex.ru/maps/org/yandeks/1124715036/'],
    ['с пробелами по краям', '   https://yandex.ru/maps/org/1124715036/   '],
  ])('принимает ссылку: %s', (_name, url) => {
    expect(validateOrganizationUrl(url)).toBeNull()
  })

  it('требует заполнить поле', () => {
    expect(validateOrganizationUrl('')).toBe('Укажите ссылку на организацию.')
    expect(validateOrganizationUrl('   ')).toBe('Укажите ссылку на организацию.')
  })

  it('отклоняет чужой домен', () => {
    expect(validateOrganizationUrl('https://maps.google.com/place/12345')).toBe(
      'Ссылка должна вести на Яндекс.Карты.',
    )
  })

  it('не обманывается доменом, похожим на яндексовый', () => {
    expect(validateOrganizationUrl('https://yandex.ru.evil.com/maps/org/1124715036/')).toBe(
      'Ссылка должна вести на Яндекс.Карты.',
    )
  })

  it.each([
    ['главная карт', 'https://yandex.ru/maps/'],
    ['город без организации', 'https://yandex.ru/maps/213/moscow/'],
    ['категория без организации', 'https://yandex.ru/maps/213/moscow/category/cafe/184106390/'],
  ])('отклоняет ссылку без идентификатора: %s', (_name, url) => {
    expect(validateOrganizationUrl(url)).toContain('идентификатор организации')
  })
})
