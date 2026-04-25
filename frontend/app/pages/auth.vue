<template>
  <div class="auth-page">
    <div class="glow" />

    <NuxtLink to="/" class="back">← Loci OS</NuxtLink>

    <div class="card">
      <div class="tabs">
        <button :class="['tab', { active: mode === 'login' }]" @click="mode = 'login'">Log in</button>
        <button :class="['tab', { active: mode === 'register' }]" @click="mode = 'register'">Register</button>
      </div>

      <form @submit.prevent="submit">
        <div v-if="mode === 'register'" class="field">
          <label>Name</label>
          <input v-model="form.name" type="text" placeholder="Your name" required />
        </div>
        <div class="field">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="you@example.com" required />
        </div>
        <div class="field">
          <label>Password</label>
          <input v-model="form.password" type="password" placeholder="••••••••" required />
        </div>
        <div v-if="mode === 'register'" class="field">
          <label>Confirm password</label>
          <input v-model="form.password_confirmation" type="password" placeholder="••••••••" required />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          <span v-if="loading">{{ mode === 'login' ? 'Logging in…' : 'Creating account…' }}</span>
          <span v-else>{{ mode === 'login' ? 'Log in' : 'Create account' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const router = useRouter()
const { request } = useApi()
const auth = useAuthStore()

if (auth.isAuthenticated) {
  await navigateTo('/canvas')
}

const mode = ref<'login' | 'register'>((route.query.mode as 'login' | 'register') ?? 'login')
const error = ref('')
const loading = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const path = mode.value === 'login' ? '/login' : '/register'
    const body: Record<string, string> = {
      email: form.email,
      password: form.password,
    }
    if (mode.value === 'register') {
      body.name = form.name
      body.password_confirmation = form.password_confirmation
    }

    const res = await request<{ token: string; user: Record<string, any> }>(path, {
      method: 'POST',
      body,
    })

    auth.setAuth(res.token, res.user)
    await navigateTo('/canvas')
  } catch (e: any) {
    error.value = e?.data?.message ?? 'Something went wrong.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  padding: 2rem;
}

.glow {
  position: absolute;
  width: 700px; height: 700px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(124,58,237,0.2) 0%, transparent 70%);
  filter: blur(80px);
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
}

.back {
  position: fixed;
  top: 1.5rem; left: 2rem;
  color: var(--muted);
  text-decoration: none;
  font-size: 0.875rem;
  transition: color 0.2s;
  z-index: 1;
}
.back:hover { color: var(--text); }

.card {
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 1.25rem;
  padding: 2rem;
  width: 100%;
  max-width: 420px;
  backdrop-filter: blur(12px);
  position: relative;
  z-index: 1;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.75rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0;
}
.tab {
  flex: 1;
  background: none;
  border: none;
  color: var(--muted);
  font-size: 0.9375rem;
  font-weight: 600;
  padding: 0.5rem;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.2s;
}
.tab.active { color: var(--text); border-bottom-color: var(--accent); }

.field { margin-bottom: 1.25rem; }
label { display: block; font-size: 0.8125rem; color: var(--muted); margin-bottom: 0.4rem; }
input {
  width: 100%;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.65rem 0.875rem;
  color: var(--text);
  font-size: 0.9375rem;
  outline: none;
  transition: border-color 0.2s;
}
input:focus { border-color: var(--accent); }
input::placeholder { color: var(--muted); opacity: 0.6; }

.error {
  color: var(--danger);
  font-size: 0.875rem;
  margin-bottom: 1rem;
  padding: 0.5rem 0.75rem;
  background: rgba(244,63,94,0.1);
  border-radius: 6px;
  border: 1px solid rgba(244,63,94,0.2);
}

.submit {
  width: 100%;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.75rem;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  margin-top: 0.5rem;
}
.submit:hover:not(:disabled) { background: #6d28d9; }
.submit:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
