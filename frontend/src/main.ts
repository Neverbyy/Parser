import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import { onUnauthorized } from './api/client'
import { createAppRouter } from './router'
import { useAuthStore } from './stores/auth'

import './styles/main.css'

async function bootstrap(): Promise<void> {
  const app = createApp(App)

  const pinia = createPinia()
  app.use(pinia)

  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        // Данные организации меняются только по нашей команде,
        // поэтому лишние перезапросы при возврате во вкладку не нужны.
        refetchOnWindowFocus: false,
        staleTime: 30_000,
        retry: 1,
      },
      mutations: {
        // Повторять мутацию вслепую опасно: парсинг тяжёлый,
        // а вход должен падать сразу и понятно.
        retry: 0,
      },
    },
  })
  app.use(VueQueryPlugin, { queryClient })

  const auth = useAuthStore(pinia)

  // Сервер оборвал сессию — сбрасываем и личность, и кеш чужих данных.
  onUnauthorized(() => {
    auth.clear()
    queryClient.clear()
  })

  // Проверяем сессию до первой навигации: иначе при перезагрузке страницы
  // guard увидел бы «не авторизован» и выбросил на экран входа.
  await auth.restore()

  const router = createAppRouter(pinia)
  app.use(router)
  await router.isReady()

  app.mount('#app')
}

void bootstrap()
