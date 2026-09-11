import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { login } from '@/api/auth'
import { mountWithApp } from '@/test/mount'

import LoginView from './LoginView.vue'

vi.mock('@/api/auth', () => ({
  login: vi.fn(),
  logout: vi.fn(),
  fetchCurrentUser: vi.fn(),
}))

const loginMock = vi.mocked(login)

async function fillAndSubmit(wrapper: Awaited<ReturnType<typeof mountWithApp>>['wrapper']) {
  await wrapper.find('input[type="email"]').setValue('demo@example.com')
  await wrapper.find('input[type="password"]').setValue('password')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

describe('LoginView', () => {
  beforeEach(() => {
    loginMock.mockReset()
  })

  it('отправляет введённые данные', async () => {
    loginMock.mockResolvedValue({ id: 1, name: 'Демо', email: 'demo@example.com' })

    const { wrapper } = await mountWithApp(LoginView, { initialRoute: '/login' })
    await fillAndSubmit(wrapper)

    expect(loginMock).toHaveBeenCalledWith({
      email: 'demo@example.com',
      password: 'password',
      remember: false,
    })
  })

  it('после успешного входа уводит на страницу настроек', async () => {
    loginMock.mockResolvedValue({ id: 1, name: 'Демо', email: 'demo@example.com' })

    const { wrapper, router } = await mountWithApp(LoginView, { initialRoute: '/login' })
    await fillAndSubmit(wrapper)

    expect(router.currentRoute.value.name).toBe('settings')
  })

  it('показывает ошибку неверных данных под полем', async () => {
    loginMock.mockRejectedValue({
      message: 'Проверьте данные.',
      status: 422,
      errors: { email: ['Неверный email или пароль.'] },
    })

    const { wrapper } = await mountWithApp(LoginView, { initialRoute: '/login' })
    await fillAndSubmit(wrapper)

    expect(wrapper.text()).toContain('Неверный email или пароль.')
  })

  it('показывает ошибку сети отдельным сообщением', async () => {
    loginMock.mockRejectedValue({
      message: 'Не удалось связаться с сервером. Проверьте, запущен ли бэкенд.',
      status: 0,
    })

    const { wrapper } = await mountWithApp(LoginView, { initialRoute: '/login' })
    await fillAndSubmit(wrapper)

    expect(wrapper.find('[role="alert"]').text()).toContain('Не удалось связаться с сервером')
  })

  it('блокирует кнопку, пока запрос в пути', async () => {
    loginMock.mockImplementation(() => new Promise(() => {}))

    const { wrapper } = await mountWithApp(LoginView, { initialRoute: '/login' })
    await fillAndSubmit(wrapper)

    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })
})
