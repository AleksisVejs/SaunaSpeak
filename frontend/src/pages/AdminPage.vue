<script setup>
// Admin panel in four tabs:
//   Pulse    - headline numbers, 30-day trends, weekly retention cohorts
//   Activity - the who-was-here-when matrix: one row per user, one cell per
//              day (reviews + chat), with a stayed/fading/gone verdict
//   Content  - course shape and the human-recordings manager
//   Users    - search, comp Löyly+, recording rights, confirm emails
// Access is enforced by the backend (admin middleware, 403); this page just
// renders what it's allowed.
import { computed, onMounted, ref } from 'vue'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playClip } = useFinnishAudio()

const TABS = [
  { id: 'pulse', label: '📈 Pulse' },
  { id: 'activity', label: '📅 Activity' },
  { id: 'content', label: '📚 Content' },
  { id: 'users', label: '👥 Users' },
  { id: 'feedback', label: '💬 Feedback' }
]
const tab = ref('pulse')

const stats = ref(null)
const trends = ref(null)
const retention = ref(null)
const users = ref(null)
const search = ref('')
const page = ref(1)
const loading = ref(true)
const denied = ref(false)
const busy = ref({}) // user id → toggling
const exporting = ref(false) // false | true | 'error'

onMounted(load)

/**
 * Pull the full snapshot and save it as a file. Built client-side from the
 * JSON response rather than linking straight at /api/admin/export, because
 * a plain <a href> carries no Authorization header and would just 401.
 */
async function downloadExport() {
  exporting.value = true
  try {
    const { data } = await api.get('/admin/export')
    const url = URL.createObjectURL(
      new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    )
    const a = document.createElement('a')
    a.href = url
    a.download = `saunaspeak-snapshot-${data.meta?.today ?? 'export'}.json`
    a.click()
    URL.revokeObjectURL(url)
    exporting.value = false
  } catch {
    exporting.value = 'error'
  }
}

async function load() {
  loading.value = true
  try {
    const [statsRes, trendsRes, retRes, actRes, usersRes, recRes, fbRes] = await Promise.all([
      api.get('/admin/stats'),
      api.get('/admin/trends'),
      api.get('/admin/retention'),
      api.get('/admin/activity', { params: activityParams() }),
      api.get('/admin/users', { params: { page: page.value, search: search.value || undefined } }),
      api.get('/admin/recordings'),
      api.get('/admin/feedback')
    ])
    stats.value = statsRes.data
    trends.value = trendsRes.data
    retention.value = retRes.data
    activity.value = actRes.data
    users.value = usersRes.data
    recordings.value = recRes.data
    feedback.value = fbRes.data
  } catch (e) {
    if (e.response?.status === 403) denied.value = true
  } finally {
    loading.value = false
  }
}

// ---- 30-day trend strips: one series per strip, hover per bar ----
const STRIPS = [
  { key: 'signups', label: 'Signups' },
  { key: 'actives', label: 'Active learners' },
  { key: 'reviews', label: 'Reviews' }
]

function strip(key) {
  const days = trends.value?.days ?? []
  const max = Math.max(1, ...days.map((d) => d[key]))
  return {
    total: days.reduce((t, d) => t + d[key], 0),
    bars: days.map((d) => ({
      date: d.date,
      value: d[key],
      pct: Math.round((d[key] / max) * 100)
    }))
  }
}

const fmtDay = (iso) => new Date(`${iso}T12:00:00`).toLocaleDateString('en', { month: 'short', day: 'numeric' })

// ---- retention cohorts: signup week × weeks since signup ----
const maxOffsets = computed(() =>
  Math.max(0, ...(retention.value?.cohorts ?? []).map((c) => c.active.length))
)

const retPct = (c, i) => (c.size ? Math.round((c.active[i] / c.size) * 100) : 0)

// Green deepens with retention; W0 is naturally strong (signup week).
const retStyle = (c, i) => ({ background: `rgba(16, 185, 129, ${(retPct(c, i) / 100) * 0.55})` })

// ---- activity matrix: who was here on which day ----
const activity = ref(null)
const actWindow = ref(30)
const actSearch = ref('')
const actPage = ref(1)
const actLoading = ref(false)

const activityParams = () => ({
  days: actWindow.value,
  search: actSearch.value || undefined,
  page: actPage.value
})

async function loadActivity() {
  actLoading.value = true
  try {
    const { data } = await api.get('/admin/activity', { params: activityParams() })
    activity.value = data
  } finally {
    actLoading.value = false
  }
}

function setWindow(d) {
  actWindow.value = d
  actPage.value = 1
  loadActivity()
}

function searchActivity() {
  actPage.value = 1
  loadActivity()
}

function actGoPage(p) {
  actPage.value = p
  loadActivity()
}

// GitHub-graph intensity: 0 quiet, then light → strong by volume.
function cellLevel([reviews, chat]) {
  const n = reviews + chat
  if (!n) return 0
  if (n < 10) return 1
  if (n < 30) return 2
  return 3
}

function cellTitle(u, i) {
  const date = activity.value.dates[i]
  const [r, c] = u.cells[i]
  const what = r + c ? `${r} reviews · ${c} chat msgs` : 'inactive'
  return `${fmtDay(date)} · ${what}${date === u.created_at ? ' · joined 🌱' : ''}`
}

// Days since last activity, measured against the window's "today".
function daysSinceActive(u) {
  // last_activity_date, not last_active_date: the latter is the streak anchor
  // and only gets written when a session is *completed*, so someone who did a
  // dozen reviews and closed the tab was showing up as "never active" next to
  // a grid full of their own reviews.
  if (!u.last_activity_date) return Infinity
  const today = activity.value?.dates.at(-1)
  return Math.max(0, Math.round((new Date(today) - new Date(u.last_activity_date)) / 86400000))
}

