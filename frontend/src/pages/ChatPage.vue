<script setup>
// Sauna Chat: free conversation with Väinö, an old-school Finn on the bench.
// Producing your own sentences — not recalling prompted ones — is what
// exposes the gaps in your Finnish (the output hypothesis). Väinö replies in
// real puhekieli at your level and only corrects real mistakes.
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playSpoken } = useFinnishAudio()
const auth = useAuthStore()

// Löyly+ gate: the backend enforces it (402); this just shows the pitch
// instead of a chat box that would error on send.
const premium = computed(() => auth.user?.is_premium !== false)
onMounted(() => {
  if (!auth.user) auth.fetchUser()
})

const OPENER = {
  role: 'assistant',
  content: 'No moi! 🧖 Istu alas vaan. Mitä sulle kuuluu?',
  translation: 'Well hi! Have a seat. How are you doing?'
}

const messages = ref([{ ...OPENER }])
const draft = ref('')
const sending = ref(false)
const showTranslation = ref({}) // index → bool
const listening = ref(false)
const listRef = ref(null)

// Väinö's portrait: drop an image at public/vaino.png (frontend) to replace
// the emoji fallback everywhere it appears. Bound dynamically so Vite doesn't
// try to resolve it at build time before the image exists.
const AVATAR_URL = '/vaino.png'
const avatarOk = ref(true)

const MAX_TURNS = 30

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
const micSupported = !!SpeechRecognition
let recognition = null

function startListening() {
  if (!micSupported || listening.value) return
  recognition = new SpeechRecognition()
  recognition.lang = 'fi-FI'
  recognition.interimResults = false
  recognition.onresult = (e) => {
    draft.value = e.results[0][0].transcript
  }
  recognition.onend = () => (listening.value = false)
  recognition.onerror = () => (listening.value = false)
  listening.value = true
  recognition.start()
}

function stopListening() {
  recognition?.abort()
  listening.value = false
}

onBeforeUnmount(stopListening)

async function scrollToEnd() {
  await nextTick()
  listRef.value?.scrollTo({ top: listRef.value.scrollHeight, behavior: 'smooth' })
}

async function send() {
  const text = draft.value.trim()
  if (!text || sending.value || messages.value.length >= MAX_TURNS) return

  stopListening()
  draft.value = ''
  messages.value.push({ role: 'user', content: text })
  sending.value = true
  scrollToEnd()

  try {
    const { data } = await api.post('/chat', {
      // The API sees only role+content; local metadata stays local.
      messages: messages.value.map(({ role, content }) => ({ role, content }))
    })

    // Attach Väinö's gentle correction to the message it corrects.
    if (data.correction) {
      messages.value[messages.value.length - 1].correction = data.correction
    }
    messages.value.push({ role: 'assistant', content: data.reply, translation: data.translation })
    playSpoken(data.reply)
  } catch {
    messages.value.push({
      role: 'assistant',
      content: 'Hups, sauna meni pimeeks! 😅',
      translation: 'Oops, the sauna went dark! (Connection problem — try again.)'
    })
  } finally {
    sending.value = false
    scrollToEnd()
  }
}

function reset() {
  messages.value = [{ ...OPENER }]
  showTranslation.value = {}
}

const full = () => messages.value.length >= MAX_TURNS
</script>

