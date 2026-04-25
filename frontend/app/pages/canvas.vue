<template>
  <div
    class="canvas-page"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointerleave="onPointerUp"
    @wheel.prevent="onWheel"
  >
    <ClientOnly>
      <TresCanvas window-size clear-color="#05030f" :antialias="true">
        <!--
          Camera position is driven entirely by our rAF loop via cameraRef.
          The initial :position prop seeds Three.js on first mount only.
        -->
        <TresPerspectiveCamera ref="cameraRef" :position="[0, 0, 22]" :fov="55" />

        <TresAmbientLight color="#ffffff" :intensity="2" />
        <TresPointLight :position="[0, 0, 18]" color="#a78bfa" :intensity="120" />
        <TresPointLight :position="[0, 20, 0]" color="#6366f1" :intensity="60" />

        <TresGroup
          v-for="card in memoryCards"
          :key="card.id"
          :ref="(el) => setGroupRef(card.id, el)"
          :position="card.position"
          :rotation="card.rotation"
        >
          <!-- Main card face -->
          <TresMesh
            :ref="(el) => setMeshRef(card.id, el)"
            @click="selectMemory(card)"
            @pointerover="hoveredId = card.id"
            @pointerout="() => { if (hoveredId === card.id) hoveredId = null }"
          >
            <TresPlaneGeometry :args="[3.2, 2]" />
            <TresMeshBasicMaterial :map="card.texture" :transparent="true" :opacity="0.88" />
          </TresMesh>
          <!-- Glow halo (opacity driven by rAF, seeded at 0.15) -->
          <TresMesh :ref="(el) => setGlowRef(card.id, el)" :position="[0, 0, -0.012]">
            <TresPlaneGeometry :args="[3.36, 2.12]" />
            <TresMeshBasicMaterial :color="card.color" :transparent="true" :opacity="0.15" />
          </TresMesh>
        </TresGroup>
      </TresCanvas>
    </ClientOnly>

    <!-- Top bar -->
    <header class="topbar">
      <span class="logo">Loci OS</span>
      <button class="btn-ghost" @click="logout">Sign out</button>
    </header>

    <!-- Search bar -->
    <div class="search-wrap">
      <form class="search-bar" @submit.prevent="runSearch">
        <input v-model="searchQuery" placeholder="Search your memories…" :disabled="searching" />
        <button type="submit" :disabled="searching || !searchQuery.trim()">
          {{ searching ? '…' : '↑' }}
        </button>
      </form>
      <div v-if="searchResults.length" class="search-results">
        <div
          v-for="r in searchResults"
          :key="r.id"
          class="result-item"
          @click="focusResult(r)"
        >
          <span class="result-type">{{ r.type }}</span>
          <span class="result-content">{{ truncate(r.content ?? r.file_path ?? '', 80) }}</span>
          <span class="result-sim">{{ Math.round((r.similarity ?? 0) * 100) }}%</span>
        </div>
      </div>
    </div>

    <!-- FABs -->
    <div class="actions">
      <button class="fab" title="Ask Loci OS" @click="chatOpen = true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
      </button>
      <button class="fab fab-primary" title="Add memory" @click="uploadOpen = true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
        </svg>
      </button>
    </div>

    <!-- Selected memory detail -->
    <Transition name="pop">
      <div v-if="selected" class="detail-card" @click.self="selected = null">
        <div class="detail-inner">
          <button class="detail-close" @click="selected = null">✕</button>
          <span class="detail-type">{{ selected.type }}</span>
          <p class="detail-content">{{ selected.content ?? selected.file_path }}</p>
          <span class="detail-date">{{ formatDate(selected.created_at) }}</span>
        </div>
      </div>
    </Transition>

    <ChatPanel :open="chatOpen" @close="chatOpen = false" />
    <UploadModal :open="uploadOpen" @close="uploadOpen = false" @saved="onMemorySaved" />

    <!-- Loading overlay — blocks interaction until memories are fetched -->
    <Transition name="fade-out">
      <div v-if="loading" class="loading-overlay">
        <div class="loading-card">
          <div class="spinner" />
          <p class="loading-label">Loading your memory palace…</p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import {
  CanvasTexture,
  type Mesh,
  type Group,
  type PerspectiveCamera,
  MeshBasicMaterial,
} from 'three'

definePageMeta({ middleware: 'auth' })

