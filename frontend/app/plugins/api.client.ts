export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  const api = $fetch.create({
    baseURL: `${config.public.apiUrl}/api/`,
    credentials: 'include',

    onRequest({ options }) {
      const headers = new Headers((options.headers as HeadersInit) || {})
      headers.set('Accept', 'application/json')

      const xsrf = useCookie('XSRF-TOKEN').value
      if (xsrf) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf))

      if (auth.token) headers.set('Authorization', `Bearer ${auth.token}`)

      options.headers = headers
    },

    onResponseError({ response }) {
      if (response.status === 401) {
        auth.logout()
        navigateTo('/auth?mode=login')
      }
    },
  })

  // Restore user data after page refresh without blocking render
  auth.loadUser()

  return { provide: { api } }
})
