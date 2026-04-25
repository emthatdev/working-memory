<template>
  <Transition name="slide">
    <div v-if="open" class="panel">
      <div class="panel-header">
        <span class="panel-title">Ask Loci OS</span>
        <button class="close-btn" @click="$emit('close')">✕</button>
      </div>

      <div ref="messagesEl" class="messages">
        <div v-if="messages.length === 0" class="empty">
          Ask anything about your memories…
        </div>
        <div
          v-for="(msg, i) in messages"
          :key="i"
          :class="['msg', msg.role]"
        >
          <div class="bubble">{{ msg.content }}</div>
        </div>
        <div v-if="loading" class="msg assistant">
          <div class="bubble typing">
            <span /><span /><span />
          </div>
        </div>
      </div>

      <form class="input-row" @submit.prevent="send">
        <input
          v-model="input"
          placeholder="Ask about your memories…"
          :disabled="loading"
          @keydown.enter.exact.prevent="send"
        />
        <button type="submit" :disabled="loading || !input.trim()" class="send-btn">↑</button>
      </form>
    </div>
  </Transition>
</template>

<script setup lang="ts">
defineProps<{ open: boolean }>()
defineEmits(['close'])

const { request } = useApi()

interface Message { role: 'user' | 'assistant'; content: string }

const messages = ref<Message[]>([])
const input = ref('')
const loading = ref(false)
const messagesEl = ref<HTMLElement | null>(null)

async function send() {
  const text = input.value.trim()
  if (!text || loading.value) return

  messages.value.push({ role: 'user', content: text })
  input.value = ''
  loading.value = true
  await nextTick(() => scrollBottom())

  const res = await request<{ message: string }>('/chat', {
    method: 'POST',
    body: { message: text },
  })
  messages.value.push({
    role: 'assistant',
    content: res.success && res.data ? res.data.message : 'Sorry, something went wrong.',
  })
  loading.value = false
  await nextTick(() => scrollBottom())
}

function scrollBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}
</script>

<style scoped>
.panel {
  position: fixed;
  top: 0; right: 0;
  width: 360px; height: 100vh;
  background: var(--glass);
  border-left: 1px solid var(--border);
  backdrop-filter: blur(16px);
  display: flex;
  flex-direction: column;
  z-index: 100;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.25rem 1rem;
  border-bottom: 1px solid var(--border);
}
.panel-title { font-weight: 700; font-size: 1rem; }
.close-btn {
  background: none; border: none; color: var(--muted);
  font-size: 1rem; cursor: pointer; padding: 0.25rem;
  transition: color 0.2s;
}
.close-btn:hover { color: var(--text); }

.messages {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.empty { color: var(--muted); font-size: 0.875rem; text-align: center; padding: 2rem 0; }

.msg { display: flex; }
.msg.user { justify-content: flex-end; }
.msg.assistant { justify-content: flex-start; }

.bubble {
  max-width: 85%;
  padding: 0.625rem 0.875rem;
  border-radius: 12px;
  font-size: 0.875rem;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
.msg.user .bubble {
  background: var(--accent);
  color: #fff;
  border-bottom-right-radius: 3px;
}
.msg.assistant .bubble {
  background: rgba(255,255,255,0.06);
  border: 1px solid var(--border);
  color: var(--text);
  border-bottom-left-radius: 3px;
}

.typing {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0.75rem 1rem;
}
.typing span {
  width: 6px; height: 6px;
  background: var(--muted);
  border-radius: 50%;
  animation: bounce 1.2s infinite;
}
.typing span:nth-child(2) { animation-delay: 0.2s; }
.typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-6px); }
}

.input-row {
  display: flex;
  gap: 0.5rem;
  padding: 1rem;
  border-top: 1px solid var(--border);
}
.input-row input {
  flex: 1;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.6rem 0.875rem;
  color: var(--text);
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.2s;
}
.input-row input:focus { border-color: var(--accent); }
.input-row input::placeholder { color: var(--muted); opacity: 0.6; }
.send-btn {
  width: 36px; height: 36px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
  flex-shrink: 0;
  align-self: center;
}
.send-btn:hover:not(:disabled) { background: #6d28d9; }
.send-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.slide-enter-active, .slide-leave-active { transition: transform 0.3s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>
