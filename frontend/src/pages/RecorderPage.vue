<script setup>
// Recording studio: a native speaker replaces the TTS audio one take at a
// time. Built for speed - the whole loop runs on three keys: space records,
// enter keeps, r redoes. Instant playback after every take is the QC step.
// Kept takes go through admin review before they replace the app audio.
// The "My takes" tab shows everything submitted - waiting or live - and any
// of it can be re-recorded (the new take goes back through review).
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, Circle, CircleCheck, Clock, Mic, PartyPopper, Play, RotateCcw, Square, Volume2 } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const auth = useAuthStore()
const { playSentence, playWord, playClip } = useFinnishAudio()

const allowed = computed(() => !!(auth.user?.is_recorder || auth.user?.is_admin))
const loading = ref(true)
const error = ref('')
const micError = ref('')

const mode = ref('sentences') // sentences | words | submitted
const queue = ref({ sentences: [], words: [], sentence_total: 0, sentence_done: 0, word_total: 0, word_done: 0 })

// Re-record flow: a take picked from "My takes" overrides the queue head.
const override = ref(null) // { kind: 'sentence'|'word', item }

const items = computed(() => {
  if (mode.value === 'sentences') return queue.value.sentences
  if (mode.value === 'words') return queue.value.words
  return []
})
const current = computed(() => override.value?.item ?? items.value[0] ?? null)
const kind = computed(() => override.value?.kind ?? (mode.value === 'words' ? 'word' : 'sentence'))

const done = computed(() => (mode.value === 'sentences' ? queue.value.sentence_done : queue.value.word_done))
const total = computed(() => (mode.value === 'sentences' ? queue.value.sentence_total : queue.value.word_total))
const pending = computed(() => (mode.value === 'sentences' ? queue.value.sentence_pending ?? 0 : queue.value.word_pending ?? 0))
const pct = computed(() => (total.value ? Math.round((done.value / total.value) * 100) : 0))

async function loadQueue() {
  try {
    const { data } = await api.get('/record/queue')
    queue.value = data
  } catch (e) {
    error.value = e.response?.status === 403 ? 'This account has no recording rights.' : 'Could not load the queue.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!auth.user) await auth.fetchUser()
  await loadQueue()
  window.addEventListener('keydown', onKey)
})

// ---- "My takes": everything submitted, pending or live ----
const sub = ref(null)
const subLoading = ref(false)
const subQ = ref('')

async function loadSubmitted() {
  subLoading.value = true
  try {
    const { data } = await api.get('/record/submitted')
    sub.value = data
  } catch {
    error.value = 'Could not load your takes.'
  } finally {
    subLoading.value = false
  }
}

const SHOW_MAX = 80

const subLists = computed(() => {
  if (!sub.value) return null
  const q = subQ.value.trim().toLowerCase()
  const hit = (text) => !q || text.toLowerCase().includes(q)
  const pendingRows = [
    ...sub.value.sentences.filter((s) => hit(s.finnish_text)).map((s) => ({
      kind: 'sentence', keyId: `ps-${s.id}`, label: s.finnish_text, note: s.english_text, url: s.pending_url, item: s
    })),
    ...sub.value.words.filter((w) => hit(w.word)).map((w) => ({
      kind: 'word', keyId: `pw-${w.word}`, label: w.word, note: 'word', url: w.pending_url, item: w
    }))
  ]
  const liveRows = [
    ...sub.value.live_sentences.filter((s) => hit(s.finnish_text)).map((s) => ({
      kind: 'sentence', keyId: `ls-${s.id}`, label: s.finnish_text, note: s.english_text, url: s.audio_url, item: s
    })),
    ...sub.value.live_words.filter((w) => hit(w.word)).map((w) => ({
      kind: 'word', keyId: `lw-${w.word}`, label: w.word, note: 'word', url: w.audio_url, item: w
    }))
  ]
  return { pending: pendingRows, live: liveRows }
})