// The verdict chip: stayed, fading, or gone - the reason this tab exists.
function status(u) {
  const gap = daysSinceActive(u)
  if (gap <= 1) return { cls: 'on', label: '🔥 active' }
  if (gap <= 3) return { cls: 'ok', label: '✅ recent' }
  if (gap <= 13) return { cls: 'fade', label: `💤 quiet ${gap}d` }
  if (gap === Infinity) return { cls: 'gone', label: '👻 never active' }
  return { cls: 'gone', label: `👻 gone ${gap}d` }
}

const isNew = (u) => {
  const dates = activity.value?.dates ?? []
  return u.created_at >= (dates[Math.max(0, dates.length - 7)] ?? '')
}

// ---- content health ----
const maxLevelSentences = computed(() =>
  Math.max(1, ...(stats.value?.levels ?? []).map((l) => Number(l.sentences) || 0))
)

// ---- pending recordings review: listen, then approve (live) or reject ----
const recordings = ref(null)
const recBusy = ref(false)

const pendingItems = () => [
  ...(recordings.value?.sentences ?? []).map((s) => ({ ...s, type: 'sentence', key: String(s.id), label: s.finnish_text })),
  ...(recordings.value?.words ?? []).map((w) => ({ ...w, type: 'word', key: w.word, label: w.word })),
  // Kuulo pair words review separately from course words: the reviewer is
  // listening for one thing - is the vowel contrast actually audible?
  ...(recordings.value?.pairs ?? []).map((p) => ({
    ...p, type: 'pair', key: p.word, label: p.word, english_text: 'Kuulo drill'
  })),
  // Taivutus phrases review as sentences - same job, one line read out loud.
  ...(recordings.value?.phrases ?? []).map((p) => ({
    ...p, type: 'phrase', key: p.base, label: p.text, english_text: 'Taivutus drill'
  })),
  // A conversation take is only reviewable against its character: the take
  // has to sound like the person it belongs to, and two lines from opposite
  // speakers arriving in the same voice is the thing to catch here.
  ...(recordings.value?.listening ?? []).map((l) => ({
    ...l,
    type: 'listening',
    key: `${l.scene}:${l.index}`,
    label: l.fi,
    english_text: `${l.speaker} (${l.voice}) · ${l.scene_title}`
  }))
]

// The "sentences" section owns phrases too: to a reviewer they're the same
// thing (one Finnish line, read aloud), so they don't earn their own row.
const inSection = (item, type) => (type === 'sentence' ? item.type === 'sentence' || item.type === 'phrase' : item.type === type)

const pendingOf = (type) => pendingItems().filter((i) => inSection(i, type))

// The recordings manager lives inside the content-health card: each
// "Human-voiced …" row expands into its review + live lists.
const openAudio = ref(null) // 'sentence' | 'word' | 'listening' | null
function toggleAudio(type) {
  openAudio.value = openAudio.value === type ? null : type
  liveQ.value = ''
}

const AUDIO_SECTIONS = [
  { type: 'sentence', label: 'Human-voiced sentences' },
  { type: 'word', label: 'Human-voiced words' },
  { type: 'pair', label: 'Human-voiced Kuulo words' },
  { type: 'listening', label: 'Human-voiced conversations' }
]

function audioNums(type) {
  const a = stats.value?.audio
  if (!a) return { done: 0, total: 0, pct: 0 }
  if (type === 'listening') {
    // Conversation lines aren't in the sentences table, so their counts come
    // from the recordings payload rather than the audio stats.
    const done = recordings.value?.live_listening?.length ?? 0
    const total = a.listening_total ?? 0
    return { done, total, pct: total ? Math.round((done / total) * 100) : 0 }
  }
  if (type === 'pair') {
    const done = a.pairs_human ?? 0
    const total = a.pairs_total ?? 0
    return { done, total, pct: total ? Math.round((done / total) * 100) : 0 }
  }
  // Taivutus phrases count with the sentences - they share the section.
  const done = type === 'sentence' ? a.sentences_human + (a.phrases_human ?? 0) : a.words_human
  const total = type === 'sentence' ? a.sentences_total + (a.phrases_total ?? 0) : a.words_total
  return { done, total, pct: total ? Math.round((done / total) * 100) : 0 }
}

// ElevenLabs coverage sits between robot and human: it never fills the bar
// above (that tracks human takes), it just says how much of the rest has the
// better synthetic voice.
function elevenCount(type) {
  const a = stats.value?.audio
  if (!a) return 0
  if (type === 'sentence') return a.sentences_eleven ?? 0
  if (type === 'word') return a.words_eleven ?? 0
  if (type === 'pair') return a.pairs_eleven ?? 0
  return 0
}

// New takes can arrive while this tab sits open - refetch on demand.
const recRefreshing = ref(false)
async function refreshRecordings() {
  recRefreshing.value = true
  try {
    const { data } = await api.get('/admin/recordings')
    recordings.value = data
  } finally {
    recRefreshing.value = false
  }
}

async function review(item, action) {
  recBusy.value = true
  try {
    await api.post(`/admin/recordings/${action}`, { type: item.type, key: item.key })
    if (action === 'approve') {
      // The take moved from pending to live - refetch for the fresh picture.
      const { data } = await api.get('/admin/recordings')
      recordings.value = data
    } else if (item.type === 'listening') {
      const list = recordings.value.listening
      const idx = list.findIndex((x) => x.scene === item.scene && x.index === item.index)
      if (idx !== -1) list.splice(idx, 1)
    } else if (item.type === 'phrase') {
      const idx = recordings.value.phrases.findIndex((x) => x.base === item.base)
      if (idx !== -1) recordings.value.phrases.splice(idx, 1)
    } else if (item.type === 'pair') {
      const idx = recordings.value.pairs.findIndex((x) => x.word === item.word)
      if (idx !== -1) recordings.value.pairs.splice(idx, 1)
    } else {
      const list = item.type === 'sentence' ? recordings.value.sentences : recordings.value.words
      const idx = list.findIndex((x) => (item.type === 'sentence' ? x.id === item.id : x.word === item.word))
      if (idx !== -1) list.splice(idx, 1)
    }
  } finally {
    recBusy.value = false
  }
}

