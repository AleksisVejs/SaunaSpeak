<script setup>
// Sauna Chat: free conversation with Väinö, an old-school Finn on the bench.
// Producing your own sentences - not recalling prompted ones - is what
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

// Rotating openers so every visit to the bench starts differently -
// and each one hands the learner a different first move to practice.
const OPENERS = [
  { content: 'No moi! 🧖 Istu alas vaan. Mitä sulle kuuluu?', translation: 'Well hi! Have a seat. How are you doing?' },
  { content: 'Moro! Heitin just löylyä. Millanen päivä sulla on ollu?', translation: "Hey! I just threw some steam. What kind of day have you had?" },
  { content: 'No terve! Pitkästä aikaa. Mitä sä oot puuhaillu?', translation: "Well hello! Long time no see. What have you been up to?" },
  { content: 'Moi moi, istu siihen. Onks sulla ollu kiire viikko?', translation: 'Hi hi, sit down. Have you had a busy week?' },
  { content: 'No moi! Sopivan kuuma, eiks vaan? Mitä sulle kuuluu?', translation: "Well hi! Nicely hot, isn't it? How are you doing?" }
]

const randomOpener = () => ({ role: 'assistant', ...OPENERS[Math.floor(Math.random() * OPENERS.length)] })

const messages = ref([randomOpener()])
const draft = ref('')
const sending = ref(false)
// Re-keyed on every Väinö reply → one steam burst per reply (löyly!).
const burst = ref(0)
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
    burst.value = Date.now()
    playSpoken(data.reply)
  } catch {
    messages.value.push({
      role: 'assistant',
      content: 'Hups, sauna meni pimeeks! 😅',
      translation: 'Oops, the sauna went dark! (Connection problem - try again.)'
    })
  } finally {
    sending.value = false
    scrollToEnd()
  }
}

function reset() {
  messages.value = [randomOpener()]
  showTranslation.value = {}
}

const full = () => messages.value.length >= MAX_TURNS
</script>

<template>
  <!-- Löyly+ paywall: the backend enforces it (402); this shows the pitch
       instead of a chat box that would error on send. -->
  <div v-if="!premium" class="chat-locked">
    <div class="locked-card">
      <div class="locked-vaino-wrap">
        <span class="locked-glow" aria-hidden="true"></span>
        <img class="locked-vaino" src="/vaino.png" alt="Väinö on the sauna bench" />
      </div>

      <div class="locked-body">
        <span class="locked-badge">🔒 Löyly+</span>
        <h1>Väinö's bench</h1>
        <p class="muted locked-lead">
          Free-form chatting with a patient old Finn - the fastest way to find the
          gaps the drills can't reach.
        </p>

        <ul class="locked-perks">
          <li><span class="lp-icon">💬</span><span>Real puhekieli, kept at your level</span></li>
          <li><span class="lp-icon">🧠</span><span>Gentle corrections that explain the why</span></li>
          <li><span class="lp-icon">🌿</span><span>Your own sentences, not just tap answers</span></li>
        </ul>

        <div class="locked-actions">
          <router-link to="/upgrade" class="btn btn-primary">♨️ See Löyly+</router-link>
          <router-link to="/dashboard" class="btn btn-ghost">Back to learning</router-link>
        </div>

        <p class="locked-free muted">The whole learning path stays free, forever.</p>
      </div>
    </div>
  </div>

  <div v-else class="chat sauna-scene">
    <!-- steam wisps + fog + kiuas glow (decoration only) -->
    <div class="steam" aria-hidden="true">
      <span v-for="i in 5" :key="i" class="wisp" :style="{ left: `${i * 19 - 5}%`, animationDelay: `${i * 1.7}s` }"></span>
    </div>
    <div class="fog" aria-hidden="true"></div>
    <div class="kiuas-glow" aria-hidden="true"></div>
    <!-- a throw of löyly every time Väinö replies: hiss, then rising puffs -->
    <div v-if="burst" :key="burst" class="loyly" aria-hidden="true">
      <i class="hiss"></i>
      <i class="puff" style="--dx: 0px; --d: 0s; --s: 1"></i>
      <i class="puff" style="--dx: -30px; --d: 0.18s; --s: 0.75"></i>
      <i class="puff" style="--dx: 26px; --d: 0.34s; --s: 0.85"></i>
      <i class="puff" style="--dx: -10px; --d: 0.52s; --s: 1.15"></i>
    </div>

    <!-- Väinö himself, on his bench in the sauna (desktop) -->
    <aside class="vaino-side">
      <img class="vaino-big" :src="AVATAR_URL" alt="Väinö sitting on the sauna bench with a ladle" @error="avatarOk = false" />
      <p class="who">Väinö</p>
      <p class="who-sub">speaks puhekieli · tap his bubbles for English</p>
    </aside>

    <!-- the chat, a panel beside him -->
    <section class="chat-panel">
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
        <div class="bubble assistant typing">
          Väinö miettii
          <span class="tdots"><span class="tdot"></span><span class="tdot"></span><span class="tdot"></span></span>
        </div>
      </div>
    </div>

      <div v-if="full()" class="full-note">
        <p class="muted">The löyly ran out - great chat! 🧖</p>
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
    </section>
  </div>
