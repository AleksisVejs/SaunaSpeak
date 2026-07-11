<script setup>
// Admin panel: platform stats + user management. Access is enforced by the
// backend (admin middleware, 403); this page just renders what it's allowed.
import { onMounted, ref } from 'vue'
import api from '../api'

const stats = ref(null)
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
    const [statsRes, usersRes] = await Promise.all([
      api.get('/admin/stats'),
      api.get('/admin/users', { params: { page: page.value, search: search.value || undefined } })
    ])
    stats.value = statsRes.data
    users.value = usersRes.data
  } catch (e) {
    if (e.response?.status === 403) denied.value = true
  } finally {
    loading.value = false
  }
}

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

      <!-- platform stats -->
      <div v-if="stats" class="stat-grid">
        <div class="card stat"><span class="v">{{ stats.users_total }}</span><span class="l">users</span></div>
        <div class="card stat"><span class="v">{{ stats.users_new_7d }}</span><span class="l">new · 7d</span></div>
        <div class="card stat"><span class="v">{{ stats.users_active_today }}</span><span class="l">active today</span></div>
        <div class="card stat"><span class="v">{{ stats.users_active_7d }}</span><span class="l">active · 7d</span></div>
        <div class="card stat accent"><span class="v">{{ stats.premium_count }}</span><span class="l">Löyly+</span></div>
        <div class="card stat"><span class="v">{{ stats.reviews_today }}</span><span class="l">reviews today</span></div>
        <div class="card stat"><span class="v">{{ stats.reviews_7d }}</span><span class="l">reviews · 7d</span></div>
        <div class="card stat"><span class="v">{{ stats.sentences_mastered_total }}</span><span class="l">mastered</span></div>
      </div>
      <p v-if="stats" class="muted content-note">
        Content: {{ stats.content.lessons }} lessons · {{ stats.content.sentences }} sentences
      </p>

      <!-- users -->
      <div class="users-head">
        <h3>Users</h3>
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
              <span v-if="u.is_premium" class="tag premium-tag">Löyly+</span>
            </p>
            <p class="u-email muted">{{ u.email }}</p>
          </div>
          <div class="u-stats muted">
            <span>⚡{{ u.xp }}</span>
            <span>🔥{{ u.streak }}</span>
            <span title="Last active">📅 {{ fmtDate(u.last_active_date) }}</span>
          </div>
          <button class="comp" :disabled="busy[u.id]" @click="togglePremium(u)">
            {{ u.is_premium ? 'Revoke +' : 'Comp 30d' }}
          </button>
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

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.stat { display: flex; flex-direction: column; align-items: center; padding: 12px 6px; }
.stat .v { font-size: 20px; font-weight: 800; }
.stat .l { font-size: 10px; color: var(--text-dim); text-align: center; }
.stat.accent .v { color: var(--accent); }
.content-note { font-size: 12px; margin: 10px 0 20px; text-align: center; }

.users-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.users-head h3 { font-size: 17px; }
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

.user-list { display: flex; flex-direction: column; gap: 8px; }
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
.premium-tag { background: var(--accent-soft); color: var(--accent); }
.u-stats { display: flex; gap: 10px; font-size: 12px; white-space: nowrap; }
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