const { request } = useApi()
const auth = useAuthStore()
const router = useRouter()

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
interface Memory {
  id: number
  type: string
  content: string | null
  file_path: string | null
  created_at: string
  similarity?: number
}
interface MemoryCard {
  id: number
  position: [number, number, number]
  rotation: [number, number, number]
  color: string
  texture: CanvasTexture
  memory: Memory
}

// ---------------------------------------------------------------------------
// Three.js object refs — updated directly in rAF, bypassing Vue reactivity
// ---------------------------------------------------------------------------
const cameraRef = ref<PerspectiveCamera | null>(null)
const meshMap  = new Map<number, Mesh>()
const glowMap  = new Map<number, Mesh>()
const groupMap = new Map<number, Group>()

function setMeshRef (id: number, el: unknown) { el ? meshMap.set(id,  el as Mesh)  : meshMap.delete(id) }
function setGlowRef (id: number, el: unknown) { el ? glowMap.set(id,  el as Mesh)  : glowMap.delete(id) }
function setGroupRef(id: number, el: unknown) { el ? groupMap.set(id, el as Group) : groupMap.delete(id) }

// ---------------------------------------------------------------------------
// Camera state (plain objects — no Vue reactivity overhead per frame)
// ---------------------------------------------------------------------------
const cam = { x: 0, y: 0, z: 22 }      // actual camera world position
const vel = { x: 0, y: 0 }             // current velocity
const targetVel = { x: 0, y: 0 }       // desired velocity (decays each frame)

// Article constants:
const V_LERP  = 0.22   // how fast velocity tracks target
const V_DECAY = 0.88   // per-frame multiplier that drains targetVel → inertia ease-out

// Distance fade thresholds (world units from camera)
const FADE_START = 18
const FADE_END   = 36

// ---------------------------------------------------------------------------
// Vue reactive state
// ---------------------------------------------------------------------------
const memoryCards   = ref<MemoryCard[]>([])
const loading       = ref(true)
const hoveredId     = ref<number | null>(null)
const selected      = ref<Memory | null>(null)
const chatOpen      = ref(false)
const uploadOpen    = ref(false)
const searchQuery   = ref('')
const searchResults = ref<Memory[]>([])
const searching     = ref(false)

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function lerp(a: number, b: number, t: number) { return a + (b - a) * t }

/**
 * Quadratic distance fade — article pattern:
 *   gridFade: 1 inside radius, smooth falloff to 0 outside
 *   final: gridFade² for a sharper inner region feel
 */
function distanceFade(dist: number): number {
  if (dist <= FADE_START) return 1
  if (dist >= FADE_END)   return 0
  const t = (dist - FADE_START) / (FADE_END - FADE_START)
  return (1 - t) * (1 - t) // quadratic ease-out
}

// ---------------------------------------------------------------------------
// Deterministic card layout
// ---------------------------------------------------------------------------
function rand(seed: number, salt: number): number {
  const x = Math.sin(seed * 127.1 + salt * 311.7) * 43758.5453
  return x - Math.floor(x)
}
function cardPosition(id: number): [number, number, number] {
  return [
    (rand(id, 0) - 0.5) * 44,
    (rand(id, 1) - 0.5) * 26,
    (rand(id, 2) - 0.5) * 34,
  ]
}
function cardRotation(id: number): [number, number, number] {
  return [
    (rand(id, 3) - 0.5) * 0.3,
    (rand(id, 4) - 0.5) * 0.5,
    (rand(id, 5) - 0.5) * 0.15,
  ]
}

// ---------------------------------------------------------------------------
// Canvas texture — renders text onto a 512×320 offscreen canvas
// ---------------------------------------------------------------------------
const TYPE_COLORS: Record<string, string> = {
  text:  '#7c3aed',
  image: '#0ea5e9',
  pdf:   '#10b981',
}

function wrapText(
  ctx: CanvasRenderingContext2D,
  text: string,
  x: number,
  y: number,
  maxWidth: number,
  lineHeight: number,
  maxLines: number,
): void {
  const words = text.split(' ')
  let line = ''
  let drawn = 0
  for (let i = 0; i < words.length; i++) {
    const test = line ? `${line} ${words[i]}` : words[i]
    if (ctx.measureText(test).width > maxWidth && line) {
      ctx.fillText(line, x, y)
      y += lineHeight
      drawn++
      if (drawn >= maxLines - 1) {
        const rest = words.slice(i).join(' ')
        ctx.fillText(rest.length > 55 ? rest.slice(0, 55) + '…' : rest, x, y)
        return
      }
      line = words[i]
    } else {
      line = test
    }
  }
  if (line) ctx.fillText(line, x, y)
}

