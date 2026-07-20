<script setup>
// Recording studio: a native speaker replaces the TTS audio one take at a
// time. Built for speed - the whole loop runs on three keys: space records,
// enter keeps, r redoes. Instant playback after every take is the QC step.
// Kept takes go through admin review before they replace the app audio.
// The "My takes" tab shows everything submitted - waiting or live - and any
// of it can be re-recorded (the new take goes back through review).
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ArrowLeft, Check, Circle, CircleCheck, Clock, Mic, PartyPopper, Play, RotateCcw, Square, UserRound, Volume2 } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const auth = useAuthStore()
const { playSentence, playWord, playClip } = useFinnishAudio()

const allowed = computed(() => !!(auth.user?.is_recorder || auth.user?.is_admin))
const loading = ref(true)
const error = ref('')
const micError = ref('')

const mode = ref('sentences') // sentences | words | pairs | conversations | submitted
const queue = ref({ sentences: [], words: [], pairs: [], sentence_total: 0, sentence_done: 0, word_total: 0, word_done: 0, pair_total: 0, pair_done: 0 })

// Re-record flow: a take picked from "My takes" overrides the queue head.
const override = ref(null) // { kind: 'sentence'|'word'|'listening', item }

// ---- conversations: pick a scene, then pick ONE speaker in it ----
//
// A scene is two people talking, so its lines can never be one flat queue:
// whoever works through that list becomes both characters, and the dialogue
// stops being a dialogue. You always record one named speaker at a time, and
// the other speaker's progress stays visible so it's obvious the scene isn't
// finished until a second voice records the other half.
const lq = ref({ scenes: [], line_total: 0, line_done: 0 })
const convScene = ref(null)
const convSpeaker = ref(null)

const scene = computed(() => lq.value.scenes.find((s) => s.id === convScene.value) ?? null)
const speaker = computed(() => scene.value?.speakers.find((s) => s.key === convSpeaker.value) ?? null)
// The other parts in this scene - the reason the tab is shaped like this.
const otherSpeakers = computed(() => scene.value?.speakers.filter((s) => s.key !== convSpeaker.value) ?? [])

// Only lines still on TTS are worth recording; the scene id rides along so a
// take knows where to POST even when reached via a "My takes" override.
const convLines = computed(() =>
  (speaker.value?.lines ?? [])
    .filter((l) => l.state === 'tts')
    .map((l) => ({ ...l, scene: convScene.value, speaker: speaker.value.name, voice: speaker.value.voice }))
)

const items = computed(() => {
  if (mode.value === 'sentences') return queue.value.sentences
  if (mode.value === 'words') return queue.value.words
  if (mode.value === 'pairs') return queue.value.pairs
  if (mode.value === 'conversations') return convLines.value
  return []
})
const current = computed(() => override.value?.item ?? items.value[0] ?? null)
// Sentence-tab items carry their own kind: a Taivutus phrase reads exactly
// like a course sentence, it just posts somewhere else.
const kind = computed(() => {
  if (override.value) return override.value.kind
  if (mode.value === 'words') return 'word'
  if (mode.value === 'pairs') return 'pair'
  if (mode.value === 'conversations') return 'listening'
  return current.value?.kind ?? 'sentence'
})

// Sentences carry finnish_text/english_text, conversation lines carry fi/en -
// one accessor each so the take card doesn't branch three ways.
const currentText = computed(() => {
  if (!current.value) return ''
  if (kind.value === 'word' || kind.value === 'pair') return current.value.word
  return current.value.finnish_text ?? current.value.fi ?? ''
})
const currentEn = computed(() => {
  if (!current.value || kind.value === 'word' || kind.value === 'pair') return ''
  return current.value.english_text ?? current.value.en ?? ''
})
const currentRefUrl = computed(() => current.value?.audio_url ?? current.value?.current_url ?? null)

