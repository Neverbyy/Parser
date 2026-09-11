import type { Pinia } from 'pinia'
import { createRouter, createWebHistory, type Router } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    /** Пускать только вошедших. */
    requiresAuth?: boolean
    /** Пускать только гостей — экран входа не нужен тому, кто уже вошёл. */
    guestOnly?: boolean
  }
}

/**
 * Роутер создаётся функцией, а не на уровне модуля: guard'у нужен конкретный
 * экземпляр Pinia, а обращаться к стору вне setup можно только явно передав его.
 */
export function createAppRouter(pinia: Pinia): Router {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      {
        path: '/',
        redirect: { name: 'settings' },
      },
      {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guestOnly: true },
      },
      {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: '/:pathMatch(.*)*',
        redirect: { name: 'settings' },
      },
    ],
  })

  // В vue-router 5 колбэк next() объявлен устаревшим: guard возвращает
  // либо ничего, либо адрес для перенаправления.
  router.beforeEach((to) => {
    const auth = useAuthStore(pinia)

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return {
        name: 'login',
        // Запоминаем, куда человек шёл, чтобы вернуть его туда после входа.
        query: to.name === 'settings' ? {} : { redirect: to.fullPath },
      }
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
      return { name: 'settings' }
    }
  })

  return router
}