function makeTexture(memory: Memory): CanvasTexture {
  const color = TYPE_COLORS[memory.type] ?? '#7c3aed'
  const W = 512, H = 320
  const cvs = document.createElement('canvas')
  cvs.width = W; cvs.height = H
  const ctx = cvs.getContext('2d')!

  ctx.fillStyle = '#130e25'
  ctx.beginPath(); ctx.roundRect(0, 0, W, H, 14); ctx.fill()

  ctx.fillStyle = color
  ctx.fillRect(0, 0, W, 6)

  ctx.strokeStyle = color + '55'
  ctx.lineWidth = 1.5
  ctx.beginPath(); ctx.roundRect(0.75, 0.75, W - 1.5, H - 1.5, 14); ctx.stroke()

  ctx.fillStyle = color + '28'
  ctx.beginPath(); ctx.roundRect(18, 18, 72, 24, 5); ctx.fill()
  ctx.fillStyle = color
  ctx.font = 'bold 12px system-ui,sans-serif'
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle'
  ctx.fillText(memory.type.toUpperCase(), 54, 30)

  ctx.fillStyle = '#d4c8f0'
  ctx.font = '15px system-ui,sans-serif'
  ctx.textAlign = 'left'; ctx.textBaseline = 'top'
  wrapText(ctx, memory.content ?? memory.file_path ?? '(no content)', 18, 60, W - 36, 26, 8)

  ctx.fillStyle = color + 'aa'
  ctx.font = '11px system-ui,sans-serif'
  ctx.textAlign = 'right'; ctx.textBaseline = 'bottom'
  ctx.fillText(new Date(memory.created_at).toLocaleDateString(undefined, { dateStyle: 'short' }), W - 18, H - 14)

  return new CanvasTexture(cvs)
}

function buildCard(memory: Memory): MemoryCard {
  return {
    id: memory.id,
    position: cardPosition(memory.id),
    rotation: cardRotation(memory.id),
    color: TYPE_COLORS[memory.type] ?? '#7c3aed',
    texture: makeTexture(memory),
    memory,
  }
}

// ---------------------------------------------------------------------------
// rAF render loop — velocity integration + per-card fade/scale
// ---------------------------------------------------------------------------
let raf = 0

function tick() {
  // --- Inertia (article pattern: lerp velocity toward target, decay target) ---
  vel.x = lerp(vel.x, targetVel.x, V_LERP)
  vel.y = lerp(vel.y, targetVel.y, V_LERP)
  targetVel.x *= V_DECAY
  targetVel.y *= V_DECAY

  if (Math.abs(vel.x) > 0.0001 || Math.abs(vel.y) > 0.0001) {
    cam.x += vel.x
    cam.y += vel.y
  }

  // Push to Three.js camera directly (no Vue reactive overhead per-frame)
  const camera = cameraRef.value
  if (camera?.position) {
    camera.position.set(cam.x, cam.y, cam.z)
  }

  // --- Per-card: distance fade + hover scale (direct Three.js mutation) ---
  const hov = hoveredId.value
  for (const card of memoryCards.value) {
    const mesh  = meshMap.get(card.id)
    const glow  = glowMap.get(card.id)
    const group = groupMap.get(card.id)
    if (!mesh || !glow || !group) continue

    // Distance from camera to card centre
    const dx = card.position[0] - cam.x
    const dy = card.position[1] - cam.y
    const dz = card.position[2] - cam.z
    const dist = Math.sqrt(dx * dx + dy * dy + dz * dz)

    const isHovered = hov === card.id
    const fade = distanceFade(dist)
    const targetOpacity = fade * (isHovered ? 1 : 0.88)

    // Lerp opacity — article uses 0.18, we use 0.12 for a slightly slower drift
    const mat = mesh.material as MeshBasicMaterial
    mat.opacity = lerp(mat.opacity, targetOpacity, 0.12)
    mesh.visible = mat.opacity > 0.005

    // Glow tracks card opacity
    const glowMat = glow.material as MeshBasicMaterial
    glowMat.opacity = lerp(glowMat.opacity, fade * (isHovered ? 0.45 : 0.15), 0.15)
    glow.visible = glowMat.opacity > 0.005

    // Scale hover spring
    const targetScale = isHovered ? 1.06 : 1
    const s = lerp(group.scale.x, targetScale, 0.14)
    group.scale.setScalar(s)
  }

  raf = requestAnimationFrame(tick)
}

