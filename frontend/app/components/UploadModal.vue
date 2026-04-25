<template>
  <Transition name="fade">
    <div v-if="open" class="overlay" @click.self="$emit('close')">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Add memory</span>
          <button class="close-btn" @click="$emit('close')">✕</button>
        </div>

        <form @submit.prevent="submit">
          <div class="field">
            <label>Content</label>
            <textarea
              v-model="content"
              placeholder="What do you want to remember?"
              rows="5"
              required
            />
          </div>

          <p v-if="error" class="error">{{ error }}</p>

          <div class="footer">
            <button type="button" class="btn-ghost" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="loading || !content.trim()">
              {{ loading ? 'Saving…' : 'Save memory' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
const props = defineProps<{ open: boolean }>()
const emit = defineEmits(['close', 'saved'])

const { request } = useApi()

const content = ref('')
const loading = ref(false)
const error = ref('')

watch(() => props.open, (v) => { if (v) { content.value = ''; error.value = '' } })

async function submit() {
  error.value = ''
  loading.value = true
  const res = await request('/memories', {
    method: 'POST',
    body: { type: 'text', content: content.value },
  })
  loading.value = false
  if (res.success && res.data) {
    emit('saved', res.data)
    emit('close')
  } else {
    error.value = res.message ?? 'Failed to save memory.'
  }
}
</script>

<style scoped>
.overlay {
  position: fixed; inset: 0;
  background: rgba(5,3,15,0.8);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
  padding: 1rem;
}

.modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 1.25rem;
  width: 100%;
  max-width: 480px;
  padding: 1.75rem;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}
.modal-title { font-weight: 700; font-size: 1rem; }
.close-btn {
  background: none; border: none; color: var(--muted);
  font-size: 1rem; cursor: pointer; padding: 0.25rem;
  transition: color 0.2s;
}
.close-btn:hover { color: var(--text); }

.field { margin-bottom: 1.25rem; }
label { display: block; font-size: 0.8125rem; color: var(--muted); margin-bottom: 0.4rem; }
textarea {
  width: 100%;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.75rem;
  color: var(--text);
  font-size: 0.9375rem;
  outline: none;
  resize: vertical;
  transition: border-color 0.2s;
  line-height: 1.5;
}
textarea:focus { border-color: var(--accent); }
textarea::placeholder { color: var(--muted); opacity: 0.6; }

.error {
  color: var(--danger);
  font-size: 0.875rem;
  margin-bottom: 1rem;
  padding: 0.5rem 0.75rem;
  background: rgba(244,63,94,0.1);
  border-radius: 6px;
  border: 1px solid rgba(244,63,94,0.2);
}

.footer { display: flex; justify-content: flex-end; gap: 0.75rem; }
.btn-ghost {
  background: none; border: 1px solid var(--border); color: var(--muted);
  border-radius: 8px; padding: 0.6rem 1.25rem; font-size: 0.875rem;
  cursor: pointer; transition: all 0.2s;
}
.btn-ghost:hover { color: var(--text); border-color: var(--text); }
.btn-primary {
  background: var(--accent); color: #fff; border: none;
  border-radius: 8px; padding: 0.6rem 1.5rem; font-size: 0.875rem;
  font-weight: 600; cursor: pointer; transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: #6d28d9; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
