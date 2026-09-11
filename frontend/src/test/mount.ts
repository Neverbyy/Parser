import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { mount, type VueWrapper } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'

import type { Component } from 'vue'

/**
 * Роутер на memory-history: в jsdom нет настоящей навигации, а компонентам
 * нужны useRouter и useRoute.
 */
export function createTestRouter(): Router {
  const blank: Component = { template: '<div />' }

  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', redirect: '/settings' },
      { path: '/login', name: 'login', component: blank },
      { path: '/settings', name: 'settings', component: blank },
    ],
  })
}

interface MountResult {
  wrapper: VueWrapper
  router: Router
  queryClient: QueryClient
}

/**
 * Монтирует компонент со всем окружением приложения: Pinia, роутер и
 * TanStack Query. Повторы запросов отключены — в тестах они только
 * растягивают прогон и прячут настоящую ошибку.
 */
export async function mountWithApp(
  component: Component,
  options: { initialRoute?: string } = {},
): Promise<MountResult> {
  const router = createTestRouter()
  await router.push(options.initialRoute ?? '/settings')
  await router.isReady()

  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  const wrapper = mount(component, {
    global: {
      plugins: [createPinia(), router, [VueQueryPlugin, { queryClient }]],
    },
  })

  return { wrapper, router, queryClient }
}
