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

  async function loadUser() {
    if (!token.value) return
    const config = useRuntimeConfig()
    try {
      const data = await $fetch<Record<string, any>>(
        `${config.public.apiUrl}/api/user`,
        {
          headers: {
            Authorization: `Bearer ${token.value}`,
            Accept: 'application/json',
          },
          credentials: 'include',
        },
      )
      user.value = data
    } catch {
      logout()
    }
  }

  return { token, user, isAuthenticated, setAuth, logout, loadUser }
})
