export const useAuthStore = defineStore('auth', () => {
  const token = useCookie<string | null>('loci_token', { default: () => null })
  const user = ref<Record<string, any> | null>(null)
  const isAuthenticated = computed(() => !!token.value)

  function setAuth(newToken: string, newUser: Record<string, any>) {
    token.value = newToken
    user.value = newUser
  }

  function logout() {
    token.value = null
    user.value = null
  }

  return { token, user, isAuthenticated, setAuth, logout }
})
