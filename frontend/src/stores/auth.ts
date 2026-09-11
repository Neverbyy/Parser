import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { type Credentials, fetchCurrentUser, login, logout } from '@/api/auth'

import type { User } from '@/types/api'

/**
 * Кто сейчас в системе.
 *
 * Это единственное, что живёт в Pinia: guard'ы роутера должны отвечать на
 * вопрос «пустить или нет» синхронно, ещё до первого рендера, а server state
 * (организация и отзывы) остаётся в TanStack Query, который для того и нужен.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  /** Сессию уже проверяли? Нужно, чтобы не дёргать /api/user повторно. */
  const restored = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  /**
   * Восстанавливает сессию по куке при старте приложения.
   *
   * 401 здесь — штатный ответ «сессии нет», а не ошибка: гость просто
   * увидит экран входа.
   */
  async function restore(): Promise<void> {
    if (restored.value) {
      return
    }

    try {
      user.value = await fetchCurrentUser()
    } catch {
      user.value = null
    } finally {
      restored.value = true
    }
  }

  async function logIn(credentials: Credentials): Promise<void> {
    user.value = await login(credentials)
    restored.value = true
  }

  async function logOut(): Promise<void> {
    try {
      await logout()
    } finally {
      // Даже если запрос не дошёл, локально пользователя отпускаем:
      // иначе он останется заперт в интерфейсе, из которого не выйти.
      user.value = null
    }
  }

  /** Сессию оборвал сервер (401). Чистим состояние без сетевых запросов. */
  function clear(): void {
    user.value = null
  }

  return { user, isAuthenticated, restore, logIn, logOut, clear }
})