// Inside a conversation the counters describe the SPEAKER's part, not the
// whole scene - "3 of 5" means Marja's five lines, which is the unit of work.
const done = computed(() => {
  if (mode.value === 'conversations') return speaker.value?.done ?? 0
  if (mode.value === 'pairs') return queue.value.pair_done
  return mode.value === 'sentences' ? queue.value.sentence_done : queue.value.word_done
})
const total = computed(() => {
  if (mode.value === 'conversations') return speaker.value?.total ?? 0
  if (mode.value === 'pairs') return queue.value.pair_total
  return mode.value === 'sentences' ? queue.value.sentence_total : queue.value.word_total
})
const pending = computed(() => {
  if (mode.value === 'conversations') return speaker.value?.pending ?? 0
  if (mode.value === 'pairs') return queue.value.pair_pending ?? 0
  return mode.value === 'sentences' ? queue.value.sentence_pending ?? 0 : queue.value.word_pending ?? 0
})
const pct = computed(() => (total.value ? Math.round((done.value / total.value) * 100) : 0))
// How many unrecorded items the search matched (server-side count, not the
// slice length - "3 of 100 shown" would be a lie).
const matchCount = computed(() =>
  mode.value === 'words' ? queue.value.word_matches ?? 0 : queue.value.sentence_matches ?? 0
)

// ---- search ----
//
// The queue is sliced server-side (100 sentences / 200 words), so filtering
// in the browser would only ever search the first slice - the sentence you're
// hunting for is usually not in it. The query goes to the server instead.
const q = ref('')
const searching = ref(false)
let qTimer = null

function onSearch() {
  clearTimeout(qTimer)
  searching.value = true
  qTimer = setTimeout(async () => {
    // A search re-heads the queue; a half-recorded take would belong to the
    // old head, so drop it rather than silently file it under the new one.
    discardTake()
    if (state.value === 'recording') recorder?.stop()
    state.value = 'idle'
    await loadQueue()
    searching.value = false
  }, 300)
}

function clearSearch() {
  q.value = ''
  onSearch()
}

// Robot-first: a clip already upgraded to ElevenLabs sounds decent, so a
// native's takes are better spent on the plain edge-tts ones first. On by
// default; the toggle only appears once there's ElevenLabs coverage to skip.
const robotFirst = ref(true)
const elevenLeft = computed(() => {
  if (mode.value === 'words') return queue.value.word_eleven ?? 0
  if (mode.value === 'pairs') return queue.value.pair_eleven ?? 0
  return queue.value.sentence_eleven ?? 0
})
// The toggle is worth showing whenever any queue has ElevenLabs clips -
// switching tabs shouldn't make it vanish mid-session.
const hasEleven = computed(() =>
  (queue.value.sentence_eleven ?? 0) + (queue.value.word_eleven ?? 0) + (queue.value.pair_eleven ?? 0) > 0
)

async function loadQueue() {
  try {
    const params = {}
    if (q.value.trim()) params.q = q.value.trim()
    // Only send when off - default true matches the server, so the common
    // case sends nothing.
    if (!robotFirst.value) params.robot_first = 0
    const { data } = await api.get('/record/queue', { params })
    queue.value = data
  } catch (e) {
    error.value = e.response?.status === 403 ? 'This account has no recording rights.' : 'Could not load the queue.'
  } finally {
    loading.value = false
  }
}

function toggleRobotFirst() {
  robotFirst.value = !robotFirst.value
  discardTake()
  if (state.value === 'recording') recorder?.stop()
  state.value = 'idle'
  loadQueue()
}