</template>

<style scoped>
.chat {
  display: flex;
  flex-direction: column;
  /* The shell goes full-bleed on this route (app-shell--full), so the sauna
     is the whole viewport. dvh tracks the real visible viewport on mobile
     (vh includes the URL bar). */
  height: 100vh;
  height: 100dvh;
  min-height: 360px;
}

.chat-locked {
  /* The shell has no padding on this route, so pad here. */
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px 16px calc(96px + env(safe-area-inset-bottom, 0px));
}
@media (min-width: 900px) {
  .chat-locked { padding: 40px; }
}
.locked-card {
  width: 100%;
  max-width: 720px;
  display: flex;
  align-items: center;
  gap: 28px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 26px 30px;
  box-shadow: var(--shadow-md);
}

.locked-vaino-wrap { position: relative; flex-shrink: 0; width: 150px; height: 150px; }
.locked-glow {
  position: absolute;
  inset: -20%;
  background: radial-gradient(closest-side, var(--accent-soft), transparent 72%);
  filter: blur(4px);
}
.locked-vaino { position: relative; width: 150px; height: 150px; }

.locked-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  text-align: left;
}

.locked-badge {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--accent);
  background: var(--accent-soft);
  border: 1px solid rgba(245, 158, 11, 0.28);
  border-radius: var(--radius-pill);
  padding: 4px 12px;
}
.chat-locked h1 { font-size: 22px; }
.locked-lead { font-size: 14px; line-height: 1.5; }

.locked-perks {
  list-style: none;
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 2px 0;
}
.locked-perks li {
  flex: 1 1 30%;
  min-width: 150px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  line-height: 1.35;
  color: var(--text);
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
}
.lp-icon { font-size: 17px; line-height: 1; flex-shrink: 0; }

.locked-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px; }
.locked-free { font-size: 12px; margin-top: 2px; }

/* stack Väinö above the pitch when the card gets narrow */
@media (max-width: 560px) {
  .locked-card { flex-direction: column; gap: 16px; padding: 24px 20px; text-align: center; }
  .locked-body { align-items: center; text-align: center; }
  .locked-actions { width: 100%; }
  .locked-actions .btn { flex: 1; }
}