<template>
  <!-- Löyly+ paywall: the backend enforces it (402); this shows the pitch
       instead of a chat box that would error on send. -->
  <div v-if="!premium" class="chat-locked">
    <img class="locked-vaino" src="/vaino.png" alt="Väinö on the sauna bench" />
    <h1>Väinö's bench is Löyly+</h1>
    <p class="muted">
      Free chatting with a patient Finn — real puhekieli, at your level, with
      gentle corrections. Producing your own sentences is the practice drills can't give you.
    </p>
    <router-link to="/upgrade" class="btn btn-primary btn-block">♨️ See Löyly+</router-link>
    <router-link to="/dashboard" class="btn btn-ghost btn-block">Back to learning</router-link>
  </div>

  <div v-else class="chat sauna-scene">
    <!-- steam wisps + kiuas glow (decoration only) -->
    <div class="steam" aria-hidden="true">
      <span v-for="i in 5" :key="i" class="wisp" :style="{ left: `${i * 19 - 5}%`, animationDelay: `${i * 1.7}s` }"></span>
    </div>
    <div class="kiuas-glow" aria-hidden="true"></div>

    <header class="scene-head">
      <span class="avatar">
        <img
          v-if="avatarOk"
          :src="AVATAR_URL"
          alt="Väinö"
          @error="avatarOk = false"
        />
        <span v-else class="avatar-fallback">🧔</span>
      </span>
      <div>
        <p class="who">Väinö</p>
        <p class="who-sub">on the bench · speaks puhekieli · tap his bubbles for English</p>
      </div>
    </header>

    <div ref="listRef" class="bubbles">
      <div
        v-for="(m, i) in messages"
        :key="i"
        class="row"
        :class="m.role"
      >
        <span v-if="m.role === 'assistant'" class="avatar small">
          <img v-if="avatarOk" :src="AVATAR_URL" alt="" @error="avatarOk = false" />
          <span v-else class="avatar-fallback">🧔</span>
        </span>
        <div
          class="bubble"
          :class="m.role"
          @click="m.role === 'assistant' && (showTranslation[i] = !showTranslation[i])"
        >
          <p class="bubble-text">{{ m.content }}</p>
          <p v-if="m.role === 'assistant' && showTranslation[i]" class="bubble-translation">
            {{ m.translation }}
          </p>
          <p v-if="m.correction" class="bubble-correction">✏️ {{ m.correction }}</p>
        </div>
        <button
          v-if="m.role === 'assistant'"
          class="speak"
          aria-label="Play"
          @click="playSpoken(m.content)"
        >🔊</button>
      </div>

      <div v-if="sending" class="row assistant">
        <span class="avatar small">
          <img v-if="avatarOk" :src="AVATAR_URL" alt="" @error="avatarOk = false" />
          <span v-else class="avatar-fallback">🧔</span>
        </span>
        <div class="bubble assistant typing">Väinö miettii…</div>
      </div>
    </div>

    <div v-if="full()" class="full-note">
      <p class="muted">The löyly ran out — great chat! 🧖</p>
      <button class="btn btn-ghost" @click="reset">Start a fresh chat</button>
    </div>

    <div v-else class="composer">
      <button
        v-if="micSupported"
        class="btn btn-ghost mic"
        :class="{ listening }"
        :title="listening ? 'Listening… tap to stop' : 'Say it in Finnish'"
        @click="listening ? stopListening() : startListening()"
      >{{ listening ? '👂' : '🎤' }}</button>
      <input
        v-model="draft"
        type="text"
        class="chat-input"
        :placeholder="listening ? 'Listening…' : 'Sano jotain suomeks…'"
        autocapitalize="none"
        autocomplete="off"
        spellcheck="false"
        :disabled="sending"
        @keyup.enter="send"
      />
      <button class="btn btn-primary send" :disabled="!draft.trim() || sending" @click="send">➤</button>
    </div>
  </div>
</template>

<style scoped>
.chat { display: flex; flex-direction: column; height: calc(100vh - 140px); min-height: 420px; }

.chat-locked {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: center;
  gap: 12px;
}
.locked-vaino { width: 130px; height: 130px; margin: 0 auto; }
.chat-locked h1 { font-size: 24px; }
.chat-locked .muted { line-height: 1.55; margin-bottom: 8px; }

/* ---- the sauna ---- */
.sauna-scene {
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-lg, 20px);
  padding: 14px;
  /* dim cedar interior: warm dark planks */
  background:
    repeating-linear-gradient(
      180deg,
      rgba(255, 255, 255, 0.02) 0 46px,
      rgba(0, 0, 0, 0.22) 46px 49px
    ),
    linear-gradient(180deg, #241812 0%, #2d1c12 55%, #38200f 100%);
  border: 1px solid rgba(245, 158, 11, 0.18);
}