async function approveAllType(type) {
  recBusy.value = true
  try {
    for (const item of pendingOf(type)) {
      await api.post('/admin/recordings/approve', { type: item.type, key: item.key })
    }
    await refreshRecordings()
  } finally {
    recBusy.value = false
  }
}

// ---- live human recordings: everything approved, removable back to TTS ----
const liveQ = ref('')
const LIVE_MAX = 50

const liveItems = computed(() => {
  const q = liveQ.value.trim().toLowerCase()
  const hit = (t) => !q || t.toLowerCase().includes(q)
  return [
    ...(recordings.value?.live_sentences ?? []).filter((s) => hit(s.finnish_text)).map((s) => ({
      type: 'sentence', keyId: `s-${s.id}`, id: s.id, label: s.finnish_text, note: s.english_text, url: s.audio_url
    })),
    ...(recordings.value?.live_words ?? []).filter((w) => hit(w.word)).map((w) => ({
      type: 'word', keyId: `w-${w.word}`, word: w.word, label: w.word, note: '', url: w.audio_url
    })),
    ...(recordings.value?.live_listening ?? [])
      .filter((l) => hit(l.fi) || hit(l.speaker) || hit(l.scene_title))
      .map((l) => ({
        type: 'listening', keyId: `l-${l.scene}-${l.index}`, scene: l.scene, index: l.index,
        label: l.fi, note: `${l.speaker} (${l.voice}) · ${l.scene_title}`, url: l.audio_url
      })),
    ...(recordings.value?.live_phrases ?? []).filter((p) => hit(p.text)).map((p) => ({
      type: 'phrase', keyId: `p-${p.base}`, base: p.base,
      label: p.text, note: 'Taivutus drill', url: p.audio_url
    })),
    ...(recordings.value?.live_pairs ?? []).filter((p) => hit(p.word)).map((p) => ({
      type: 'pair', keyId: `pr-${p.word}`, word: p.word, label: p.word, note: 'Kuulo drill', url: p.audio_url
    }))
  ]
})

const liveOf = (type) => liveItems.value.filter((i) => inSection(i, type))

async function removeLive(item) {
  recBusy.value = true
  try {
    if (item.type === 'sentence') {
      await api.delete(`/record/sentence/${item.id}`)
      const idx = recordings.value.live_sentences.findIndex((s) => s.id === item.id)
      if (idx !== -1) recordings.value.live_sentences.splice(idx, 1)
    } else if (item.type === 'listening') {
      await api.delete(`/record/listening/${item.scene}/${item.index}`)
      const idx = recordings.value.live_listening.findIndex((l) => l.scene === item.scene && l.index === item.index)
      if (idx !== -1) recordings.value.live_listening.splice(idx, 1)
    } else if (item.type === 'phrase') {
      await api.delete(`/record/phrase/${item.base}`)
      const idx = recordings.value.live_phrases.findIndex((p) => p.base === item.base)
      if (idx !== -1) recordings.value.live_phrases.splice(idx, 1)
    } else if (item.type === 'pair') {
      await api.delete('/record/pair', { params: { word: item.word } })
      const idx = recordings.value.live_pairs.findIndex((p) => p.word === item.word)
      if (idx !== -1) recordings.value.live_pairs.splice(idx, 1)
    } else {
      await api.delete('/record/word', { params: { word: item.word } })
      const idx = recordings.value.live_words.findIndex((w) => w.word === item.word)
      if (idx !== -1) recordings.value.live_words.splice(idx, 1)
    }
  } finally {
    recBusy.value = false
  }
}

// ---- feedback inbox ----
const feedback = ref(null)
const fbBusy = ref({}) // feedback id → deleting

async function loadFeedback(p = 1) {
  const { data } = await api.get('/admin/feedback', { params: { page: p } })
  feedback.value = data
}

async function clearFeedback(item) {
  fbBusy.value[item.id] = true
  try {
    await api.delete(`/admin/feedback/${item.id}`)
    const idx = feedback.value.data.findIndex((f) => f.id === item.id)
    if (idx !== -1) feedback.value.data.splice(idx, 1)
    feedback.value.total = Math.max(0, (feedback.value.total ?? 1) - 1)
  } finally {
    fbBusy.value[item.id] = false
  }
}

const fmtWhen = (d) => new Date(d).toLocaleString('en', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })

// ---- users ----
async function loadUsers() {
  const { data } = await api.get('/admin/users', { params: { page: page.value, search: search.value || undefined } })
  users.value = data
}

async function searchUsers() {
  page.value = 1
  await loadUsers()
}

async function goPage(p) {
  page.value = p
  await loadUsers()
}

async function togglePremium(u) {
  busy.value[u.id] = true
  try {
    const { data } = await api.post(`/admin/users/${u.id}/premium`)
    u.premium_until = data.premium_until
    u.is_premium = !!data.premium_until
  } finally {
    busy.value[u.id] = false
  }
}

async function toggleRecorder(u) {
  busy.value[u.id] = true
  try {
    const { data } = await api.post(`/admin/users/${u.id}/recorder`)
    u.is_recorder = data.is_recorder
  } finally {
    busy.value[u.id] = false
  }
}

// One-way: for learners whose verification mail never arrived. Unblocks
// Löyly+ checkout, which requires a confirmed inbox.
async function verifyEmail(u) {
  busy.value[u.id] = true
  try {
    const { data } = await api.post(`/admin/users/${u.id}/verify-email`)
    u.email_verified_at = data.email_verified_at
  } finally {
    busy.value[u.id] = false
  }
}

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString() : '-')
</script>

