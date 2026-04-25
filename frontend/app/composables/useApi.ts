let csrfFetched = false

export const useApi = () => {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  async function ensureCsrf() {
    if (csrfFetched || import.meta.server) return
    try {
      await $fetch(`${config.public.apiUrl}/sanctum/csrf-cookie`, {
        credentials: 'include',
      })
      csrfFetched = true
    } catch { /* will surface as 419 on the actual request */ }
  }

  function getCsrfToken(): string | undefined {
    if (import.meta.server) return undefined
    const match = document.cookie.match(/(?:^|;)\s*XSRF-TOKEN=([^;]*)/)
    return match ? decodeURIComponent(match[1]!) : undefined
  }

  async function request<T = any>(
    path: string,
    options: Parameters<typeof $fetch>[1] & { multipart?: boolean } = {},
  ): Promise<T> {
    const { multipart, ...fetchOptions } = options

    const method = ((fetchOptions.method as string) ?? 'GET').toUpperCase()
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      await ensureCsrf()
    }

    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
      ...(fetchOptions.headers as Record<string, string> ?? {}),
    }
    if (!multipart) headers['Content-Type'] = 'application/json'

    const csrf = getCsrfToken()
    if (csrf) headers['X-XSRF-TOKEN'] = csrf

    return $fetch<T>(`${config.public.apiUrl}/api${path}`, {
      ...fetchOptions,
      credentials: 'include',
      headers,
    })
  }

  return { request }
}