/* ---- the sauna room (fills the viewport, edge to edge) ---- */
.sauna-scene {
  position: relative;
  overflow: hidden;
  padding: 14px;
  /* dim cedar interior: warm dark planks */
  background:
    repeating-linear-gradient(
      180deg,
      rgba(255, 255, 255, 0.02) 0 46px,
      rgba(0, 0, 0, 0.22) 46px 49px
    ),
    linear-gradient(180deg, #241812 0%, #2d1c12 55%, #38200f 100%);
}

/* ---- Väinö on his bench (left side, desktop only) ---- */
.vaino-side {
  display: none;
  position: relative;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  width: 250px;
  flex-shrink: 0;
  padding: 0 8px 18px;
  text-align: center;
}
.vaino-big {
  width: 210px;
  height: 210px;
  filter: drop-shadow(0 14px 22px rgba(0, 0, 0, 0.5));
  animation: breathe 5s ease-in-out infinite;
}
@keyframes breathe {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
@media (prefers-reduced-motion: reduce) {
  .vaino-big { animation: none; }
}
.vaino-side .who { margin-top: 10px; font-size: 18px; }
.vaino-side .who-sub { margin-top: 2px; max-width: 210px; line-height: 1.4; }

/* ---- the chat panel (the small box) ---- */
.chat-panel {
  position: relative;
  flex: 1;
  min-width: 0;
  /* Without this the panel refuses to shrink below its content in the
     mobile column layout, so .bubbles never scrolls and the composer
     gets clipped by the scene's overflow: hidden. */
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: rgba(18, 10, 5, 0.55);
  border: 1px solid rgba(243, 231, 211, 0.14);
  border-radius: 16px;
  padding: 12px;
}

/* side-by-side once there's room; Väinö steps out of the header */
@media (min-width: 760px) {
  .sauna-scene { flex-direction: row; gap: 10px; padding: 18px; }
  .vaino-side { display: flex; }
  .chat-panel .scene-head { display: none; }
}

/* Below 900px the fixed tab bar overlays the bottom edge: pad the scene so
   the composer clears it (the sauna glow still shows through the bar's blur).
   The scene now starts at the very top, so clear the notch too. */
@media (max-width: 899px) {
  .sauna-scene {
    padding-top: calc(14px + env(safe-area-inset-top, 0px));
    padding-bottom: calc(84px + env(safe-area-inset-bottom, 0px));
  }
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
/* fog hanging under the ceiling, drifting slowly */
.fog {
  position: absolute;
  top: -50px;
  left: -15%;
  right: -15%;
  height: 130px;
  background:
    radial-gradient(45% 90% at 25% 60%, rgba(238, 240, 244, 0.10), transparent 70%),
    radial-gradient(50% 100% at 65% 40%, rgba(238, 240, 244, 0.08), transparent 70%),
    radial-gradient(40% 80% at 90% 60%, rgba(238, 240, 244, 0.09), transparent 70%);
  filter: blur(14px);
  pointer-events: none;
  animation: drift 16s ease-in-out infinite alternate;
}
@keyframes drift {
  from { transform: translateX(-30px); }
  to { transform: translateX(30px); }
}

/* a ladleful of löyly hitting the stones when Väinö replies */
.loyly {
  position: absolute;
  bottom: 34px;
  left: 17%;
  width: 0;
  height: 0;
  pointer-events: none;
}
/* the sharp bright hiss where water meets stone */
.loyly .hiss {
  position: absolute;
  bottom: -6px;
  left: -35px;
  width: 70px;
  height: 34px;
  border-radius: 50%;
  background: radial-gradient(ellipse at center, rgba(255, 244, 220, 0.85), rgba(255, 200, 120, 0.25) 55%, transparent 75%);
  filter: blur(3px);
  opacity: 0;
  animation: hiss 0.55s ease-out forwards;
}
@keyframes hiss {
  0% { opacity: 0; transform: scaleX(0.3) scaleY(0.5); }
  30% { opacity: 1; transform: scaleX(1.15) scaleY(1); }
  100% { opacity: 0; transform: scaleX(1.5) scaleY(0.7); }
}
/* the steam itself: staggered puffs wobbling upward */
.loyly .puff {
  position: absolute;
  bottom: 0;
  left: -48px;
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: radial-gradient(circle at 45% 55%, rgba(240, 242, 246, 0.34), rgba(240, 242, 246, 0.12) 55%, transparent 70%);
  filter: blur(7px);
  opacity: 0;
  transform: translate(var(--dx, 0), 14px) scale(calc(var(--s, 1) * 0.3));
  animation: puff-rise 2.4s cubic-bezier(0.25, 0.6, 0.45, 1) forwards;
  animation-delay: var(--d, 0s);
}
@keyframes puff-rise {
  0% {
    opacity: 0;
    transform: translate(var(--dx, 0), 14px) scale(calc(var(--s, 1) * 0.3));
    filter: blur(5px);
  }
  14% { opacity: 0.85; }
  40% {
    opacity: 0.5;
    transform: translate(calc(var(--dx, 0) * 1.6), -130px) scale(calc(var(--s, 1) * 1.2));
  }
  100% {
    opacity: 0;
    transform: translate(calc(var(--dx, 0) * 2.6), -320px) scale(calc(var(--s, 1) * 2.2));
    filter: blur(16px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wisp, .kiuas-glow, .fog, .bubble, .vaino-big { animation: none; }
  .loyly { display: none; }
}

/* ---- Väinö ---- */
.scene-head {
  position: relative;
  flex-shrink: 0;
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
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 4px 2px 12px;
}
.row { display: flex; align-items: flex-end; gap: 6px; }
.row.user { justify-content: flex-end; }

.bubble {
  position: relative;
  max-width: 80%;
  padding: 12px 16px;
  line-height: 1.45;
  animation: puff-in 0.45s cubic-bezier(0.34, 1.4, 0.64, 1);
}
/* messages materialize like a puff of steam */
@keyframes puff-in {
  from { opacity: 0; transform: scale(0.82) translateY(8px); filter: blur(5px); }
  to { opacity: 1; transform: scale(1) translateY(0); filter: blur(0); }
}

.bubble.assistant {
  /* Väinö's words arrive as steam clouds */
  background: radial-gradient(130% 160% at 30% 15%, rgba(255, 255, 255, 0.97), rgba(230, 233, 238, 0.88));
  color: #2b1c10;
  border-radius: 26px 22px 24px 10px;
  box-shadow:
    0 8px 22px rgba(0, 0, 0, 0.3),
    inset 0 2px 6px rgba(255, 255, 255, 0.9),
    inset 0 -4px 10px rgba(160, 170, 185, 0.28);
  cursor: pointer;
}
/* cloud lumps along the top edge */
.bubble.assistant::before,
.bubble.assistant::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  background: inherit;
  box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.85);
  z-index: -1;
}
.bubble.assistant::before { width: 26px; height: 26px; top: -9px; left: 18px; }
.bubble.assistant::after { width: 18px; height: 18px; top: -6px; left: 48px; }

.bubble.user {
  /* the learner's löyly: warm amber steam */
  background: radial-gradient(130% 160% at 70% 15%, rgba(252, 200, 96, 0.96), rgba(245, 145, 30, 0.92));
  color: #1a1204;
  border-radius: 22px 26px 10px 24px;
  box-shadow:
    0 8px 22px rgba(0, 0, 0, 0.3),
    inset 0 2px 6px rgba(255, 226, 170, 0.85),
    inset 0 -4px 10px rgba(160, 80, 10, 0.3);
}
.bubble.user::before {
  content: '';
  position: absolute;
  width: 22px;
  height: 22px;
  top: -8px;
  right: 22px;
  border-radius: 50%;
  background: inherit;
  box-shadow: inset 0 2px 4px rgba(255, 226, 170, 0.8);
  z-index: -1;
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
.bubble.typing {
  font-style: italic;
  font-size: 14px;
  color: rgba(43, 28, 16, 0.6);
  display: flex;
  align-items: center;
  gap: 6px;
}
/* thinking = little steam puffs rising */
.tdots { display: inline-flex; gap: 4px; align-items: flex-end; }
.tdot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: rgba(43, 28, 16, 0.45);
  animation: tdot-rise 1.2s ease-in-out infinite;
}
.tdot:nth-child(2) { animation-delay: 0.2s; }
.tdot:nth-child(3) { animation-delay: 0.4s; }
@keyframes tdot-rise {
  0%, 100% { transform: translateY(0); opacity: 0.4; }
  40% { transform: translateY(-5px); opacity: 1; }
}

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
.composer { position: relative; display: flex; gap: 8px; padding-top: 10px; flex-shrink: 0; }
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