<template>
  <div>
    <div v-if="loading && !stats" class="spinner"></div>

    <div v-else-if="denied" class="denied">
      <div class="denied-icon">🚫</div>
      <h2>Admins only</h2>
      <p class="muted">Promote an account with <code>php artisan user:promote &lt;email&gt;</code>.</p>
      <router-link to="/dashboard" class="btn btn-ghost">Back</router-link>
    </div>

    <div v-else class="admin">
      <h2>🛠 Admin</h2>

      <div class="tabbar" role="tablist">
        <button
          v-for="t in TABS"
          :key="t.id"
          class="tab"
          :class="{ active: tab === t.id }"
          role="tab"
          :aria-selected="tab === t.id ? 'true' : 'false'"
          @click="tab = t.id"
        >
          {{ t.label }}
          <span v-if="t.id === 'feedback' && feedback?.total" class="tab-badge">{{ feedback.total }}</span>
        </button>
      </div>

      <!-- ============================== PULSE ============================== -->
      <template v-if="tab === 'pulse'">
        <template v-if="stats">
          <div class="export-row">
            <button class="btn btn-ghost btn-sm" :disabled="exporting" @click="downloadExport">
              {{ exporting === 'error' ? 'Export failed - retry' : exporting ? 'Preparing…' : 'Download data snapshot (JSON)' }}
            </button>
            <span class="export-hint">Everything on this panel, unpaginated. No names or emails.</span>
          </div>

          <h3 class="group-label">Learners</h3>
          <div class="stat-grid five">
            <div class="card stat"><span class="v">{{ stats.users_total }}</span><span class="l">users</span></div>
            <div class="card stat"><span class="v">{{ stats.users_new_7d }}</span><span class="l">new · 7d</span></div>
            <div class="card stat"><span class="v">{{ stats.users_active_today }}</span><span class="l">active today</span></div>
            <div class="card stat"><span class="v">{{ stats.users_active_7d }}</span><span class="l">active · 7d</span></div>
            <div class="card stat"><span class="v">{{ stats.users_active_30d }}</span><span class="l">active · 30d</span></div>
          </div>

          <h3 class="group-label">Engagement &amp; business</h3>
          <div class="stat-grid">
            <div class="card stat"><span class="v">{{ stats.reviews_today }}</span><span class="l">reviews today</span></div>
            <div class="card stat"><span class="v">{{ stats.reviews_7d }}</span><span class="l">reviews · 7d</span></div>
            <div class="card stat"><span class="v">{{ stats.sentences_mastered_total }}</span><span class="l">mastered</span></div>
            <!-- Paying is the headline: comps and trials are not revenue. -->
            <div class="card stat accent">
              <span class="v">{{ stats.premium_paying }}</span>
              <span class="l">Löyly+ paying</span>
              <span class="sub">{{ stats.premium_trialing }} trial · {{ stats.premium_comped }} comped</span>
            </div>
          </div>
        </template>

        <template v-if="trends">
          <h3 class="group-label">Last 30 days</h3>
          <div class="trend-grid">
            <div v-for="s in STRIPS" :key="s.key" class="card trend">
              <div class="trend-head">
                <span class="trend-title">{{ s.label }}</span>
                <span class="trend-total muted">{{ strip(s.key).total }} total</span>
              </div>
              <div class="trend-bars">
                <div
                  v-for="b in strip(s.key).bars"
                  :key="b.date"
                  class="tbar"
                  :class="{ empty: !b.value }"
                  role="img"
                  :aria-label="`${fmtDay(b.date)}: ${b.value} ${s.label.toLowerCase()}`"
                  :title="`${fmtDay(b.date)} · ${b.value}`"
                >
                  <div class="tbar-fill" :style="{ height: b.pct + '%' }"></div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- who stayed: weekly signup cohorts × weeks since signup -->
        <template v-if="retention">
          <h3 class="group-label">Retention - who stayed</h3>
          <div class="card ret-card">
            <p v-if="!retention.cohorts.length" class="muted rec-empty">No signups in the last 8 weeks yet.</p>
            <div v-else class="ret-scroll">
              <table class="ret">
                <thead>
                  <tr>
                    <th class="ret-week-h">Joined week of</th>
                    <th>Size</th>
                    <th v-for="i in maxOffsets" :key="i">W{{ i - 1 }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in retention.cohorts" :key="c.week">
                    <td class="ret-week">{{ fmtDay(c.week) }}</td>
                    <td class="ret-size">{{ c.size }}</td>
                    <td v-for="i in maxOffsets" :key="i" class="ret-cell">
                      <span v-if="i - 1 < c.active.length" class="ret-pct" :style="retStyle(c, i - 1)">
                        {{ retPct(c, i - 1) }}%
                      </span>
                      <span v-else class="muted">·</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="muted ret-note">
              W0 = signup week, W1 = the week after, and so on. A cell = the share of that
              week's signups who reviewed or chatted during that week. W1 is the number to
              watch: it's the "came back after trying it" rate.
            </p>
          </div>
        </template>
      </template>

      <!-- ============================ ACTIVITY ============================ -->
      <template v-else-if="tab === 'activity'">
        <div class="act-head">
          <h3 class="group-label users-label">Who was here, day by day</h3>
          <div class="act-tools">
            <div class="win-toggle" role="group" aria-label="Window">
              <button class="win-btn" :class="{ active: actWindow === 14 }" @click="setWindow(14)">14d</button>
              <button class="win-btn" :class="{ active: actWindow === 30 }" @click="setWindow(30)">30d</button>
            </div>
            <input
              v-model="actSearch"
              class="search"
              type="search"
              placeholder="Search name or email…"
              @keyup.enter="searchActivity"
            />
          </div>
        </div>

        <div v-if="activity" class="card mx-card" :class="{ dim: actLoading }">
          <div class="mx-legend muted">
            <span>Each cell is a day: reviews + chat messages.</span>
            <span class="mx-scale">
              quiet
              <i class="mx-cell lvl0"></i><i class="mx-cell lvl1"></i><i class="mx-cell lvl2"></i><i class="mx-cell lvl3"></i>
              busy · <i class="mx-cell lvl0 joined"></i> = joined that day
            </span>
          </div>

          <div class="mx-scroll">
            <div class="mx-table">
              <div class="mx-row mx-headrow" aria-hidden="true">
                <span class="mx-name"></span>
                <div class="mx-cells">
                  <span v-for="(d, i) in activity.dates" :key="d" class="mx-daylab">
                    {{ i % 5 === 0 ? Number(d.slice(8)) : '' }}
                  </span>
                </div>
                <span class="mx-meta-h">days · verdict</span>
              </div>

              <div v-for="u in activity.users" :key="u.id" class="mx-row">
                <div class="mx-name">
                  <p class="mx-n">
                    {{ u.name }}
                    <span v-if="u.is_premium" class="tag premium-tag">+</span>
                    <span v-if="isNew(u)" class="tag new-tag">new</span>
                  </p>
                  <p class="mx-e muted">{{ u.email }}</p>
                </div>
                <div class="mx-cells">
                  <span
                    v-for="(c, i) in u.cells"
                    :key="i"
                    class="mx-cell"
                    :class="['lvl' + cellLevel(c), { joined: activity.dates[i] === u.created_at }]"
                    :title="cellTitle(u, i)"
                  ></span>
                </div>
                <div class="mx-meta">
                  <span class="mx-days" :title="`Active on ${u.active_days} of the last ${activity.dates.length} days`">
                    {{ u.active_days }}/{{ activity.dates.length }}
                  </span>
                  <span class="status" :class="status(u).cls">{{ status(u).label }}</span>
                </div>
              </div>
            </div>
          </div>

          <p v-if="!activity.users.length" class="rec-empty muted">
            {{ actSearch ? `Nobody matches "${actSearch}".` : 'No users yet.' }}
          </p>

          <div v-if="activity.last_page > 1" class="pager">
            <button class="btn btn-ghost" :disabled="activity.current_page <= 1 || actLoading" @click="actGoPage(activity.current_page - 1)">‹ Prev</button>
            <span class="muted">{{ activity.current_page }} / {{ activity.last_page }} · {{ activity.total }} users</span>
            <button class="btn btn-ghost" :disabled="activity.current_page >= activity.last_page || actLoading" @click="actGoPage(activity.current_page + 1)">Next ›</button>
          </div>
        </div>
      </template>

      <!-- ============================= CONTENT ============================= -->
      <template v-else-if="tab === 'content'">
        <template v-if="stats">
          <h3 class="group-label">Content health</h3>
          <div class="card content-card">
            <div class="level-rows">
              <div v-for="l in stats.levels" :key="l.level" class="level-row">
                <span class="level-code">{{ l.level }}</span>
                <span class="level-meta muted">{{ l.lessons }} lessons · {{ l.sentences }} sentences</span>
                <div class="level-track">
                  <div class="level-fill" :style="{ width: Math.round((l.sentences / maxLevelSentences) * 100) + '%' }"></div>
                </div>
              </div>
            </div>

            <div class="audio-block">
              <template v-for="sec in AUDIO_SECTIONS" :key="sec.type">
                <button class="audio-row" :aria-expanded="openAudio === sec.type ? 'true' : 'false'" @click="toggleAudio(sec.type)">
                  <span class="audio-label">🎙 {{ sec.label }}</span>
                  <span v-if="pendingOf(sec.type).length" class="rev-badge">{{ pendingOf(sec.type).length }} to review</span>
                  <span v-if="elevenCount(sec.type)" class="eleven-badge" title="Voiced by ElevenLabs - still robot, still worth a human take">
                    {{ elevenCount(sec.type) }} 11L
                  </span>
                  <span class="audio-nums muted">{{ audioNums(sec.type).done }}/{{ audioNums(sec.type).total }} · {{ audioNums(sec.type).pct }}%</span>
                  <svg class="audio-chev" :class="{ open: openAudio === sec.type }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 9l6 6 6-6" />
                  </svg>
                </button>
                <div class="progress-track slim"><div class="progress-fill green" :style="{ width: audioNums(sec.type).pct + '%' }"></div></div>

                <!-- expanded: review what's pending, manage what's live -->
                <div v-if="openAudio === sec.type" class="audio-panel">
                  <div class="rec-tools">
                    <button class="rec-btn" :disabled="recRefreshing" title="Check for newly submitted takes" @click="refreshRecordings">
                      {{ recRefreshing ? '↻ Checking…' : '↻ Refresh' }}
                    </button>
                    <button v-if="pendingOf(sec.type).length" class="rec-btn ok" :disabled="recBusy" @click="approveAllType(sec.type)">
                      ✓ Approve all ({{ pendingOf(sec.type).length }})
                    </button>
                    <input
                      v-if="liveOf(sec.type).length > 5 || liveQ"
                      v-model="liveQ"
                      class="search live-search"
                      type="search"
                      placeholder="Search…"
                      aria-label="Search recordings"
                    />
                  </div>

                  <p v-if="!pendingOf(sec.type).length && !liveOf(sec.type).length" class="rec-empty muted">
                    {{ liveQ ? `Nothing matches "${liveQ}".` : 'Nothing here yet - takes appear the moment your recorder keeps one.' }}
                  </p>

                  <template v-if="pendingOf(sec.type).length">
                    <p class="panel-sub">⏳ Awaiting review</p>
                    <div v-for="item in pendingOf(sec.type)" :key="item.type + item.key" class="rec-row pending">
                      <div class="rec-main">
                        <p class="rec-text">{{ item.label }}</p>
                        <!-- For a conversation take this line names the speaker
                             and their voice - the thing being reviewed. -->
                        <p v-if="item.english_text" class="rec-en muted">{{ item.english_text }}</p>
                      </div>
                      <div class="rec-actions">
                        <button class="rec-btn play" title="Play the new human take" @click="playClip(item.pending_url)">▶ New take</button>
                        <button class="rec-btn" title="Play the current version for comparison" :disabled="!item.current_url" @click="playClip(item.current_url)">🤖 Current</button>
                        <button class="rec-btn ok" :disabled="recBusy" title="Approve - goes live" @click="review(item, 'approve')">✓ Approve</button>
                        <button class="rec-btn no" :disabled="recBusy" title="Reject - back to the recorder's queue" @click="review(item, 'reject')">✗</button>
                      </div>
                    </div>
                  </template>

                  <template v-if="liveOf(sec.type).length">
                    <p class="panel-sub">✅ Live in the app <span class="muted">{{ liveOf(sec.type).length }}</span></p>
                    <div v-for="item in liveOf(sec.type).slice(0, LIVE_MAX)" :key="item.keyId" class="rec-row">
                      <div class="rec-main">
                        <p class="rec-text">{{ item.label }}</p>
                        <p v-if="item.note" class="rec-en muted">{{ item.note }}</p>
                      </div>
                      <div class="rec-actions">
                        <button class="rec-btn play" title="Play the live recording" @click="playClip(item.url)">▶ Play</button>
                        <button class="rec-btn no" :disabled="recBusy" title="Remove - the app falls back to the TTS voice" @click="removeLive(item)">✗ Remove</button>
                      </div>
                    </div>
                    <p v-if="liveOf(sec.type).length > LIVE_MAX" class="muted live-more">
                      +{{ liveOf(sec.type).length - LIVE_MAX }} more - search to narrow down.
                    </p>
                  </template>
                </div>
              </template>
            </div>
          </div>
        </template>
      </template>

      <!-- ============================== USERS ============================== -->
      <template v-else-if="tab === 'users'">
        <div class="users-head">
          <h3 class="group-label users-label">Users</h3>
          <input
            v-model="search"
            class="search"
            type="search"
            placeholder="Search name or email…"
            @keyup.enter="searchUsers"
          />
        </div>

        <div v-if="users" class="user-list">
          <div v-for="u in users.data" :key="u.id" class="card user-row">
            <div class="u-main">
              <p class="u-name">
                {{ u.name }}
                <span v-if="u.is_admin" class="tag admin-tag">admin</span>
                <span v-if="u.is_recorder" class="tag recorder-tag">recorder</span>
                <span v-if="u.is_premium" class="tag premium-tag">Löyly+</span>
                <span v-if="!u.email_verified_at" class="tag unverified-tag">unverified</span>
                <!-- Defaults on, so this only shows when someone opted out. -->
                <span v-if="!u.review_emails" class="tag nomail-tag">no email</span>
              </p>
              <p class="u-email muted">{{ u.email }}</p>
            </div>
            <div class="u-stats muted">
              <span>⚡{{ u.xp }}</span>
              <span>🔥{{ u.streak }}</span>
              <span title="Last active">📅 {{ fmtDate(u.last_active_date) }}</span>
              <span title="Joined">🌱 {{ fmtDate(u.created_at) }}</span>
            </div>
            <div class="u-actions">
              <button class="comp" :disabled="busy[u.id]" @click="togglePremium(u)">
                {{ u.is_premium ? 'Revoke +' : 'Comp 30d' }}
              </button>
              <button class="comp" :disabled="busy[u.id]" :title="u.is_recorder ? 'Revoke recording rights' : 'Grant recording-studio access'" @click="toggleRecorder(u)">
                {{ u.is_recorder ? '🎙 Revoke' : '🎙 Grant' }}
              </button>
              <button
                v-if="!u.email_verified_at"
                class="comp"
                :disabled="busy[u.id]"
                title="Mark the email as confirmed - use when the verification mail never arrived. Unblocks Löyly+ checkout."
                @click="verifyEmail(u)"
              >
                ✉️ Confirm
              </button>
            </div>
          </div>

          <div v-if="users.last_page > 1" class="pager">
            <button class="btn btn-ghost" :disabled="users.current_page <= 1" @click="goPage(users.current_page - 1)">‹ Prev</button>
            <span class="muted">{{ users.current_page }} / {{ users.last_page }}</span>
            <button class="btn btn-ghost" :disabled="users.current_page >= users.last_page" @click="goPage(users.current_page + 1)">Next ›</button>
          </div>
        </div>
      </template>

      <!-- ============================= FEEDBACK ============================= -->
      <template v-else-if="tab === 'feedback'">
        <h3 class="group-label">What learners wrote</h3>

        <div v-if="feedback" class="user-list">
          <p v-if="!feedback.data.length" class="rec-empty muted">
            Inbox zero - feedback appears here the moment someone uses the dashboard box.
          </p>

          <div v-for="f in feedback.data" :key="f.id" class="card fb-row">
            <div class="fb-main">
              <p class="fb-who">
                {{ f.user?.name ?? 'deleted user' }}
                <span class="muted fb-mail">{{ f.user?.email }}</span>
                <span class="muted fb-when">{{ fmtWhen(f.created_at) }}</span>
              </p>
              <p class="fb-msg">{{ f.message }}</p>
            </div>
            <button class="comp" :disabled="fbBusy[f.id]" title="Handled - remove from the inbox" @click="clearFeedback(f)">
              ✓ Clear
            </button>
          </div>

          <div v-if="feedback.last_page > 1" class="pager">
            <button class="btn btn-ghost" :disabled="feedback.current_page <= 1" @click="loadFeedback(feedback.current_page - 1)">‹ Prev</button>
            <span class="muted">{{ feedback.current_page }} / {{ feedback.last_page }}</span>
            <button class="btn btn-ghost" :disabled="feedback.current_page >= feedback.last_page" @click="loadFeedback(feedback.current_page + 1)">Next ›</button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.admin h2 { font-size: 24px; margin-bottom: 12px; }

/* ---- tab bar ---- */
.tabbar {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 8px;
  overflow-x: auto;
}
.tab {
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 700;
  padding: 9px 12px;
  cursor: pointer;
  white-space: nowrap;
}
.tab:hover { color: var(--text); }
.tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.tab-badge {
  font-size: 10px;
  font-weight: 800;
  background: var(--accent-soft);
  color: var(--accent);
  border-radius: 99px;
  padding: 1px 7px;
  margin-left: 3px;
}

/* ---- feedback inbox ---- */
.fb-row { display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; }
.fb-main { flex: 1; min-width: 0; }
.fb-who { font-size: 13px; font-weight: 700; display: flex; gap: 8px; flex-wrap: wrap; align-items: baseline; }
.fb-mail, .fb-when { font-size: 11.5px; font-weight: 600; }
.fb-msg { font-size: 14px; line-height: 1.5; margin-top: 5px; white-space: pre-wrap; overflow-wrap: break-word; }

.group-label {
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin: 20px 0 8px;
}

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.stat-grid.five { grid-template-columns: repeat(5, 1fr); }
@media (max-width: 560px) {
  .stat-grid, .stat-grid.five { grid-template-columns: repeat(3, 1fr); }
}
.stat { display: flex; flex-direction: column; align-items: center; padding: 12px 6px; }
.stat .v { font-size: 20px; font-weight: 800; }
.stat .l { font-size: 10px; color: var(--text-dim); text-align: center; }
.stat.accent .v { color: var(--accent); }
.stat .sub { font-size: 9px; color: var(--text-dim); text-align: center; margin-top: 2px; opacity: .75; }

/* ---- data snapshot export ---- */
.export-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.export-hint { font-size: 11px; color: var(--text-dim); }

/* ---- 30-day trend strips ---- */
.trend-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
@media (min-width: 640px) { .trend-grid { grid-template-columns: repeat(3, 1fr); } }
.trend { padding: 12px 14px; }
.trend-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px; }
.trend-title { font-size: 13px; font-weight: 800; }
.trend-total { font-size: 11px; }
.trend-bars { display: flex; align-items: flex-end; gap: 2px; height: 46px; }
.tbar {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: flex-end;
  min-width: 0;
  border-radius: 2px;
}
.tbar:hover .tbar-fill { background: var(--accent-2); }
.tbar-fill {
  width: 100%;
  min-height: 2px;
  border-radius: 2px 2px 0 0;
  background: var(--accent);
}
.tbar.empty .tbar-fill { background: var(--border); }