async function loadListeningQueue() {
  try {
    const { data } = await api.get('/record/listening')
    lq.value = data
  } catch {
    error.value = 'Could not load the conversations.'
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
  // Conversation lines are searchable by their text OR by who says them -
  // "marja" pulls up every line that voice is responsible for.
  const hitLine = (l) => hit(l.fi) || hit(l.speaker) || hit(l.scene_title)
  const pendingRows = [
    ...sub.value.sentences.filter((s) => hit(s.finnish_text)).map((s) => ({
      kind: 'sentence', keyId: `ps-${s.id}`, label: s.finnish_text, note: s.english_text, url: s.pending_url, item: s
    })),
    ...(sub.value.phrases ?? []).filter((p) => hit(p.text)).map((p) => ({
      kind: 'phrase', keyId: `pp-${p.base}`, label: p.text, note: 'Taivutus drill', url: p.pending_url, item: p
    })),
    ...(sub.value.listening ?? []).filter(hitLine).map((l) => ({
      kind: 'listening', keyId: `pl-${l.scene}-${l.index}`, label: l.fi,
      note: `${l.speaker} (${l.voice}) · ${l.scene_title}`, url: l.pending_url, item: l
    })),
    ...sub.value.words.filter((w) => hit(w.word)).map((w) => ({
      kind: 'word', keyId: `pw-${w.word}`, label: w.word, note: 'word', url: w.pending_url, item: w
    })),
    ...(sub.value.pairs ?? []).filter((p) => hit(p.word)).map((p) => ({
      kind: 'pair', keyId: `ppr-${p.word}`, label: p.word, note: 'Kuulo drill', url: p.pending_url, item: p
    }))
  ]
  const liveRows = [
    ...sub.value.live_sentences.filter((s) => hit(s.finnish_text)).map((s) => ({
      kind: 'sentence', keyId: `ls-${s.id}`, label: s.finnish_text, note: s.english_text, url: s.audio_url, item: s
    })),
    ...(sub.value.live_phrases ?? []).filter((p) => hit(p.text)).map((p) => ({
      kind: 'phrase', keyId: `lp-${p.base}`, label: p.text, note: 'Taivutus drill', url: p.audio_url, item: p
    })),
    ...(sub.value.live_listening ?? []).filter(hitLine).map((l) => ({
      kind: 'listening', keyId: `ll-${l.scene}-${l.index}`, label: l.fi,
      note: `${l.speaker} (${l.voice}) · ${l.scene_title}`, url: l.audio_url, item: l
    })),
    ...sub.value.live_words.filter((w) => hit(w.word)).map((w) => ({
      kind: 'word', keyId: `lw-${w.word}`, label: w.word, note: 'word', url: w.audio_url, item: w
    })),
    ...(sub.value.live_pairs ?? []).filter((p) => hit(p.word)).map((p) => ({
      kind: 'pair', keyId: `lpr-${p.word}`, label: p.word, note: 'Kuulo drill', url: p.audio_url, item: p
    }))
  ]
  return { pending: pendingRows, live: liveRows }
})