function redo(row) {
  override.value = {
    kind: row.kind,
    item: row.kind === 'sentence'
      ? { id: row.item.id, finnish_text: row.item.finnish_text, english_text: row.item.english_text, audio_url: row.url }
      : { word: row.item.word, audio_url: row.url }
  }
  discardTake()
  state.value = 'idle'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelOverride() {
  if (state.value === 'recording') recorder?.stop()
  discardTake()
  state.value = 'idle'
  override.value = null
}

// ---- the recorder itself ----
const state = ref('idle') // idle | recording | review | saving
const takeUrl = ref(null)
let takeBlob = null
let stream = null
let recorder = null
let chunks = []

// First supported container wins; Chrome/Firefox record webm/opus, Safari mp4.
const MIME = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', ''].find(
  (t) => !t || (window.MediaRecorder && MediaRecorder.isTypeSupported(t))
)

async function ensureStream() {
  if (stream) return stream
  stream = await navigator.mediaDevices.getUserMedia({ audio: true })
  return stream
}

async function toggleRecord() {
  if (!current.value || state.value === 'saving') return
  if (state.value === 'recording') {
    recorder?.stop()
    return
  }
  micError.value = ''
  try {
    await ensureStream()
  } catch {
    micError.value = 'Microphone blocked - allow it in the browser and reload.'
    return
  }
  discardTake()
  chunks = []
  recorder = new MediaRecorder(stream, MIME ? { mimeType: MIME } : undefined)
  recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data)
  recorder.onstop = () => {
    takeBlob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' })
    takeUrl.value = URL.createObjectURL(takeBlob)
    state.value = 'review'
    playTake() // instant QC: hear it before keeping it
  }
  recorder.start()
  state.value = 'recording'
}

function playTake() {
  if (takeUrl.value) new Audio(takeUrl.value).play()
}

function discardTake() {
  if (takeUrl.value) URL.revokeObjectURL(takeUrl.value)
  takeUrl.value = null
  takeBlob = null
}

function redoTake() {
  discardTake()
  state.value = 'idle'
  toggleRecord()
}

async function save() {
  if (state.value !== 'review' || !takeBlob || !current.value) return
  state.value = 'saving'
  const form = new FormData()
  const ext = (takeBlob.type.includes('mp4') && 'm4a') || (takeBlob.type.includes('ogg') && 'ogg') || 'webm'
  form.append('audio', takeBlob, `take.${ext}`)

  try {
    if (kind.value === 'sentence') {
      await api.post(`/record/sentence/${current.value.id}`, form)
    } else {
      form.append('word', current.value.word)
      await api.post('/record/word', form)
    }

    discardTake()
    state.value = 'idle'

    if (override.value) {
      override.value = null
      await loadSubmitted() // the re-recorded take is pending again
      return
    }

    if (mode.value === 'sentences') {
      queue.value.sentences.shift()
      queue.value.sentence_done++
      queue.value.sentence_pending = (queue.value.sentence_pending ?? 0) + 1
    } else {
      queue.value.words.shift()
      queue.value.word_done++
      queue.value.word_pending = (queue.value.word_pending ?? 0) + 1
    }
    sub.value = null // "My takes" refetches fresh next time it opens

    // The queue arrives in slices - refill when a slice runs dry.
    if (!items.value.length && done.value < total.value) {
      loading.value = true
      await loadQueue()
    }
  } catch {
    error.value = 'Saving failed - the take is still here, try again.'
    state.value = 'review'
  }
}

function skip() {
  if (!current.value || override.value || state.value === 'recording') return
  discardTake()
  state.value = 'idle'
  const list = items.value
  list.push(list.shift()) // to the back of the line
}

function playReference() {
  if (!current.value) return
  if (kind.value === 'sentence') playSentence(current.value.finnish_text, current.value.audio_url)
  else playWord(current.value.word)
}

function setMode(m) {
  if (state.value === 'recording') recorder?.stop()
  discardTake()
  state.value = 'idle'
  override.value = null
  mode.value = m
  if (m === 'submitted' && !sub.value) loadSubmitted()
}