/* ---- retention cohorts ---- */
.ret-card { padding: 14px 16px; }
.ret-scroll { overflow-x: auto; }
.ret { border-collapse: collapse; width: 100%; min-width: 520px; }
.ret th {
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-dim);
  text-align: center;
  padding: 4px 6px;
}
.ret-week-h { text-align: left !important; }
.ret td { padding: 3px 4px; text-align: center; }
.ret-week { font-size: 12.5px; font-weight: 700; white-space: nowrap; text-align: left !important; }
.ret-size { font-size: 12.5px; color: var(--text-dim); }
.ret-pct {
  display: block;
  min-width: 44px;
  font-size: 12px;
  font-weight: 700;
  border-radius: 6px;
  padding: 5px 4px;
}
.ret-note { font-size: 12px; line-height: 1.5; margin-top: 10px; }

/* ---- activity matrix ---- */
.act-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.act-tools { display: flex; gap: 8px; align-items: center; flex: 1; justify-content: flex-end; }
.win-toggle { display: flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.win-btn {
  background: none;
  border: none;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 700;
  padding: 8px 12px;
  cursor: pointer;
}
.win-btn.active { background: var(--accent-soft); color: var(--accent); }

.mx-card { padding: 14px 16px; margin-top: 8px; }
.mx-card.dim { opacity: 0.55; pointer-events: none; }
.mx-legend {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  font-size: 11.5px;
  margin-bottom: 10px;
}
.mx-scale { display: inline-flex; align-items: center; gap: 3px; }
.mx-scale .mx-cell { width: 11px; height: 11px; flex: none; }

.mx-scroll { overflow-x: auto; }
.mx-table { min-width: 620px; }
.mx-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 0;
  border-top: 1px solid var(--border);
}
.mx-headrow { border-top: none; padding: 0 0 2px; }
.mx-name { width: 168px; flex: none; min-width: 0; }
.mx-n { font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mx-e { font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mx-cells { display: flex; gap: 2px; flex: 1; min-width: 0; }
.mx-daylab { flex: 1; min-width: 6px; font-size: 9px; color: var(--text-faint); text-align: left; }
.mx-cell {
  flex: 1;
  min-width: 6px;
  height: 18px;
  border-radius: 3px;
  display: inline-block;
}
.mx-cell.lvl0 { background: var(--bg-soft); }
.mx-cell.lvl1 { background: rgba(16, 185, 129, 0.35); }
.mx-cell.lvl2 { background: rgba(16, 185, 129, 0.62); }
.mx-cell.lvl3 { background: rgba(16, 185, 129, 0.95); }
.mx-cell.joined { box-shadow: inset 0 0 0 1.5px var(--accent); }
.mx-meta { width: 138px; flex: none; display: flex; align-items: center; gap: 7px; justify-content: flex-end; }
.mx-meta-h { width: 138px; flex: none; font-size: 9.5px; color: var(--text-faint); text-align: right; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
.mx-days { font-size: 11.5px; font-weight: 700; color: var(--text-dim); white-space: nowrap; }
.status {
  font-size: 10.5px;
  font-weight: 800;
  border-radius: 99px;
  padding: 3px 8px;
  white-space: nowrap;
}
.status.on { background: var(--green-soft); color: var(--green); }
.status.ok { background: var(--accent-soft); color: var(--accent); }
.status.fade { background: var(--bg-soft); color: var(--text-dim); }
.status.gone { background: var(--red-soft, rgba(239, 68, 68, 0.12)); color: var(--red); }
.new-tag { background: var(--green-soft); color: var(--green); }

/* ---- content health ---- */
.content-card { padding: 14px 16px; }
.level-rows { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.level-row { display: grid; grid-template-columns: 34px 1fr; grid-template-rows: auto auto; column-gap: 10px; row-gap: 3px; align-items: center; }
.level-code {
  grid-row: span 2;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 800;
  color: var(--accent);
  background: var(--accent-soft);
}
.level-meta { font-size: 12px; }
.level-track { height: 5px; background: var(--bg-soft); border-radius: 99px; overflow: hidden; }
.level-fill { height: 100%; border-radius: 99px; background: var(--accent); }

.audio-block { border-top: 1px solid var(--border); padding-top: 14px; display: flex; flex-direction: column; gap: 6px; }
.audio-row {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  background: none;
  border: none;
  padding: 4px 0;
  cursor: pointer;
  color: var(--text);
  font-family: inherit;
  text-align: left;
}
.audio-row:hover .audio-label { color: var(--accent); }
.audio-label { font-size: 13px; font-weight: 700; flex: 1; }
.rev-badge {
  font-size: 10.5px;
  font-weight: 800;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: 99px;
  padding: 2px 9px;
  white-space: nowrap;
}
.audio-nums { font-size: 12px; white-space: nowrap; }
.audio-chev { width: 14px; height: 14px; color: var(--text-faint); transition: transform 0.2s ease; flex-shrink: 0; }
.audio-chev.open { transform: rotate(180deg); }
.progress-track.slim { height: 6px; margin-bottom: 8px; }
.progress-fill.green { background: var(--green); }

.eleven-badge {
  flex-shrink: 0;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.03em;
  color: #a78bfa;
  background: color-mix(in srgb, #a78bfa 16%, transparent);
  border-radius: var(--radius-pill);
  padding: 2px 8px;
}

/* ---- expanded recordings panel ---- */
.audio-panel {
  background: var(--bg-soft);
  border-radius: var(--radius-sm);
  padding: 12px;
  margin-bottom: 10px;
}
.rec-tools { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.rec-empty { font-size: 13px; text-align: center; line-height: 1.5; padding: 8px 0; }
.panel-sub {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin: 10px 0 6px;
  display: flex;
  gap: 6px;
  align-items: baseline;
}
.rec-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 11px;
  flex-wrap: wrap;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  margin-bottom: 6px;
}
.rec-row.pending { border-color: var(--accent); border-style: dashed; }
.rec-main { flex: 1; min-width: 150px; }
.rec-text { font-weight: 700; font-size: 14px; }
.rec-en { font-size: 12px; margin-top: 1px; }
.rec-actions { display: flex; gap: 5px; flex-wrap: wrap; }
.rec-btn {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  padding: 6px 9px;
  cursor: pointer;
  white-space: nowrap;
}
.rec-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.rec-btn:disabled { opacity: 0.5; cursor: default; }
.rec-btn.ok:hover:not(:disabled) { border-color: var(--green); color: var(--green); }
.rec-btn.no:hover:not(:disabled) { border-color: var(--red); color: var(--red); }
.rec-btn.play { color: var(--accent); background: var(--accent-soft); border-color: transparent; }
.live-search { max-width: 170px; margin-left: auto; padding: 7px 10px; font-size: 13px; }
.live-more { font-size: 12px; text-align: center; margin-top: 4px; }


/* ---- users ---- */
.users-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 4px; }
.users-label { margin-bottom: 0; }
.search {
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-family: inherit;
  font-size: 14px;
  padding: 9px 12px;
  outline: none;
  min-width: 0;
  flex: 1;
  max-width: 260px;
}
.search:focus { border-color: var(--accent); }

.user-list { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
.user-row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; flex-wrap: wrap; }
.u-main { flex: 1; min-width: 150px; }
.u-name { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.u-email { font-size: 12px; }
.tag {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 2px 7px;
  border-radius: 99px;
}
.admin-tag { background: var(--blue-soft, rgba(96,165,250,0.14)); color: #60a5fa; }
.recorder-tag { background: var(--green-soft); color: var(--green); }
.premium-tag { background: var(--accent-soft); color: var(--accent); }
.unverified-tag { background: var(--red-soft, rgba(239,68,68,0.12)); color: var(--red); }
.nomail-tag { background: var(--red-soft, rgba(239,68,68,0.12)); color: var(--red); }
.u-stats { display: flex; gap: 10px; font-size: 12px; white-space: nowrap; flex-wrap: wrap; }
.u-actions { display: flex; gap: 6px; }
.comp {
  background: none;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  padding: 7px 10px;
  cursor: pointer;
  white-space: nowrap;
}
.comp:hover { border-color: var(--accent); color: var(--accent); }
.comp:disabled { opacity: 0.5; }

.pager { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 14px; }

.denied { text-align: center; margin-top: 12vh; display: flex; flex-direction: column; gap: 10px; align-items: center; }
.denied-icon { font-size: 48px; }
</style>
