export interface ApiResponse<T = any> {
  success: boolean
  data?: T | null
  message?: string
  status?: number
  errors?: Record<string, string[]>
}

let csrfFetched = false

async function ensureCsrf(baseUrl: string) {
  const xsrf = useCookie('XSRF-TOKEN')
  if (xsrf.value || csrfFetched) return
  try {
    await $fetch(`${baseUrl}/sanctum/csrf-cookie`, { credentials: 'include' })
    csrfFetched = true
  } catch { /* 419 retry will handle it */ }
}

export const useApi = () => {
  const { $api } = useNuxtApp()
  const config = useRuntimeConfig()

  async function request<T = any>(
    path: string,
    options: Parameters<typeof $api>[1] = {},
    _retries = 0,
  ): Promise<ApiResponse<T>> {
    const method = ((options.method as string) ?? 'GET').toUpperCase()
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      await ensureCsrf(config.public.apiUrl as string)
    }

    try {
      const data = await $api<T>(path, options)
      return { success: true, data }
    } catch (err: any) {
      const status: number = err.status ?? err?.data?.status ?? 500

      if (status === 419 && _retries < 2) {
        csrfFetched = false
        await ensureCsrf(config.public.apiUrl as string)
        return request<T>(path, options, _retries + 1)
      }

      return {
        success: false,
        status,
        message: err?.data?.message ?? 'Something went wrong.',
        errors: err?.data?.errors,
      }
    }
  }

  return { request }
}
