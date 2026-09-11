<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'

import UiButton from '@/components/ui/UiButton.vue'
import { useLogoutMutation } from '@/queries/auth'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const { user } = storeToRefs(useAuthStore())

const { mutate, isPending } = useLogoutMutation()

function logOut(): void {
  mutate(undefined, {
    onSettled: () => {
      void router.replace({ name: 'login' })
    },
  })
}
</script>

<template>
  <header class="header">
    <div class="header__inner">
      <span class="header__brand">Отзывы организаций</span>

      <div class="header__user">
        <span v-if="user" class="header__email">{{ user.email }}</span>
        <UiButton variant="secondary" :loading="isPending" @click="logOut">Выйти</UiButton>
      </div>
    </div>
  </header>
</template>

<style scoped>
.header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}

.header__inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  max-width: var(--page-width);
  margin: 0 auto;
  padding: 14px 24px;
}

.header__brand {
  font-weight: 650;
  letter-spacing: -0.01em;
}

.header__user {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header__email {
  font-size: 14px;
  color: var(--text-muted);
}
</style>
