<script setup>
// Tilanteet: guided roleplay situations. Each card is a mission ("buy milk
// and pay") played out with an AI character in the chat scene. The catalog
// comes from the backend, ordered so situations matching the learner's
// intake goal float to the top.
import { computed, onMounted, ref } from 'vue'
import { Check, Lock, Medal, Target } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { SCENE_ART } from '../utils/sceneArt'

// Per-card image failure → emoji fallback (art is optional by design).
const artFailed = ref({})

const auth = useAuthStore()
const scenarios = ref(null)
const error = ref('')

// Same gate pattern as ChatPage: the backend enforces premium on /chat;
// here we just route free users to the pitch instead of a dead chat.
const premium = computed(() => auth.user?.is_premium !== false)

onMounted(async () => {
  if (!auth.user) auth.fetchUser()
  try {
    const { data } = await api.get('/scenarios')
    scenarios.value = data.scenarios
  } catch {
    error.value = 'Could not load the situations. Try again in a moment.'
  }
})

const recommended = computed(() => (scenarios.value ?? []).filter((s) => s.recommended))
const others = computed(() => (scenarios.value ?? []).filter((s) => !s.recommended))
const doneCount = computed(() => (scenarios.value ?? []).filter((s) => s.done).length)
</script>

<template>
  <div class="scenarios">
    <header class="head">
      <h1>
        Situations
        <span v-if="doneCount" class="done-count"><Medal class="dc-ico" aria-hidden="true" /> {{ doneCount }}/{{ scenarios.length }} completed</span>
      </h1>
      <p class="muted lead">
        Real-life missions, played out in spoken Finnish. Walk into the scene,
        say your part, get the thing done.
      </p>
    </header>

    <div v-if="error" class="error-msg">{{ error }}</div>
    <p v-else-if="!scenarios" class="muted loading">Setting the scenes…</p>

    <template v-else>
      <div v-if="!premium" class="card lock-note">
        <span class="lock-badge"><Lock class="lb-ico" aria-hidden="true" /> Löyly+</span>
        <p>
          Situations are part of <b>Löyly+</b> - browse them freely, and
          <router-link to="/upgrade">upgrade</router-link> to step into one.
        </p>
      </div>

      <section v-if="recommended.length" class="group">
        <h2 class="group-title">Recommended for you</h2>
        <div class="grid">
          <component
            :is="premium ? 'router-link' : 'div'"
            v-for="s in recommended"
            :key="s.id"
            :to="premium ? { name: 'chat', query: { scenario: s.id } } : undefined"
            class="scene-card"
            :class="{ locked: !premium }"
          >
            <img
              v-if="SCENE_ART[s.id] && !artFailed[s.id]"
              class="scene-face"
              :src="SCENE_ART[s.id].character"
              :alt="s.persona"
              loading="lazy"
              @error="artFailed[s.id] = true"
            />
            <span v-else class="scene-emoji">{{ s.emoji }}</span>
            <div class="scene-body">
              <p class="scene-title">{{ s.title }}</p>
              <p class="scene-tagline muted">{{ s.tagline }}</p>
              <p class="scene-mission"><Target class="sm-ico" aria-hidden="true" /> {{ s.mission }}</p>
            </div>
            <span class="scene-meta">
              <span class="pill" :class="s.difficulty">{{ s.difficulty }}</span>
              <span v-if="s.done" class="scene-done" title="Mission accomplished - replay any time"><Check class="sd-ico" aria-hidden="true" /></span>
              <span v-else-if="!premium" class="scene-lock"><Lock class="sd-ico" aria-hidden="true" /></span>
              <span v-else class="scene-xp" title="First-completion reward">+{{ s.xp }} XP</span>
            </span>
          </component>
        </div>
      </section>

      <section class="group">
        <h2 v-if="recommended.length" class="group-title">All situations</h2>
        <div class="grid">
          <component
            :is="premium ? 'router-link' : 'div'"
            v-for="s in others"
            :key="s.id"
            :to="premium ? { name: 'chat', query: { scenario: s.id } } : undefined"
            class="scene-card"
            :class="{ locked: !premium }"
          >
            <img
              v-if="SCENE_ART[s.id] && !artFailed[s.id]"
              class="scene-face"
              :src="SCENE_ART[s.id].character"
              :alt="s.persona"
              loading="lazy"
              @error="artFailed[s.id] = true"
            />
            <span v-else class="scene-emoji">{{ s.emoji }}</span>
            <div class="scene-body">
              <p class="scene-title">{{ s.title }}</p>
              <p class="scene-tagline muted">{{ s.tagline }}</p>
              <p class="scene-mission"><Target class="sm-ico" aria-hidden="true" /> {{ s.mission }}</p>
            </div>
            <span class="scene-meta">
              <span class="pill" :class="s.difficulty">{{ s.difficulty }}</span>
              <span v-if="s.done" class="scene-done" title="Mission accomplished - replay any time"><Check class="sd-ico" aria-hidden="true" /></span>
              <span v-else-if="!premium" class="scene-lock"><Lock class="sd-ico" aria-hidden="true" /></span>
              <span v-else class="scene-xp" title="First-completion reward">+{{ s.xp }} XP</span>
            </span>
          </component>
        </div>
      </section>

      <p class="free-chat muted">
        Just want to talk? <router-link to="/chat">Väinö's bench</router-link> is always open for free-form chat.
      </p>
    </template>
  </div>
