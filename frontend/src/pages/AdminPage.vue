<script setup>
// Admin panel: platform pulse (stats + 30-day trends), content health,
// pending-recording review, and user management. Access is enforced by the
// backend (admin middleware, 403); this page just renders what it's allowed.
import { computed, onMounted, ref } from 'vue'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playClip } = useFinnishAudio()

const stats = ref(null)
const trends = ref(null)
const users = ref(null)
const search = ref('')
const page = ref(1)
const loading = ref(true)
const denied = ref(false)
const busy = ref({}) // user id → toggling

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [statsRes, trendsRes, usersRes, recRes] = await Promise.all([
      api.get('/admin/stats'),
      api.get('/admin/trends'),
      api.get('/admin/users', { params: { page: page.value, search: search.value || undefined } }),
      api.get('/admin/recordings')
    ])
    stats.value = statsRes.data
    trends.value = trendsRes.data
    users.value = usersRes.data
    recordings.value = recRes.data
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

// ---- content health ----
const maxLevelSentences = computed(() =>
  Math.max(1, ...(stats.value?.levels ?? []).map((l) => Number(l.sentences) || 0))
)

// ---- pending recordings review: listen, then approve (live) or reject ----
const recordings = ref(null)
const recBusy = ref(false)

const pendingItems = () => [
  ...(recordings.value?.sentences ?? []).map((s) => ({ ...s, type: 'sentence', key: String(s.id), label: s.finnish_text })),
  ...(recordings.value?.words ?? []).map((w) => ({ ...w, type: 'word', key: w.word, label: w.word }))
]

const pendingOf = (type) => pendingItems().filter((i) => i.type === type)

// The recordings manager lives inside the content-health card: each
// "Human-voiced …" row expands into its review + live lists.
const openAudio = ref(null) // 'sentence' | 'word' | null
function toggleAudio(type) {
  openAudio.value = openAudio.value === type ? null : type
  liveQ.value = ''
}

const AUDIO_SECTIONS = [
  { type: 'sentence', label: 'Human-voiced sentences' },
  { type: 'word', label: 'Human-voiced words' }
]

function audioNums(type) {
  const a = stats.value?.audio
  if (!a) return { done: 0, total: 0, pct: 0 }
  const done = type === 'sentence' ? a.sentences_human : a.words_human
  const total = type === 'sentence' ? a.sentences_total : a.words_total
  return { done, total, pct: total ? Math.round((done / total) * 100) : 0 }
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
    }))
  ]
})

const liveOf = (type) => liveItems.value.filter((i) => i.type === type)

async function removeLive(item) {
  recBusy.value = true
  try {
    if (item.type === 'sentence') {
      await api.delete(`/record/sentence/${item.id}`)
      const idx = recordings.value.live_sentences.findIndex((s) => s.id === item.id)
      if (idx !== -1) recordings.value.live_sentences.splice(idx, 1)
    } else {
      await api.delete('/record/word', { params: { word: item.word } })
      const idx = recordings.value.live_words.findIndex((w) => w.word === item.word)
      if (idx !== -1) recordings.value.live_words.splice(idx, 1)
    }
  } finally {
    recBusy.value = false
  }
}

// ---- users ----
async function searchUsers() {
  page.value = 1
  await load()
}

async function goPage(p) {
  page.value = p
  await load()
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

      <!-- headline numbers -->
      <template v-if="stats">
        <h3 class="group-label">Learners</h3>
        <div class="stat-grid">
          <div class="card stat"><span class="v">{{ stats.users_total }}</span><span class="l">users</span></div>
          <div class="card stat"><span class="v">{{ stats.users_new_7d }}</span><span class="l">new · 7d</span></div>
          <div class="card stat"><span class="v">{{ stats.users_active_today }}</span><span class="l">active today</span></div>
          <div class="card stat"><span class="v">{{ stats.users_active_7d }}</span><span class="l">active · 7d</span></div>
        </div>

        <h3 class="group-label">Engagement &amp; business</h3>
        <div class="stat-grid">
          <div class="card stat"><span class="v">{{ stats.reviews_today }}</span><span class="l">reviews today</span></div>
          <div class="card stat"><span class="v">{{ stats.reviews_7d }}</span><span class="l">reviews · 7d</span></div>
          <div class="card stat"><span class="v">{{ stats.sentences_mastered_total }}</span><span class="l">mastered</span></div>
          <div class="card stat accent"><span class="v">{{ stats.premium_count }}</span><span class="l">Löyly+</span></div>
        </div>
      </template>

      <!-- 30-day trends: one series per strip -->
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

      <!-- content health: course shape + the road from robot to human voice -->
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
                      <p v-if="item.type === 'sentence' && item.english_text" class="rec-en muted">{{ item.english_text }}</p>
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

      <!-- users -->
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
    </div>
  </div>
</template>

<style scoped>
.admin h2 { font-size: 24px; margin-bottom: 16px; }

.group-label {
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin: 20px 0 8px;
}

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.stat { display: flex; flex-direction: column; align-items: center; padding: 12px 6px; }
.stat .v { font-size: 20px; font-weight: 800; }
.stat .l { font-size: 10px; color: var(--text-dim); text-align: center; }
.stat.accent .v { color: var(--accent); }

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
