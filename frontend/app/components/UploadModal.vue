<template>
  <Transition name="fade">
    <div v-if="open" class="overlay" @click.self="$emit('close')">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Add memory</span>
          <button class="close-btn" @click="$emit('close')">✕</button>
        </div>

        <!-- Type tabs -->
        <div class="tabs">
          <button
            v-for="t in types"
            :key="t.value"
            type="button"
            :class="['tab', { active: type === t.value }]"
            @click="selectType(t.value)"
          >
            {{ t.label }}
          </button>
        </div>

        <form @submit.prevent="submit">
          <!-- Text -->
          <div v-if="type === 'text'" class="field">
            <textarea
              v-model="content"
              placeholder="What do you want to remember?"
              rows="6"
            />
          </div>

          <!-- Image / PDF -->
          <div v-if="type === 'image'" class="field note-field">
            <textarea
              v-model="note"
              placeholder="Add a note about this image… (optional)"
              rows="2"
            />
          </div>
          <div v-if="type === 'image' || type === 'pdf'" class="field">
            <label
              class="drop-zone"
              :class="{ 'drag-over': dragging, 'has-file': !!file }"
              @dragover.prevent="dragging = true"
              @dragleave="dragging = false"
              @drop.prevent="onDrop"
            >
              <input
                ref="fileInput"
                type="file"
                :accept="type === 'image' ? 'image/jpeg,image/png,image/gif,image/webp' : 'application/pdf'"
                class="file-input"
                @change="onFileChange"
              />
              <template v-if="file">
                <img v-if="type === 'image'" :src="preview" class="preview-img" />
                <div v-else class="file-info">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                  <span>{{ file.name }}</span>
                  <span class="file-size">{{ formatSize(file.size) }}</span>
                </div>
                <button type="button" class="clear-file" @click.prevent="clearFile">✕</button>
              </template>
              <template v-else>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="drop-icon">
                  <path v-if="type === 'image'" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline v-if="type === 'image'" points="17,8 12,3 7,8"/><line v-if="type === 'image'" x1="12" y1="3" x2="12" y2="15"/>
                  <path v-if="type === 'pdf'" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline v-if="type === 'pdf'" points="14,2 14,8 20,8"/>
                </svg>
                <span class="drop-label">Drop {{ type === 'image' ? 'an image' : 'a PDF' }} here or <u>browse</u></span>
                <span class="drop-hint">{{ type === 'image' ? 'JPG, PNG, GIF, WEBP · max 20 MB' : 'PDF · max 20 MB' }}</span>
              </template>
            </label>
          </div>

          <p v-if="error" class="error">{{ error }}</p>

          <div class="footer">
            <button type="button" class="btn-ghost" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="loading || !canSubmit">
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

const types = [
  { value: 'text',  label: 'Text'  },
  { value: 'image', label: 'Image' },
  { value: 'pdf',   label: 'PDF'   },
]

const type    = ref<'text' | 'image' | 'pdf'>('text')
const content = ref('')
const note    = ref('')
const file    = ref<File | null>(null)
const preview = ref('')
const dragging = ref(false)
const loading  = ref(false)
const error    = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

const canSubmit = computed(() => {
  if (type.value === 'text') return content.value.trim().length > 0
  return !!file.value
})

watch(() => props.open, (v) => {
  if (v) reset()
})

function selectType(t: 'text' | 'image' | 'pdf') {
  type.value = t
  clearFile()
  error.value = ''
}

function reset() {
  content.value = ''
  note.value = ''
  error.value = ''
  type.value = 'text'
  clearFile()
}

function clearFile() {
  file.value = null
  preview.value = ''
  dragging.value = false
  if (fileInput.value) fileInput.value.value = ''
}

function onFileChange(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) setFile(f)
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const f = e.dataTransfer?.files?.[0]
  if (f) setFile(f)
}

function setFile(f: File) {
  file.value = f
  if (type.value === 'image') {
    preview.value = URL.createObjectURL(f)
  }
}

function formatSize(bytes: number) {
  return bytes < 1024 * 1024
    ? `${(bytes / 1024).toFixed(0)} KB`
    : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

async function submit() {
  error.value = ''
  loading.value = true

  let res
  if (type.value === 'text') {
    res = await request('/memories', {
      method: 'POST',
      body: { type: 'text', content: content.value },
    })
  } else {
    const fd = new FormData()
    fd.append('type', type.value)
    fd.append('file', file.value!)
    if (note.value.trim()) fd.append('note', note.value.trim())
    res = await request('/memories', { method: 'POST', body: fd })
  }

  loading.value = false
  if (res.success && res.data) {
    if (preview.value) URL.revokeObjectURL(preview.value)
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
  margin-bottom: 1.25rem;
}
.modal-title { font-weight: 700; font-size: 1rem; }
.close-btn {
  background: none; border: none; color: var(--muted);
  font-size: 1rem; cursor: pointer; padding: 0.25rem;
  transition: color 0.2s;
}
.close-btn:hover { color: var(--text); }

/* Tabs */
.tabs {
  display: flex;
  gap: 0.375rem;
  margin-bottom: 1.25rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.25rem;
}
.tab {
  flex: 1;
  background: none; border: none;
  color: var(--muted);
  font-size: 0.8125rem; font-weight: 500;
  padding: 0.4rem 0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}
.tab.active {
  background: var(--accent);
  color: #fff;
}
.tab:not(.active):hover { color: var(--text); }

.field { margin-bottom: 1.25rem; }
.note-field { margin-bottom: 0.75rem; }

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

/* Drop zone */
.drop-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 160px;
  border: 1.5px dashed var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
  position: relative;
  overflow: hidden;
  padding: 1.5rem;
}
.drop-zone:hover, .drop-zone.drag-over {
  border-color: var(--accent);
  background: rgba(124,58,237,0.06);
}
.drop-zone.has-file { border-style: solid; padding: 0; }

.file-input {
  position: absolute; inset: 0;
  opacity: 0; cursor: pointer;
  width: 100%; height: 100%;
}

.drop-icon { color: var(--muted); }
.drop-label { font-size: 0.9rem; color: var(--text); }
.drop-label u { color: var(--accent); text-decoration-color: var(--accent); }
.drop-hint { font-size: 0.75rem; color: var(--muted); }

.preview-img {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
  border-radius: 9px;
}

.file-info {
  display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
  color: var(--text);
}
.file-info svg { color: var(--accent); }
.file-info span { font-size: 0.875rem; }
.file-size { color: var(--muted); font-size: 0.75rem; }

.clear-file {
  position: absolute; top: 0.5rem; right: 0.5rem;
  background: rgba(5,3,15,0.7);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 50%;
  width: 24px; height: 24px;
  font-size: 0.7rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.15s;
  z-index: 1;
}
.clear-file:hover { background: rgba(244,63,94,0.2); border-color: var(--danger); color: var(--danger); }

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
