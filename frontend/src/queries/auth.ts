import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { useAuthStore } from '@/stores/auth'

import type { Credentials } from '@/api/auth'
import type { ApiError } from '@/types/api'

/**
 * Вход и выход оформлены мутациями: состояние запроса (идёт / упал / с какой
 * ошибкой) ведёт Query, а Pinia хранит только результат — кто вошёл.
 */
export function useLoginMutation() {
  const auth = useAuthStore()
  const queryClient = useQueryClient()

  return useMutation<void, ApiError, Credentials>({
    mutationFn: (credentials) => auth.logIn(credentials),
    onSuccess: () => {
      // Кеш мог остаться от предыдущего пользователя.
      queryClient.clear()
    },
  })
}

export function useLogoutMutation() {
  const auth = useAuthStore()
  const queryClient = useQueryClient()

  return useMutation<void, ApiError, void>({
    mutationFn: () => auth.logOut(),
    onSettled: () => {
      // Чистим и при ошибке: локально пользователь всё равно вышел.
      queryClient.clear()
    },
  })
}