function onKey(e) {
  if (!allowed.value || !current.value) return
  if (e.target && ['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return
  if (e.code === 'Space') {
    e.preventDefault()
    toggleRecord()
  } else if (state.value === 'review') {
    if (e.key === 'Enter') save()
    else if (e.key === 'r') redoTake()
    else if (e.key === 'l') playTake()
  } else if (e.key === 'p') {
    playReference()
  }
}

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  if (state.value === 'recording') recorder?.stop()
  discardTake()
  stream?.getTracks().forEach((t) => t.stop())
})
</script>

<template>
  <!-- single root element - required by the page transition in App.vue -->
  <div>
    <div v-if="loading" class="spinner"></div>

    <div v-else-if="!allowed || (error && !queue.sentence_total)" class="studio">
      <router-link to="/dashboard" class="back">‹ Back</router-link>
      <div class="card denied">
        <p>{{ error || 'This account has no recording rights.' }}</p>
        <p class="muted">Recording access is granted from the admin panel or with <code>php artisan user:recorder &lt;email&gt;</code>.</p>
      </div>
    </div>

    <div v-else class="studio">
      <router-link to="/dashboard" class="back">‹ Back</router-link>

      <div class="head">
        <h2><Mic class="head-ico" aria-hidden="true" /> Recording studio</h2>
        <p class="muted">
          Your voice replaces the robot's, one take at a time. Space to record,
          enter to keep - it plays back automatically so you catch bad takes
          instantly. Kept takes go live after a quick review.
        </p>
      </div>

      <div class="tabs">
        <button :class="{ on: mode === 'sentences' }" @click="setMode('sentences')">
          Sentences <span class="tab-count">{{ queue.sentence_done }}/{{ queue.sentence_total }}</span>
        </button>
        <button :class="{ on: mode === 'words' }" @click="setMode('words')">
          Words <span class="tab-count">{{ queue.word_done }}/{{ queue.word_total }}</span>
        </button>
        <button :class="{ on: mode === 'submitted' }" @click="setMode('submitted')">
          My takes
        </button>
      </div>

      <template v-if="mode !== 'submitted'">
        <div class="progress-track studio-progress">
          <div class="progress-fill" :style="{ width: pct + '%' }"></div>
        </div>
        <p v-if="pending" class="muted pending-note"><Clock class="sm-ico" aria-hidden="true" /> {{ pending }} awaiting review</p>
      </template>

      <!-- re-record banner: this take replaces an earlier one, via review -->
      <div v-if="override" class="override-note">
        <RotateCcw class="sm-ico" aria-hidden="true" /> Re-recording - the new take replaces the old one after review.
        <button class="override-cancel" @click="cancelOverride">Cancel</button>
      </div>

      <!-- the recording flow (queue head or an override pick) -->
      <template v-if="current">
        <div class="card take-card" :class="state">
          <p class="take-kicker">
            {{ override ? 'New take' : `${done + 1} of ${total}` }} · read this out loud
          </p>
          <p class="take-fi">{{ kind === 'sentence' ? current.finnish_text : current.word }}</p>
          <p v-if="kind === 'sentence' && current.english_text" class="take-en muted">{{ current.english_text }}</p>
          <button class="ref-btn" title="Hear the current version (p)" @click="playReference">
            <Volume2 class="sm-ico" aria-hidden="true" /> Hear the current version
          </button>
        </div>

        <p v-if="micError" class="error-msg">{{ micError }}</p>
        <p v-else-if="error" class="error-msg">{{ error }}</p>

        <div class="controls">
          <button
            v-if="state === 'idle' || state === 'recording'"
            class="btn btn-block rec-btn"
            :class="state === 'recording' ? 'btn-ghost recording' : 'btn-primary'"
            @click="toggleRecord"
          >
            <template v-if="state === 'recording'"><Square class="rec-ico" aria-hidden="true" /> Stop</template>
            <template v-else><Circle class="rec-ico rec-dot" aria-hidden="true" /> Record</template>
            <span class="key-hint">space</span>
          </button>

          <template v-else>
            <div class="review-row">
              <button class="btn btn-ghost" :disabled="state === 'saving'" @click="playTake">
                <Play class="sm-ico" aria-hidden="true" /> Listen <span class="key-hint">l</span>
              </button>
              <button class="btn btn-ghost" :disabled="state === 'saving'" @click="redoTake">
                <RotateCcw class="sm-ico" aria-hidden="true" /> Redo <span class="key-hint">r</span>
              </button>
              <button class="btn btn-primary keep" :disabled="state === 'saving'" @click="save">
                <template v-if="state === 'saving'">Saving…</template>
                <template v-else><Check class="sm-ico" aria-hidden="true" /> Keep it</template> <span class="key-hint">enter</span>
              </button>
            </div>
          </template>

          <button v-if="!override" class="skip" :disabled="state === 'recording' || state === 'saving'" @click="skip">
            Skip for now →
          </button>
        </div>
      </template>

      <div v-else-if="mode !== 'submitted'" class="card all-done">
        <PartyPopper class="sm-ico" aria-hidden="true" /> Everything in this mode has your voice. Kiitos!
      </div>

      <!-- "My takes": everything submitted, searchable, re-recordable -->
      <template v-if="mode === 'submitted' && !override">
        <div v-if="subLoading" class="spinner"></div>

        <template v-else-if="subLists">
          <input
            v-model="subQ"
            type="search"
            class="sub-search"
            placeholder="Search your takes…"
            aria-label="Search your takes"
          />

          <p v-if="!subLists.pending.length && !subLists.live.length" class="muted sub-empty">
            {{ subQ ? `Nothing matches "${subQ}".` : 'Nothing submitted yet - your kept takes will show up here.' }}
          </p>

          <section v-if="subLists.pending.length" class="sub-group">
            <h3 class="sub-title"><Clock class="sm-ico" aria-hidden="true" /> Awaiting review <span class="muted">{{ subLists.pending.length }}</span></h3>
            <div v-for="row in subLists.pending.slice(0, SHOW_MAX)" :key="row.keyId" class="card sub-row">
              <div class="sub-main">
                <p class="sub-label">{{ row.label }}</p>
                <p v-if="row.note" class="sub-note muted">{{ row.note }}</p>
              </div>
              <div class="sub-actions">
                <button class="sub-btn" title="Play your take" @click="playClip(row.url)"><Play class="sub-ico" aria-hidden="true" /></button>
                <button class="sub-btn" title="Record a new take" @click="redo(row)"><RotateCcw class="sub-ico" aria-hidden="true" /> Again</button>
              </div>
            </div>
            <p v-if="subLists.pending.length > SHOW_MAX" class="muted sub-more">
              +{{ subLists.pending.length - SHOW_MAX }} more - search to narrow down.
            </p>
          </section>

          <section v-if="subLists.live.length" class="sub-group">
            <h3 class="sub-title"><CircleCheck class="sm-ico" aria-hidden="true" /> Live in the app <span class="muted">{{ subLists.live.length }}</span></h3>
            <div v-for="row in subLists.live.slice(0, SHOW_MAX)" :key="row.keyId" class="card sub-row">
              <div class="sub-main">
                <p class="sub-label">{{ row.label }}</p>
                <p v-if="row.note" class="sub-note muted">{{ row.note }}</p>
              </div>
              <div class="sub-actions">
                <button class="sub-btn" title="Play the live take" @click="playClip(row.url)"><Play class="sub-ico" aria-hidden="true" /></button>
                <button class="sub-btn" title="Record a replacement (goes through review)" @click="redo(row)"><RotateCcw class="sub-ico" aria-hidden="true" /> Again</button>
              </div>
            </div>
            <p v-if="subLists.live.length > SHOW_MAX" class="muted sub-more">
              +{{ subLists.live.length - SHOW_MAX }} more - search to narrow down.
            </p>
          </section>
        </template>
      </template>
    </div>
  </div>