</template>

<style scoped>
.scenarios { display: flex; flex-direction: column; gap: 20px; }
.head h1 { font-size: 26px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.done-count {
  font-size: 12px;
  font-weight: 800;
  color: var(--green);
  background: var(--green-soft);
  border: 1px solid var(--green);
  border-radius: var(--radius-pill);
  padding: 4px 12px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.dc-ico { width: 13px; height: 13px; flex-shrink: 0; }
.sd-ico { width: 12px; height: 12px; }
.sm-ico { width: 12px; height: 12px; vertical-align: -1px; }
.lb-ico { width: 12px; height: 12px; flex-shrink: 0; }
.scene-done {
  width: 22px;
  height: 22px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 800;
  color: var(--green);
  background: var(--green-soft);
  border: 1.5px solid var(--green);
}
.lead { margin-top: 6px; font-size: 14px; line-height: 1.5; max-width: 46ch; }
.loading { text-align: center; padding: 30px 0; }

.lock-note { display: flex; align-items: center; gap: 12px; line-height: 1.5; font-size: 14px; }
.lock-badge {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 700;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: var(--radius-pill);
  padding: 4px 12px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.lock-note a { color: var(--accent); font-weight: 700; }

.group { display: flex; flex-direction: column; gap: 10px; }
.group-title { font-size: 14px; font-weight: 800; letter-spacing: 0.01em; color: var(--text-dim); text-transform: uppercase; }

.grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 560px) { .grid { grid-template-columns: 1fr 1fr; } }

.scene-card {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px;
  color: var(--text);
  transition: border-color 0.15s ease, transform 0.15s ease;
}
.scene-card:not(.locked):hover { border-color: var(--accent); transform: translateY(-1px); }
.scene-card.locked { opacity: 0.75; }

.scene-emoji { font-size: 26px; line-height: 1; margin-top: 2px; }
.scene-face { width: 52px; height: 52px; object-fit: contain; flex-shrink: 0; }
.scene-body { flex: 1; min-width: 0; }
.scene-title { font-weight: 800; font-size: 15px; }
.scene-tagline { font-size: 13px; line-height: 1.4; margin-top: 2px; }
.scene-mission { font-size: 12.5px; font-weight: 600; margin-top: 8px; color: var(--accent); }

.scene-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  color: var(--text-dim);
}
.pill.easy { background: var(--green-soft); border-color: var(--green); color: var(--green); }
.pill.hard { background: var(--red-soft); border-color: var(--red); color: var(--red); }
.scene-lock { color: var(--text-dim); display: inline-flex; }
.scene-xp { font-size: 11px; font-weight: 800; color: var(--accent); white-space: nowrap; }

.free-chat { text-align: center; font-size: 13px; }
.free-chat a { color: var(--accent); font-weight: 700; }
</style>