// ---------------------------------------------------------------------------
// Pointer + wheel navigation
// ---------------------------------------------------------------------------
// Pointer capture is deferred until the drag crosses a pixel threshold.
// Below the threshold the canvas element receives the normal click event,
// so TresJS's raycaster fires and @click on TresMesh works correctly.
const DRAG_THRESHOLD = 5 // px
let isDragging  = false
let captured    = false
let lastPtr     = { x: 0, y: 0 }
let startPtr    = { x: 0, y: 0 }

function onPointerDown(e: PointerEvent) {
  if ((e.target as Element).closest('header, button, input, form, .detail-card, .panel')) return
  isDragging = false
  captured   = false
  lastPtr    = { x: e.clientX, y: e.clientY }
  startPtr   = { x: e.clientX, y: e.clientY }
}

function onPointerMove(e: PointerEvent) {
  if (startPtr.x === 0 && startPtr.y === 0) return
  const dx = e.clientX - lastPtr.x
  const dy = e.clientY - lastPtr.y

  // Engage drag only after the pointer has moved enough to be intentional
  if (!isDragging) {
    const totalDx = e.clientX - startPtr.x
    const totalDy = e.clientY - startPtr.y
    if (Math.sqrt(totalDx * totalDx + totalDy * totalDy) < DRAG_THRESHOLD) return
    isDragging = true
    // Capture now so fast drags don't escape the element
    if (!captured) {
      try { (e.currentTarget as Element).setPointerCapture(e.pointerId); captured = true } catch { /* noop */ }
    }
  }

  const scale = cam.z * 0.0014
  targetVel.x -= dx * scale
  targetVel.y += dy * scale
  lastPtr = { x: e.clientX, y: e.clientY }
}

function onPointerUp(e: PointerEvent) {
  if (captured) {
    try { (e.currentTarget as Element).releasePointerCapture(e.pointerId) } catch { /* noop */ }
  }
  isDragging = false
  captured   = false
  startPtr   = { x: 0, y: 0 }
  // intentionally do NOT zero velocity — let inertia carry through
}

function onWheel(e: WheelEvent) {
  if (e.ctrlKey) {
    // Pinch zoom / Ctrl+scroll → move along Z
    cam.z = Math.max(5, Math.min(80, cam.z + e.deltaY * 0.04))
  } else {
    // Trackpad two-finger pan or mouse wheel scroll → pan XY
    const scale = cam.z * 0.001
    targetVel.x += e.deltaX * scale
    targetVel.y -= e.deltaY * scale
  }
}

// ---------------------------------------------------------------------------
// Data loading
// ---------------------------------------------------------------------------
onMounted(async () => {
  const res = await request<Memory[]>('/memories')
  if (res.success) {
    const cards = (res.data ?? []).map(buildCard)
    memoryCards.value = cards

    if (cards.length > 0) {
      cam.x = cards.reduce((s, c) => s + c.position[0], 0) / cards.length
      cam.y = cards.reduce((s, c) => s + c.position[1], 0) / cards.length
    }
  }
  loading.value = false
  raf = requestAnimationFrame(tick)
})

onUnmounted(() => {
  cancelAnimationFrame(raf)
  memoryCards.value.forEach(c => c.texture.dispose())
})

// ---------------------------------------------------------------------------
// UI handlers
// ---------------------------------------------------------------------------
function selectMemory(card: MemoryCard) { selected.value = card.memory }

async function runSearch() {
  const q = searchQuery.value.trim()
  if (!q) return
  searching.value = true
  const res = await request<Memory[]>('/memories/search', { method: 'POST', body: { query: q } })
  searching.value = false
  searchResults.value = res.success ? (res.data ?? []) : []
}

function focusResult(r: Memory) {
  selected.value = r
  searchResults.value = []
  searchQuery.value = ''
}

function onMemorySaved(memory: Memory) {
  memoryCards.value.unshift(buildCard(memory))
}