function redo(row) {
  const item =
    row.kind === 'sentence' || row.kind === 'phrase'
      ? { kind: row.kind, id: row.item.id ?? row.item.base, finnish_text: row.item.finnish_text ?? row.item.text, english_text: row.item.english_text, audio_url: row.url }
      : row.kind === 'listening'
        ? {
            scene: row.item.scene, index: row.item.index, fi: row.item.fi, en: row.item.en,
            speaker: row.item.speaker, voice: row.item.voice, current_url: row.url
          }
        : { word: row.item.word, audio_url: row.url }

  override.value = { kind: row.kind, item }
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
    } else if (kind.value === 'phrase') {
      await api.post(`/record/phrase/${current.value.id}`, form)
    } else if (kind.value === 'listening') {
      await api.post(`/record/listening/${current.value.scene}/${current.value.index}`, form)
    } else if (kind.value === 'pair') {
      form.append('word', current.value.word)
      await api.post('/record/pair', form)
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

    if (mode.value === 'conversations') {
      // The line leaves this speaker's queue by changing state, which also
      // ticks their pending count - convLines is derived, so both follow.
      const line = speaker.value.lines.find((l) => l.index === current.value.index)
      if (line) line.state = 'pending'
      speaker.value.pending++
    } else if (mode.value === 'sentences') {
      queue.value.sentences.shift()
      queue.value.sentence_done++
      queue.value.sentence_matches = Math.max(0, (queue.value.sentence_matches ?? 1) - 1)
      queue.value.sentence_pending = (queue.value.sentence_pending ?? 0) + 1
    } else if (mode.value === 'pairs') {
      queue.value.pairs.shift()
      queue.value.pair_done++
      queue.value.pair_pending = (queue.value.pair_pending ?? 0) + 1
    } else {
      queue.value.words.shift()
      queue.value.word_done++
      queue.value.word_matches = Math.max(0, (queue.value.word_matches ?? 1) - 1)
      queue.value.word_pending = (queue.value.word_pending ?? 0) + 1
    }
    sub.value = null // "My takes" refetches fresh next time it opens

    // The queue arrives in slices - refill when a slice runs dry.
    if (mode.value !== 'conversations' && !items.value.length && done.value < total.value) {
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
  // Pair clips live outside the word manifest, so their URL rides on the item.
  if (kind.value === 'word') playWord(current.value.word)
  else playSentence(currentText.value, currentRefUrl.value)
}

function setMode(m) {
  if (state.value === 'recording') recorder?.stop()
  discardTake()
  state.value = 'idle'
  override.value = null
  mode.value = m
  if (m === 'submitted' && !sub.value) loadSubmitted()
  if (m === 'conversations' && !lq.value.scenes.length) loadListeningQueue()
}

function pickScene(id) {
  convScene.value = id
  convSpeaker.value = null
  discardTake()
  state.value = 'idle'
}

function pickSpeaker(key) {
  convSpeaker.value = key
  discardTake()
  state.value = 'idle'
}

// Back out of a scene/speaker without losing the tab.
function leaveConv() {
  if (state.value === 'recording') recorder?.stop()
  discardTake()
  state.value = 'idle'
  if (convSpeaker.value) convSpeaker.value = null
  else convScene.value = null
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
        <button :class="{ on: mode === 'pairs' }" @click="setMode('pairs')">
          Kuulo <span class="tab-count">{{ queue.pair_done }}/{{ queue.pair_total }}</span>
        </button>
        <button :class="{ on: mode === 'conversations' }" @click="setMode('conversations')">
          Talks <span v-if="lq.line_total" class="tab-count">{{ lq.line_done }}/{{ lq.line_total }}</span>
        </button>
        <button :class="{ on: mode === 'submitted' }" @click="setMode('submitted')">
          My takes
        </button>
      </div>

      <!-- Conversations: pick a scene, then pick which character you're voicing.
           Never a flat list of lines - that's how a two-person dialogue ends up
           recorded by one person. -->
      <template v-if="mode === 'conversations' && !override">
        <!-- 1. the scenes -->
        <template v-if="!convScene">
          <p class="muted conv-lede">
            Each conversation needs <b>two different voices</b>. Pick a scene, then pick the
            character you're voicing - record only their lines and let someone else take the other part.
          </p>
          <div v-for="s in lq.scenes" :key="s.id" class="card conv-scene" @click="pickScene(s.id)">
            <span class="conv-emoji">{{ s.emoji }}</span>
            <div class="conv-main">
              <p class="conv-title">{{ s.title }}</p>
              <p class="conv-sub muted">
                <span v-for="(sp, i) in s.speakers" :key="sp.key">
                  <template v-if="i"> · </template>{{ sp.name }} ({{ sp.voice }}) {{ sp.done }}/{{ sp.total }}
                </span>
              </p>
            </div>
            <span class="conv-count" :class="{ full: s.done === s.total }">{{ s.done }}/{{ s.total }}</span>
          </div>
          <p v-if="!lq.scenes.length" class="muted sub-empty">No conversations yet.</p>
        </template>

        <!-- 2. the characters in that scene -->
        <template v-else-if="!convSpeaker">
          <button class="conv-back" @click="leaveConv"><ArrowLeft class="sm-ico" aria-hidden="true" /> All conversations</button>
          <p class="conv-scene-name">{{ scene.emoji }} {{ scene.title }}</p>
          <p class="muted conv-lede">Which character are you? Record only their lines.</p>
          <button
            v-for="sp in scene.speakers"
            :key="sp.key"
            class="card conv-speaker"
            :class="{ full: sp.done === sp.total }"
            @click="pickSpeaker(sp.key)"
          >
            <UserRound class="conv-person" :class="sp.voice" aria-hidden="true" />
            <div class="conv-main">
              <p class="conv-title">{{ sp.name }} <span class="voice-tag" :class="sp.voice">{{ sp.voice }} voice</span></p>
              <p class="conv-sub muted">{{ sp.role || 'speaker' }} · {{ sp.total }} lines</p>
            </div>
            <span class="conv-count" :class="{ full: sp.done === sp.total }">{{ sp.done }}/{{ sp.total }}</span>
          </button>
        </template>
      </template>

      <!-- Who you are right now. Stays on screen through every take in the
           scene, because "which one am I again?" is the mistake that ruins a
           whole conversation. -->
      <div v-if="mode === 'conversations' && speaker && !override" class="as-banner" :class="speaker.voice">
        <UserRound class="sm-ico" aria-hidden="true" />
        <span class="as-text">
          You are <b>{{ speaker.name }}</b> - {{ speaker.voice }} voice<span v-if="speaker.role">, {{ speaker.role }}</span>
        </span>
        <button class="as-change" @click="leaveConv">Change</button>
      </div>
      <p v-if="mode === 'conversations' && speaker && otherSpeakers.length && !override" class="muted other-note">
        Still needs another voice:
        <span v-for="(o, i) in otherSpeakers" :key="o.key">
          <template v-if="i">, </template><b>{{ o.name }}</b> ({{ o.voice }}) {{ o.done }}/{{ o.total }}
        </span>
      </p>

      <!-- Kuulo words drill one vowel contrast each - the instruction matters:
           an exaggerated vowel teaches learners a contrast nobody speaks. -->
      <p v-if="mode === 'pairs' && !override" class="muted conv-lede">
        Single words from the vowel drills (y/u, ä/a, ö/o). Say each word the way
        you'd say it in a sentence - <b>don't exaggerate the vowel</b>. The learner's
        job is to hear the difference in normal speech.
      </p>

      <!-- Jump straight to a specific line instead of skipping toward it.
           Server-side: the queue is sliced, so the match usually isn't in
           the slice the browser holds. -->
      <div v-if="(mode === 'sentences' || mode === 'words') && !override" class="q-row">
        <input
          v-model="q"
          type="search"
          class="q-input"
          :placeholder="mode === 'words' ? 'Search words…' : 'Search sentences (Finnish or English)…'"
          :aria-label="mode === 'words' ? 'Search words' : 'Search sentences'"
          @input="onSearch"
        />
        <button v-if="q" class="q-clear" title="Clear search" @click="clearSearch">✕</button>
      </div>
      <p v-if="(mode === 'sentences' || mode === 'words') && q && !override" class="muted q-note">
        <template v-if="searching">Searching…</template>
        <template v-else-if="!items.length">Nothing left to record matching "{{ q }}".</template>
        <template v-else>{{ matchCount }} to record matching "{{ q }}"</template>
      </p>

      <!-- Robot-first: hide the clips ElevenLabs already voiced, so a native's
           takes land on the plain-robot ones first. Only shown when there's
           ElevenLabs coverage to skip. -->
      <label v-if="(mode === 'sentences' || mode === 'words' || mode === 'pairs') && hasEleven && !override" class="robot-first">
        <input type="checkbox" :checked="robotFirst" @change="toggleRobotFirst" />
        <span class="rf-text">
          Replace robot voices first
          <span class="rf-sub muted">
            <template v-if="robotFirst">{{ elevenLeft }} already on ElevenLabs — hidden. Uncheck to include them.</template>
            <template v-else>Showing ElevenLabs clips too — a human take still improves them.</template>
          </span>
        </span>
      </label>

      <template v-if="mode !== 'submitted' && (mode !== 'conversations' || speaker || override)">
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
            <span v-if="kind === 'listening' && current.speaker" class="take-as">as {{ current.speaker }}</span>
            <span v-else-if="kind === 'phrase'" class="take-as">Taivutus drill</span>
            <span v-else-if="kind === 'pair'" class="take-as">Kuulo drill</span>
            <span v-if="current.tier === 'eleven'" class="take-tier eleven">replacing ElevenLabs</span>
            <span v-else-if="current.tier === 'tts'" class="take-tier">replacing robot</span>
          </p>
          <p class="take-fi">{{ currentText }}</p>
          <p v-if="currentEn" class="take-en muted">{{ currentEn }}</p>
          <button v-if="currentRefUrl" class="ref-btn" title="Hear the current version (p)" @click="playReference">
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

      <div v-else-if="mode === 'conversations' && speaker" class="card all-done">
        <PartyPopper class="sm-ico" aria-hidden="true" />
        {{ speaker.name }}'s lines are all recorded. Kiitos!
        <p v-if="otherSpeakers.some((o) => o.done + o.pending < o.total)" class="muted done-next">
          This scene still needs
          <b>{{ otherSpeakers.filter((o) => o.done + o.pending < o.total).map((o) => `${o.name} (${o.voice})`).join(', ') }}</b>
          - a different voice has to record that part.
        </p>
        <button class="btn btn-ghost done-btn" @click="leaveConv">Back to the scene</button>
      </div>

      <!-- Robot-first cleared the plain-TTS clips but ElevenLabs ones remain:
           don't claim it's finished - offer them. -->
      <div v-else-if="mode !== 'submitted' && mode !== 'conversations' && robotFirst && elevenLeft" class="card all-done">
        <PartyPopper class="sm-ico" aria-hidden="true" /> Every robot clip here has your voice now!
        <p class="muted done-next">
          {{ elevenLeft }} more {{ elevenLeft === 1 ? 'is' : 'are' }} on ElevenLabs - a decent voice, but a human take still beats it.
        </p>
        <button class="btn btn-ghost done-btn" @click="toggleRobotFirst">Record those too</button>
      </div>

      <div v-else-if="mode !== 'submitted' && mode !== 'conversations'" class="card all-done">
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

/* ---- queue search ---- */
.q-row { position: relative; margin-bottom: 8px; }
.q-input {
  width: 100%;
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px 34px 10px 13px;
  font-family: inherit;
  font-size: 14px;
  color: var(--text);
  outline: none;
}
.q-input:focus { border-color: var(--accent); }
.q-clear {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-faint);
  font-size: 13px;
  cursor: pointer;
  padding: 4px 6px;
}
.q-clear:hover { color: var(--text); }
.q-note { font-size: 12px; margin-bottom: 10px; }

/* ---- robot-first toggle ---- */
.robot-first {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  padding: 10px 12px;
  margin-bottom: 10px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 600;
}
.robot-first input { margin-top: 2px; flex-shrink: 0; cursor: pointer; }
.rf-text { display: flex; flex-direction: column; gap: 2px; }
.rf-sub { font-size: 11.5px; font-weight: 500; line-height: 1.4; }

.take-tier {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border-radius: var(--radius-pill);
  padding: 2px 8px;
  margin-left: 6px;
  color: var(--text-dim);
  background: var(--bg-soft);
}
.take-tier.eleven { color: #a78bfa; background: color-mix(in srgb, #a78bfa 16%, transparent); }

/* ---- conversations: scene → speaker ---- */
.conv-lede { font-size: 13px; line-height: 1.55; margin-bottom: 12px; }
.conv-back {
  background: none;
  border: none;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  margin-bottom: 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.conv-back:hover { color: var(--text); }
.conv-scene-name { font-size: 17px; font-weight: 800; margin-bottom: 4px; }

.conv-scene,
.conv-speaker {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  text-align: left;
  padding: 13px 14px;
  margin-bottom: 8px;
  cursor: pointer;
  font-family: inherit;
  transition: border-color 0.15s ease;
}
.conv-scene:hover,
.conv-speaker:hover { border-color: var(--accent); }
.conv-scene.full,
.conv-speaker.full { border-color: color-mix(in srgb, var(--green) 45%, transparent); }
.conv-emoji { font-size: 24px; flex-shrink: 0; }
.conv-person { width: 22px; height: 22px; flex-shrink: 0; }
/* The two voices never share a colour - the whole point is telling them apart. */
.conv-person.female { color: #c084fc; }
.conv-person.male { color: #60a5fa; }
.conv-main { flex: 1; min-width: 0; }
.conv-title { font-weight: 800; font-size: 14.5px; color: var(--text); display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.conv-sub { font-size: 12px; margin-top: 2px; line-height: 1.4; }
.conv-count {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-dim);
  background: var(--bg-soft);
  border-radius: var(--radius-pill);
  padding: 4px 10px;
}
.conv-count.full { color: var(--green); background: var(--green-soft); }

.voice-tag {
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border-radius: var(--radius-pill);
  padding: 2px 8px;
}
.voice-tag.female { color: #c084fc; background: color-mix(in srgb, #c084fc 16%, transparent); }
.voice-tag.male { color: #60a5fa; background: color-mix(in srgb, #60a5fa 16%, transparent); }

/* Who you're voicing, pinned above every take in the scene. */
.as-banner {
  display: flex;
  align-items: center;
  gap: 9px;
  border-radius: var(--radius-sm);
  padding: 9px 13px;
  margin-bottom: 8px;
  font-size: 13px;
}
.as-banner.female { color: #c084fc; background: color-mix(in srgb, #c084fc 14%, transparent); border: 1px solid color-mix(in srgb, #c084fc 40%, transparent); }
.as-banner.male { color: #60a5fa; background: color-mix(in srgb, #60a5fa 14%, transparent); border: 1px solid color-mix(in srgb, #60a5fa 40%, transparent); }
.as-text { flex: 1; min-width: 0; }
.as-change {
  flex-shrink: 0;
  background: none;
  border: 1px solid currentColor;
  color: inherit;
  border-radius: var(--radius-pill);
  padding: 3px 11px;
  font-family: inherit;
  font-size: 11.5px;
  font-weight: 700;
  cursor: pointer;
}
.other-note { font-size: 12px; line-height: 1.5; margin-bottom: 10px; }
.take-as { color: var(--accent); font-weight: 800; }
.done-next { font-size: 12.5px; line-height: 1.5; margin-top: 8px; font-weight: 400; }
.done-btn { margin-top: 12px; }

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