/* embers glowing at the bottom, like the kiuas */
.kiuas-glow {
  position: absolute;
  left: 0;
  right: 0;
  bottom: -60px;
  height: 140px;
  background: radial-gradient(ellipse at 50% 100%, rgba(245, 110, 15, 0.30), transparent 68%);
  pointer-events: none;
  animation: ember 3.4s ease-in-out infinite;
}
@keyframes ember {
  0%, 100% { opacity: 0.75; }
  50% { opacity: 1; }
}

/* rising steam */
.steam { position: absolute; inset: 0; pointer-events: none; }
.wisp {
  position: absolute;
  bottom: -40px;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(238, 240, 244, 0.07), transparent 70%);
  filter: blur(6px);
  animation: rise 9s linear infinite;
}
@keyframes rise {
  from { transform: translateY(0) scale(1); opacity: 0; }
  15% { opacity: 1; }
  to { transform: translateY(-540px) scale(1.7); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .wisp, .kiuas-glow { animation: none; }
}

/* ---- Väinö ---- */
.scene-head {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(245, 158, 11, 0.15);
  margin-bottom: 10px;
}
.avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid rgba(245, 158, 11, 0.45);
  background: rgba(245, 158, 11, 0.12);
  display: grid;
  place-items: center;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-fallback { font-size: 26px; }
.avatar.small { width: 30px; height: 30px; border-width: 1px; align-self: flex-end; }
.avatar.small .avatar-fallback { font-size: 15px; }
.who { font-weight: 800; font-size: 16px; color: #f3e7d3; }
.who-sub { font-size: 12px; color: rgba(243, 231, 211, 0.55); }

/* ---- bubbles ---- */
.bubbles {
  position: relative;
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 4px 2px 12px;
}
.row { display: flex; align-items: flex-end; gap: 6px; }
.row.user { justify-content: flex-end; }

.bubble {
  max-width: 80%;
  padding: 11px 14px;
  border-radius: var(--radius);
  line-height: 1.45;
}
.bubble.assistant {
  /* pale birch wood — Väinö's side */
  background: rgba(243, 231, 211, 0.94);
  color: #2b1c10;
  border-bottom-left-radius: 6px;
  cursor: pointer;
}
.bubble.user {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.92), rgba(251, 146, 60, 0.92));
  color: #1a1204;
  border-bottom-right-radius: 6px;
}
.bubble-text { font-size: 15px; font-weight: 600; }
.bubble-translation {
  font-size: 13px;
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px dashed rgba(43, 28, 16, 0.3);
  color: rgba(43, 28, 16, 0.75);
}
.bubble-correction {
  font-size: 13px;
  font-weight: 700;
  margin-top: 6px;
  color: #7a3b00;
}
.bubble.typing { font-style: italic; font-size: 14px; color: rgba(43, 28, 16, 0.6); }

.speak {
  background: rgba(243, 231, 211, 0.12);
  border: 1px solid rgba(243, 231, 211, 0.3);
  color: #f3e7d3;
  border-radius: 8px;
  padding: 6px 8px;
  font-size: 12px;
  cursor: pointer;
  flex-shrink: 0;
}
.speak:hover { border-color: var(--accent); }

.full-note {
  position: relative;
  text-align: center;
  padding: 12px 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}
.full-note .muted { color: rgba(243, 231, 211, 0.7); }

/* ---- composer ---- */
.composer { position: relative; display: flex; gap: 8px; padding-top: 10px; }
.mic { padding: 12px 14px; font-size: 17px; flex-shrink: 0; }
.mic.listening {
  border-color: var(--accent);
  color: var(--accent);
  animation: mic-pulse 1.2s ease-in-out infinite;
}
@keyframes mic-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.35); }
  50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
}
.chat-input {
  flex: 1;
  min-width: 0;
  background: rgba(20, 12, 6, 0.6);
  border: 1px solid rgba(243, 231, 211, 0.25);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  font-size: 16px;
  font-family: inherit;
  color: #f3e7d3;
  outline: none;
}
.chat-input::placeholder { color: rgba(243, 231, 211, 0.4); }
.chat-input:focus { border-color: var(--accent); }
.send { padding: 12px 18px; flex-shrink: 0; }
</style>
