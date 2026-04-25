export const useApi = () => {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  async function request<T = any>(
    path: string,
    options: Parameters<typeof $fetch>[1] & { multipart?: boolean } = {},
  ): Promise<T> {
    const { multipart, ...fetchOptions } = options
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(auth.token.value ? { Authorization: `Bearer ${auth.token.value}` } : {}),
      ...(fetchOptions.headers as Record<string, string> ?? {}),
    }
    if (!multipart) headers['Content-Type'] = 'application/json'

    return $fetch<T>(`${config.public.apiUrl}/api${path}`, {
      ...fetchOptions,
      headers,
    })
  }

  return { request }
}
