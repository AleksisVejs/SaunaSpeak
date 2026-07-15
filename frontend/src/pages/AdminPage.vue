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
const audioPct = computed(() => {
  const a = stats.value?.audio
  if (!a) return { sentences: 0, words: 0 }
  return {
    sentences: a.sentences_total ? Math.round((a.sentences_human / a.sentences_total) * 100) : 0,
    words: a.words_total ? Math.round((a.words_human / a.words_total) * 100) : 0
  }
})

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

async function approveAll() {
  recBusy.value = true
  try {
    await api.post('/admin/recordings/approve', { all: true })
    // Approved pending takes are now live - refetch for the fresh picture.
    const { data } = await api.get('/admin/recordings')
    recordings.value = data
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
      type: 'word', keyId: `w-${w.word}`, word: w.word, label: w.word, note: 'word', url: w.audio_url
    }))
  ]
})

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
            <div class="audio-row">
              <span class="audio-label">🎙 Human-voiced sentences</span>
              <span class="audio-nums muted">{{ stats.audio.sentences_human }}/{{ stats.audio.sentences_total }} · {{ audioPct.sentences }}%</span>
            </div>
            <div class="progress-track slim"><div class="progress-fill green" :style="{ width: audioPct.sentences + '%' }"></div></div>

            <div class="audio-row">
              <span class="audio-label">🎙 Human-voiced words</span>
              <span class="audio-nums muted">{{ stats.audio.words_human }}/{{ stats.audio.words_total }} · {{ audioPct.words }}%</span>
            </div>
            <div class="progress-track slim"><div class="progress-fill green" :style="{ width: audioPct.words + '%' }"></div></div>
          </div>
        </div>
      </template>

      <!-- pending recordings: nothing goes live without a listen here -->
      <template v-if="pendingItems().length">
        <div class="rec-head">
          <h3 class="group-label rec-label">🎙 Recordings awaiting review <span class="rec-count">{{ pendingItems().length }}</span></h3>
          <button class="btn btn-ghost approve-all" :disabled="recBusy" @click="approveAll">✓ Approve all</button>
        </div>
        <div class="rec-list">
          <div v-for="item in pendingItems()" :key="item.type + item.key" class="card rec-row">
            <div class="rec-main">
              <p class="rec-text">{{ item.label }}</p>
              <p v-if="item.type === 'sentence' && item.english_text" class="rec-en muted">{{ item.english_text }}</p>
              <p v-else-if="item.type === 'word'" class="rec-en muted">word</p>
            </div>
            <div class="rec-actions">
              <button class="rec-btn" title="Play the new human take" @click="playUrl(item.pending_url)">▶ New</button>
              <button class="rec-btn" title="Play the current (TTS) version" :disabled="!item.current_url" @click="playUrl(item.current_url)">🤖 Old</button>
              <button class="rec-btn ok" :disabled="recBusy" title="Approve - goes live" @click="review(item, 'approve')">✓</button>
              <button class="rec-btn no" :disabled="recBusy" title="Reject - back to the recorder's queue" @click="review(item, 'reject')">✗</button>
            </div>
          </div>
        </div>
      </template>

      <!-- live human recordings: play any, retire any back to TTS -->
      <template v-if="recordings && (recordings.live_sentences?.length || recordings.live_words?.length)">
        <div class="rec-head">
          <h3 class="group-label rec-label">✅ Live human recordings <span class="rec-count">{{ (recordings.live_sentences?.length ?? 0) + (recordings.live_words?.length ?? 0) }}</span></h3>
          <input
            v-model="liveQ"
            class="search live-search"
            type="search"
            placeholder="Search recordings…"
            aria-label="Search live recordings"
          />
        </div>
        <div class="rec-list">
          <div v-for="item in liveItems.slice(0, LIVE_MAX)" :key="item.keyId" class="card rec-row">
            <div class="rec-main">
              <p class="rec-text">{{ item.label }}</p>
              <p v-if="item.note" class="rec-en muted">{{ item.note }}</p>
            </div>
            <div class="rec-actions">
              <button class="rec-btn" title="Play the live recording" @click="playUrl(item.url)">▶ Play</button>
              <button class="rec-btn no" :disabled="recBusy" title="Remove - the app falls back to the TTS voice" @click="removeLive(item)">✗ Remove</button>
            </div>
          </div>
          <p v-if="liveItems.length > LIVE_MAX" class="muted live-more">
            +{{ liveItems.length - LIVE_MAX }} more - search to narrow down.
          </p>
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
.audio-row { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.audio-label { font-size: 13px; font-weight: 700; }
.audio-nums { font-size: 12px; white-space: nowrap; }
.progress-track.slim { height: 6px; margin-bottom: 8px; }
.progress-fill.green { background: var(--green); }

/* ---- pending recordings ---- */
.rec-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.rec-label { display: flex; align-items: center; gap: 8px; }
.rec-count {
  font-size: 11px;
  font-weight: 800;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: 99px;
  padding: 2px 9px;
}
.approve-all { font-size: 13px; padding: 8px 14px; }
.live-search { max-width: 200px; }
.live-more { font-size: 12px; text-align: center; margin-top: 4px; }
.rec-list { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
.rec-row { display: flex; align-items: center; gap: 12px; padding: 11px 14px; flex-wrap: wrap; }
.rec-main { flex: 1; min-width: 160px; }
.rec-text { font-weight: 700; font-size: 15px; }
.rec-en { font-size: 12px; margin-top: 1px; }
.rec-actions { display: flex; gap: 6px; }
.rec-btn {
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
.rec-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.rec-btn:disabled { opacity: 0.5; cursor: default; }
.rec-btn.ok:hover:not(:disabled) { border-color: var(--green); color: var(--green); }
.rec-btn.no:hover:not(:disabled) { border-color: var(--red); color: var(--red); }

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