</template>

<style scoped>
.studio { max-width: 560px; margin: 0 auto; }
.back { display: inline-block; color: var(--text-dim); font-size: 14px; margin-bottom: 14px; }
.back:hover { color: var(--text); }
.head { margin-bottom: 18px; }
.head h2 { font-size: 24px; margin-bottom: 6px; display: flex; align-items: center; gap: 9px; }
.head-ico { width: 20px; height: 20px; color: var(--accent); flex-shrink: 0; }
.sm-ico { width: 14px; height: 14px; vertical-align: -2px; flex-shrink: 0; }
.sub-ico { width: 12px; height: 12px; vertical-align: -1px; }
.rec-ico { width: 15px; height: 15px; vertical-align: -2px; }
.rec-dot { color: var(--red, #f87171); fill: currentColor; }
.head .muted { line-height: 1.5; font-size: 14px; }

.denied { text-align: center; padding: 26px 20px; line-height: 1.6; }
.denied code { font-size: 12px; background: var(--bg-soft); border-radius: 6px; padding: 2px 6px; }

.tabs { display: flex; gap: 8px; margin-bottom: 10px; }
.tabs button {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  padding: 10px;
  cursor: pointer;
}
.tabs button.on { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
.tab-count { font-size: 12px; font-weight: 600; opacity: 0.8; }

.studio-progress { margin-bottom: 8px; }
.pending-note { font-size: 12px; text-align: center; margin-bottom: 12px; }

.override-note {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
  font-weight: 600;
  color: var(--accent);
  background: var(--accent-soft);
  border: 1px dashed var(--accent);
  border-radius: var(--radius-sm);
  padding: 9px 13px;
  margin-bottom: 12px;
}
.override-cancel {
  background: none;
  border: none;
  color: var(--accent);
  font-family: inherit;
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.all-done { text-align: center; padding: 30px 20px; font-size: 16px; font-weight: 700; }

.take-card { text-align: center; padding: 28px 22px; margin-bottom: 14px; transition: border-color 0.2s ease; }
.take-card.recording { border-color: var(--red); }
.take-card.review { border-color: var(--accent); }
.take-kicker {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin-bottom: 12px;
}
.take-fi { font-size: 26px; font-weight: 800; line-height: 1.35; }
.take-en { font-size: 15px; margin-top: 8px; }
.ref-btn {
  margin-top: 16px;
  background: none;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 600;
  padding: 6px 14px;
  cursor: pointer;
}
.ref-btn:hover { border-color: var(--accent); color: var(--accent); }

.controls { display: flex; flex-direction: column; gap: 10px; }
.rec-btn { font-size: 17px; padding: 15px; }
.rec-btn.recording { border-color: var(--red); color: var(--red); animation: pulse 1.2s ease infinite; }
@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.35); }
  50% { box-shadow: 0 0 0 8px transparent; }
}
.review-row { display: grid; grid-template-columns: 1fr 1fr 1.4fr; gap: 10px; }
.review-row .btn { padding: 13px 8px; font-size: 14px; }
.key-hint {
  font-size: 10px;
  font-weight: 700;
  opacity: 0.6;
  border: 1px solid currentColor;
  border-radius: 5px;
  padding: 1px 5px;
  margin-left: 6px;
  text-transform: uppercase;
}
.skip {
  background: none;
  border: none;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 6px;
}
.skip:hover:not(:disabled) { color: var(--text); }
.skip:disabled { opacity: 0.4; cursor: default; }

/* ---- my takes ---- */
.sub-search {
  width: 100%;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-family: inherit;
  font-size: 14px;
  padding: 10px 14px;
  margin-bottom: 14px;
}
.sub-search:focus { outline: none; border-color: var(--accent); }
.sub-empty { text-align: center; margin: 20px 0; }
.sub-group { margin-bottom: 18px; }
.sub-title {
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin-bottom: 8px;
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.sub-row { display: flex; align-items: center; gap: 12px; padding: 10px 13px; margin-bottom: 8px; }
.sub-main { flex: 1; min-width: 0; }
.sub-label { font-weight: 700; font-size: 14.5px; }
.sub-note { font-size: 12px; margin-top: 1px; }
.sub-actions { display: flex; gap: 6px; flex-shrink: 0; }
.sub-btn {
  background: none;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 700;
  padding: 7px 10px;
  cursor: pointer;
  white-space: nowrap;
}
.sub-btn:hover { border-color: var(--accent); color: var(--accent); }
.sub-more { font-size: 12px; text-align: center; }
</style>
