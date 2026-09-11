<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import UiAlert from '@/components/ui/UiAlert.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { useLoginMutation } from '@/queries/auth'

const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)

const { mutate, isPending, error, reset } = useLoginMutation()

/** Ошибки по конкретным полям приходят от Laravel при 422. */
const fieldErrors = computed(() => error.value?.errors ?? {})

const emailError = computed(() => fieldErrors.value.email?.[0] ?? null)
const passwordError = computed(() => fieldErrors.value.password?.[0] ?? null)

/**
 * Общая ошибка — всё, что не разложилось по полям: сеть, троттлинг, 500.
 * Неверный пароль сервер возвращает как ошибку поля email, и он уже показан
 * под полем — дублировать его сверху не нужно.
 */
const generalError = computed(() => {
  if (!error.value || error.value.errors) {
    return null
  }

  return error.value.message
})

function submit(): void {
  reset()

  mutate(
    { email: email.value, password: password.value, remember: remember.value },
    {
      onSuccess: () => {
        const redirect = route.query.redirect

        void router.replace(typeof redirect === 'string' ? redirect : { name: 'settings' })
      },
    },
  )
}
</script>

<template>
  <main class="login">
    <form class="login__card" novalidate @submit.prevent="submit">
      <header class="login__header">
        <h1 class="login__title">Отзывы организаций</h1>
        <p class="login__subtitle">Войдите, чтобы настроить карточку и посмотреть отзывы.</p>
      </header>

      <UiAlert v-if="generalError" variant="error">{{ generalError }}</UiAlert>

      <UiInput
        v-model="email"
        label="Email"
        type="email"
        placeholder="demo@example.com"
        autocomplete="username"
        :disabled="isPending"
        :error="emailError"
        required
      />

      <UiInput
        v-model="password"
        label="Пароль"
        type="password"
        placeholder="••••••••"
        autocomplete="current-password"
        :disabled="isPending"
        :error="passwordError"
        required
      />

      <label class="login__remember">
        <input v-model="remember" type="checkbox" :disabled="isPending" />
        <span>Запомнить меня</span>
      </label>

      <UiButton type="submit" block :loading="isPending">Войти</UiButton>
    </form>
  </main>
</template>

<style scoped>
.login {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100%;
  padding: 24px;
}

.login__card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 380px;
  padding: 28px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}

.login__header {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 4px;
}

.login__title {
  font-size: 21px;
}

.login__subtitle {
  font-size: 14px;
  color: var(--text-muted);
}

.login__remember {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--text-muted);
  cursor: pointer;
}
</style>