function truncate(s: string, n: number) { return s.length > n ? s.slice(0, n) + '…' : s }
function formatDate(iso: string) { return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' }) }

async function logout() {
  await request('/logout', { method: 'POST' })
  auth.logout()
  router.push('/')
}
</script>

<style scoped>
.canvas-page {
  position: fixed; inset: 0;
  background: var(--bg);
  overflow: hidden;
  /* Prevent browser text-selection drag and default touch panning */
  user-select: none;
  touch-action: none;
  cursor: grab;
}
.canvas-page:active { cursor: grabbing; }

/* Top bar */
.topbar {
  position: fixed; top: 0; left: 0; right: 0;
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.5rem;
  z-index: 10;
  background: linear-gradient(to bottom, rgba(5,3,15,0.85) 0%, transparent 100%);
  cursor: default;
}
.logo {
  font-size: 1.125rem; font-weight: 700; letter-spacing: -0.02em;
  background: linear-gradient(135deg, #a78bfa, #6366f1);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.btn-ghost {
  background: none; border: 1px solid var(--border); color: var(--muted);
  border-radius: 8px; padding: 0.4rem 1rem; font-size: 0.8125rem;
  cursor: pointer; transition: all 0.2s;
}
.btn-ghost:hover { color: var(--text); border-color: var(--text); }

/* Search */
.search-wrap {
  position: fixed; bottom: 2rem; left: 50%;
  transform: translateX(-50%);
  width: min(480px, calc(100vw - 8rem));
  z-index: 10;
  cursor: default;
}
.search-bar {
  display: flex; gap: 0.5rem;
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.5rem 0.5rem 0.5rem 1.25rem;
  backdrop-filter: blur(16px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}
.search-bar input {
  flex: 1; background: none; border: none; color: var(--text);
  font-size: 0.9375rem; outline: none; cursor: text;
}
.search-bar input::placeholder { color: var(--muted); opacity: 0.6; }
.search-bar button {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--accent); color: #fff; border: none;
  font-size: 1.1rem; cursor: pointer; flex-shrink: 0;
  transition: background 0.2s;
}
.search-bar button:hover:not(:disabled) { background: #6d28d9; }
.search-bar button:disabled { opacity: 0.4; cursor: not-allowed; }

.search-results {
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-top: 0.5rem;
  overflow: hidden;
  backdrop-filter: blur(16px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}
.result-item {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.75rem 1rem;
  cursor: pointer; transition: background 0.15s;
  border-bottom: 1px solid var(--border);
}
.result-item:last-child { border-bottom: none; }
.result-item:hover { background: rgba(255,255,255,0.05); }
.result-type {
  font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--accent); flex-shrink: 0;
}
.result-content {
  flex: 1; font-size: 0.875rem; color: var(--text);
  overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
}
.result-sim { font-size: 0.75rem; color: var(--muted); flex-shrink: 0; }

/* FABs */
.actions {
  position: fixed; bottom: 2rem; right: 1.5rem;
  display: flex; flex-direction: column; gap: 0.75rem;
  z-index: 10; cursor: default;
}
.fab {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--glass); border: 1px solid var(--border);
  color: var(--text); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(12px);
  transition: all 0.2s;
  box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}
.fab:hover { border-color: var(--accent); color: var(--accent); }
.fab-primary { background: var(--accent); border-color: transparent; color: #fff; }
.fab-primary:hover { background: #6d28d9; color: #fff; }

/* Detail */
.detail-card {
  position: fixed; inset: 0;
  display: flex; align-items: center; justify-content: center;
  z-index: 50; padding: 1rem; cursor: default;
}
.detail-inner {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 1.25rem;
  padding: 1.75rem;
  width: 100%; max-width: 440px;
  position: relative;
  box-shadow: 0 24px 64px rgba(0,0,0,0.6);
}
.detail-close {
  position: absolute; top: 1rem; right: 1rem;
  background: none; border: none; color: var(--muted);
  font-size: 1rem; cursor: pointer; transition: color 0.2s;
}
.detail-close:hover { color: var(--text); }
.detail-type {
  display: inline-block; font-size: 0.6875rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--accent); margin-bottom: 0.75rem;
}
.detail-content {
  font-size: 0.9375rem; line-height: 1.6; color: var(--text);
  white-space: pre-wrap; word-break: break-word; margin-bottom: 1rem;
}
.detail-date { font-size: 0.8125rem; color: var(--muted); }

.pop-enter-active, .pop-leave-active { transition: opacity 0.2s, transform 0.2s; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: scale(0.96); }

/* Loading overlay */
.loading-overlay {
  position: fixed; inset: 0;
  z-index: 100;
  display: flex; align-items: center; justify-content: center;
  background: var(--bg);
  /* Prevent any interaction with the canvas beneath */
  pointer-events: all;
}
.loading-card {
  display: flex; flex-direction: column; align-items: center; gap: 1.25rem;
}
.spinner {
  width: 36px; height: 36px;
  border: 2.5px solid rgba(124, 58, 237, 0.2);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.75s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-label {
  font-size: 0.9375rem;
  color: var(--muted);
  letter-spacing: 0.01em;
}

.fade-out-leave-active { transition: opacity 0.4s ease; }
.fade-out-leave-to { opacity: 0; }
</style>
