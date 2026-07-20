<script setup>
// Sauna Chat: free conversation with Väinö, an old-school Finn on the bench.
// Producing your own sentences - not recalling prompted ones - is what
// exposes the gaps in your Finnish (the output hypothesis). Väinö replies in
// real puhekieli at your level and only corrects real mistakes.
//
// Scenario mode (?scenario=kauppa): the same chat engine playing a character
// from the Tilanteet catalog, with a mission banner and a completion moment
// when the backend reports goal_reached.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { BookmarkCheck, Brain, CircleCheck, Drama, Ear, Languages, Lightbulb, Lock, MessageCircle, Mic, RotateCcw, Send, Sparkles, Target, Turtle, Volume2 } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { SCENE_ART } from '../utils/sceneArt'

const { playSpoken } = useFinnishAudio()
const auth = useAuthStore()
const route = useRoute()

// Löyly+ gate: the backend enforces it (402); this just shows the pitch
// instead of a chat box that would error on send.
const premium = computed(() => auth.user?.is_premium !== false)

// (Re)enter the scene: pick the character and their first line. Runs on
// mount and whenever the ?scenario= query changes (e.g. tapping the Chat tab
// while inside a situation returns to Väinö).
async function enterScene() {
  missionDone.value = false
  xpGained.value = 0
  showTranslation.value = {}
  charOk.value = true

  const id = route.query.scenario
  if (!id) {
    scenario.value = null
    messages.value = restoreChat() ?? [randomOpener()]
    return
  }

  try {
    const { data } = await api.get('/scenarios')
    scenario.value = data.scenarios.find((s) => s.id === id) ?? null
  } catch {
    scenario.value = null
  }

  messages.value = scenario.value
    ? [{ role: 'assistant', content: scenario.value.opener, translation: scenario.value.opener_translation }]
    : [randomOpener()]
}

onMounted(() => {
  if (!auth.user) auth.fetchUser()
  enterScene()
  // Desktop: land ready to type. On touch, focusing would pop the keyboard.
  if (window.matchMedia('(pointer: fine)').matches) inputRef.value?.focus()
})
watch(() => route.query.scenario, () => {
  if (route.name === 'chat') enterScene()
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

// Väinö remembers the bench: the free-form chat survives a refresh (24h),
// so leaving mid-thought never wipes the conversation. Missions start fresh.
const CHAT_STORE = 'ss_chat_vaino'

function saveChat() {
  if (scenario.value) return
  try {
    localStorage.setItem(CHAT_STORE, JSON.stringify({ at: Date.now(), messages: messages.value }))
  } catch {
    // storage blocked or full - the chat just won't persist
  }
}

function restoreChat() {
  try {
    const raw = JSON.parse(localStorage.getItem(CHAT_STORE))
    if (raw?.messages?.length > 1 && Date.now() - raw.at < 24 * 3600 * 1000) return raw.messages
  } catch {
    // corrupt entry - fall through to a fresh opener
  }
  return null
}

// Scaffolding for the blank-page moment: three lines a beginner can always
// reach for. Tapping one fills the box - reading it before sending is part
// of the practice. Väinö's bench gets small talk; inside a Situation the
// small-talk line would derail the mission, so that set is all repair
// phrases that work mid-transaction with any character.
const OPEN_HINTS = [
  { fi: 'Mulle kuuluu hyvää, entä sulle?', en: "I'm doing well, how about you?" },
  { fi: 'Voitsä sanoo sen uudestaan?', en: 'Can you say that again?' },
  { fi: 'Mitä toi tarkottaa?', en: 'What does that mean?' }
]
const SCENARIO_HINTS = [
  { fi: 'Anteeks, mä en ymmärtäny.', en: "Sorry, I didn't understand." },
  { fi: 'Voitsä sanoo sen uudestaan?', en: 'Can you say that again?' },
  { fi: 'Miten se sanotaan suomeks?', en: 'How do you say that in Finnish?' }
]
const hints = computed(() => (scenario.value ? SCENARIO_HINTS : OPEN_HINTS))
const showHints = ref(false)
function useHint(h) {
  draft.value = h.fi
  showHints.value = false
  inputRef.value?.focus()
}

// Scenario mode: metadata for the situation named in ?scenario=, or null for
// Väinö's free-form bench. Fetched from the catalog so prompts and openers
// have one source of truth (the backend).
const scenario = ref(null)
const missionDone = ref(false)
// First-completion XP reward, shown in the done mission strip.
const xpGained = ref(0)

const personaName = computed(() => scenario.value?.persona ?? 'Väinö')
// Female personas (Marja, Liisa, ...) speak with the female TTS voice.
const personaVoice = computed(() => scenario.value?.voice ?? 'male')
// Emoji stands in wherever a portrait image is missing or fails to load.
const personaEmoji = computed(() => scenario.value?.emoji ?? '🧔')

// Scenario art: character portrait + scene backdrop (see utils/sceneArt.js).
const art = computed(() => (scenario.value ? SCENE_ART[scenario.value.id] : null))
const charOk = ref(true)

// The portrait shown in avatars/side panel, with per-mode load-failure flags.
const portraitSrc = computed(() => (scenario.value ? art.value?.character : AVATAR_URL))
const portraitOk = computed(() => (scenario.value ? charOk.value && !!art.value : avatarOk.value))
function portraitFailed() {
  if (scenario.value) charOk.value = false
  else avatarOk.value = false
}

// The scene backdrop: each scenario brings its own artwork, and Väinö's
// free-form bench gets the sauna interior. The dark gradient keeps the
// panel readable on top of any artwork; if the image ever fails to load,
// the CSS plank gradient underneath still gives a sauna-dark room.
const sceneStyle = computed(() => ({
  backgroundImage: `linear-gradient(rgba(16, 9, 5, 0.5), rgba(14, 8, 4, 0.78)), url('${art.value?.background ?? '/scenes/sauna.jpg'}')`,
  backgroundSize: 'cover',
  backgroundPosition: 'center'
}))

const messages = ref([])
const draft = ref('')
const sending = ref(false)
// Re-keyed on every Väinö reply → one steam burst per reply (löyly!).
const burst = ref(0)
const showTranslation = ref({}) // index → bool (explicit per-message override)
const listening = ref(false)
const listRef = ref(null)
const inputRef = ref(null)

// Beginner mode: show the English under every one of Väinö's lines without
// tapping. Sticky across visits - a learner who needs it, needs it every day.
const AUTO_EN_STORE = 'ss_chat_auto_en'
const autoTranslate = ref(localStorage.getItem(AUTO_EN_STORE) === '1')
watch(autoTranslate, (v) => {
  try {
    localStorage.setItem(AUTO_EN_STORE, v ? '1' : '0')
  } catch {
    // storage blocked - the toggle just won't persist
  }
})

// A message shows its English when auto-translate says so, unless the learner
// explicitly toggled that message the other way.
function enShown(i) {
  return autoTranslate.value ? showTranslation.value[i] !== false : showTranslation.value[i] === true
}
function toggleEn(i) {
  showTranslation.value[i] = !enShown(i)
}

// Tap-a-word: every word in Väinö's bubbles is speakable on its own -
// hearing the pieces is how a sentence stops being a wall of sound.
const tokenize = (text) => text.split(/(\s+)/)
const isWord = (t) => /\p{L}/u.test(t)
function speakWord(token) {
  const clean = token.replace(/[^\p{L}\p{N}'\-]+/gu, '')
  if (clean) playSpoken(clean, null, personaVoice.value)
}

// Slow playback for shadowing: same native voice, unhurried.
const SLOW_RATE = 0.65

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
      messages: messages.value.map(({ role, content }) => ({ role, content })),
      scenario: scenario.value?.id ?? null
    })

    // Attach the character's gentle correction to the message it corrects.
    if (data.correction) {
      messages.value[messages.value.length - 1].correction = data.correction
    }
    messages.value.push({ role: 'assistant', content: data.reply, translation: data.translation })
    if (scenario.value && data.goal_reached && !missionDone.value) {
      missionDone.value = true
      window.umami?.track('scenario_complete', { scenario: scenario.value.id })
      // Persist the ✓ and collect the first-completion XP (0 on replays).
      api.post(`/scenarios/${scenario.value.id}/complete`)
        .then(({ data: d }) => {
          xpGained.value = d.xp_gained ?? 0
          if (auth.user && typeof d.xp === 'number') auth.user.xp = d.xp
        })
        .catch(() => {})
    }
    burst.value = Date.now()
    playSpoken(data.reply, null, personaVoice.value)
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
  missionDone.value = false
  xpGained.value = 0
  showTranslation.value = {}
  localStorage.removeItem(CHAT_STORE)
  messages.value = scenario.value
    ? [{ role: 'assistant', content: scenario.value.opener, translation: scenario.value.opener_translation }]
    : [randomOpener()]
}

// New-chat button: a real conversation deserves a second tap before it's
// wiped (no dialog - the button itself turns into the confirmation).
const confirmReset = ref(false)
let confirmTimer = null
function newChat() {
  if (messages.value.length <= 1) {
    reset()
    return
  }
  if (!confirmReset.value) {
    confirmReset.value = true
    clearTimeout(confirmTimer)
    confirmTimer = setTimeout(() => (confirmReset.value = false), 2500)
    return
  }
  clearTimeout(confirmTimer)
  confirmReset.value = false
  reset()
}
onBeforeUnmount(() => clearTimeout(confirmTimer))

watch(messages, saveChat, { deep: true })

const full = () => messages.value.length >= MAX_TURNS
// A quiet heads-up when the conversation is close to its cap.
const turnsLeft = computed(() => MAX_TURNS - messages.value.length)
const lowTurns = computed(() => turnsLeft.value > 0 && turnsLeft.value <= 6)
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
        <span class="locked-badge"><Lock class="lb-ico" aria-hidden="true" /> Löyly+</span>
        <h1>Väinö's bench</h1>
        <p class="muted locked-lead">
          Free-form chatting with a patient old Finn - the fastest way to find the
          gaps the drills can't reach.
        </p>

        <ul class="locked-perks">
          <li><MessageCircle class="lp-icon" aria-hidden="true" /><span>Real yleispuhekieli, kept at your level</span></li>
          <li><Brain class="lp-icon" aria-hidden="true" /><span>Gentle corrections that explain the why</span></li>
          <li><Drama class="lp-icon" aria-hidden="true" /><span>Situations: real-life missions like buying groceries</span></li>
        </ul>

        <div class="locked-actions">
          <router-link to="/upgrade" class="btn btn-primary loyly-btn"><LoylyIcon class="lb-ico" aria-hidden="true" /> See Löyly+</router-link>
          <router-link to="/dashboard" class="btn btn-ghost">Back to learning</router-link>
        </div>

        <p class="locked-free muted">The whole learning path stays free, forever.</p>
      </div>
    </div>
  </div>

  <div v-else class="chat sauna-scene" :style="sceneStyle">
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

    <!-- The character beside the chat (desktop): Väinö on his bench, or the
         scenario persona as their scene emoji -->
    <aside class="stage">
      <img
        v-if="portraitOk"
        class="stage-portrait"
        :src="portraitSrc"
        :alt="scenario ? personaName : 'Väinö sitting on the sauna bench with a ladle'"
        @error="portraitFailed"
      />
      <span v-else class="stage-emoji">{{ personaEmoji }}</span>
      <p class="stage-name">{{ personaName }}</p>
      <p class="stage-sub">{{ scenario ? scenario.title : 'speaks real puhekieli' }}</p>
      <p v-if="!scenario" class="stage-tip">tap a word in his bubbles to hear it</p>
    </aside>

    <!-- the chat panel -->
    <section class="chat-panel">
      <header class="panel-head">
        <span class="avatar">
          <img
            v-if="portraitOk"
            :src="portraitSrc"
            :alt="personaName"
            @error="portraitFailed"
          />
          <span v-else class="avatar-fallback">{{ personaEmoji }}</span>
        </span>
        <div class="head-id">
          <p class="who">{{ personaName }}</p>
          <p class="who-sub">{{ scenario ? scenario.title : 'puhekieli · tap a word to hear it' }}</p>
        </div>
        <div class="head-actions">
          <button
            class="hbtn"
            :class="{ on: autoTranslate }"
            :title="autoTranslate ? 'Hide English (tap 文A under a message for one-offs)' : 'Always show English under replies'"
            :aria-pressed="autoTranslate"
            aria-label="Always show English translations"
            @click="autoTranslate = !autoTranslate"
          ><Languages class="hbtn-ico" aria-hidden="true" /></button>
          <button
            class="hbtn"
            :class="{ warn: confirmReset }"
            :title="confirmReset ? 'Tap again to start over' : 'Start a new chat'"
            aria-label="Start a new chat"
            @click="newChat"
          ><RotateCcw class="hbtn-ico" aria-hidden="true" /></button>
        </div>
      </header>

      <!-- The mission strip: what to accomplish, flipping to done. -->
      <div v-if="scenario" class="mission" :class="{ done: missionDone }">
        <span class="mission-icon">
          <CircleCheck v-if="missionDone" class="mi-ico" aria-hidden="true" />
          <Target v-else class="mi-ico" aria-hidden="true" />
        </span>
        <p class="mission-text">{{ missionDone ? 'Mission accomplished!' : scenario.mission }}</p>
        <span v-if="missionDone && xpGained" class="mission-xp">+{{ xpGained }} XP</span>
        <router-link v-if="missionDone" to="/scenarios" class="mission-next">Next situation ›</router-link>
      </div>

      <div ref="listRef" class="bubbles">
        <template v-for="(m, i) in messages" :key="i">
          <div class="row" :class="m.role">
            <span v-if="m.role === 'assistant'" class="avatar small">
              <img v-if="portraitOk" :src="portraitSrc" alt="" @error="portraitFailed" />
              <span v-else class="avatar-fallback">{{ personaEmoji }}</span>
            </span>

            <div class="msg" :class="m.role">
              <div class="bubble" :class="m.role">
                <!-- Väinö's words, one tappable piece at a time -->
                <p v-if="m.role === 'assistant'" class="bubble-text">
                  <template v-for="(t, wi) in tokenize(m.content)" :key="wi">
                    <button
                      v-if="isWord(t)"
                      class="w"
                      type="button"
                      :title="`Hear “${t}”`"
                      @click.stop="speakWord(t)"
                    >{{ t }}</button>
                    <template v-else>{{ t }}</template>
                  </template>
                </p>
                <p v-else class="bubble-text">{{ m.content }}</p>

                <p v-if="m.role === 'assistant' && enShown(i) && m.translation" class="bubble-translation">
                  {{ m.translation }}
                </p>
              </div>

              <!-- listen / listen slowly / English - the teaching toolbar -->
              <div v-if="m.role === 'assistant'" class="msg-tools">
                <button class="tool" title="Listen" aria-label="Listen" @click="playSpoken(m.content, null, personaVoice)">
                  <Volume2 class="tool-ico" aria-hidden="true" />
                </button>
                <button class="tool" title="Listen slowly" aria-label="Listen slowly" @click="playSpoken(m.content, SLOW_RATE, personaVoice)">
                  <Turtle class="tool-ico" aria-hidden="true" />
                </button>
                <button
                  v-if="m.translation"
                  class="tool"
                  :class="{ on: enShown(i) }"
                  :title="enShown(i) ? 'Hide English' : 'Show English'"
                  :aria-pressed="enShown(i)"
                  aria-label="Toggle English translation"
                  @click="toggleEn(i)"
                >
                  <Languages class="tool-ico" aria-hidden="true" />
                </button>
              </div>

              <!-- the coach card: their sentence, said better -->
              <div v-if="m.correction" class="coach">
                <p class="coach-head"><Sparkles class="coach-ico" aria-hidden="true" /> A better way to say it</p>
                <p class="coach-text">{{ m.correction }}</p>
                <div class="coach-foot">
                  <button class="coach-listen" @click="playSpoken(m.correction, null, personaVoice)">
                    <Volume2 class="coach-listen-ico" aria-hidden="true" /> Listen
                  </button>
                  <span class="coach-saved" title="This correction is now a flashcard - it comes back for review before you'd slip again">
                    <BookmarkCheck class="coach-ico" aria-hidden="true" /> Saved for review
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>

        <div v-if="sending" class="row assistant">
          <span class="avatar small">
            <img v-if="portraitOk" :src="portraitSrc" alt="" @error="portraitFailed" />
            <span v-else class="avatar-fallback">{{ personaEmoji }}</span>
          </span>
          <div class="msg assistant">
            <div class="bubble assistant typing">
              {{ personaName }} miettii
              <span class="tdots"><span class="tdot"></span><span class="tdot"></span><span class="tdot"></span></span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="full()" class="full-note">
        <p>The löyly ran out - great chat! 🧖</p>
        <button class="scene-btn" @click="reset"><RotateCcw class="scene-btn-ico" aria-hidden="true" /> Start a fresh chat</button>
      </div>

      <div v-else class="dock">
        <p v-if="lowTurns" class="turns-note">The löyly is running low - {{ turnsLeft }} messages left</p>

        <div v-if="showHints" class="hint-row">
          <button v-for="h in hints" :key="h.fi" class="hint-chip" @click="useHint(h)">
            <span class="hint-fi">{{ h.fi }}</span>
            <span class="hint-en">{{ h.en }}</span>
          </button>
        </div>

        <div class="composer">
          <button
            class="cbtn"
            :class="{ on: showHints }"
            :title="showHints ? 'Hide hints' : 'Stuck? Get a line to say'"
            :aria-pressed="showHints"
            aria-label="Toggle reply hints"
            @click="showHints = !showHints"
          ><Lightbulb class="cbtn-ico" aria-hidden="true" /></button>
          <button
            v-if="micSupported"
            class="cbtn"
            :class="{ listening }"
            :title="listening ? 'Listening… tap to stop' : 'Say it in Finnish'"
            :aria-label="listening ? 'Stop listening' : 'Speak in Finnish'"
            @click="listening ? stopListening() : startListening()"
          ><Ear v-if="listening" class="cbtn-ico" aria-hidden="true" /><Mic v-else class="cbtn-ico" aria-hidden="true" /></button>
          <input
            ref="inputRef"
            v-model="draft"
            type="text"
            class="chat-input"
            :placeholder="listening ? 'Listening…' : 'Sano jotain suomeks…'"
            autocapitalize="none"
            autocomplete="off"
            spellcheck="false"
            @keyup.enter="send"
          />
          <button class="send" :disabled="!draft.trim() || sending" aria-label="Send" @click="send">
            <Send class="send-ico" aria-hidden="true" />
          </button>
        </div>
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
  display: inline-flex;
  align-items: center;
  gap: 5px;
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
.lp-icon { width: 17px; height: 17px; color: var(--accent); flex-shrink: 0; }
.lb-ico { width: 13px; height: 13px; flex-shrink: 0; }
.loyly-btn { display: inline-flex; align-items: center; gap: 6px; justify-content: center; }

.locked-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px; }
.locked-free { font-size: 12px; margin-top: 2px; }

/* stack Väinö above the pitch when the card gets narrow */
@media (max-width: 560px) {
  .locked-card { flex-direction: column; gap: 16px; padding: 24px 20px; text-align: center; }
  .locked-body { align-items: center; text-align: center; }
  .locked-actions { width: 100%; }
  .locked-actions .btn { flex: 1; }
}

/* ============================================================
   The sauna room. Always dusk in here regardless of app theme:
   the scene photography is dark, so the panel styles below use
   scene-local colors, not the app tokens.
   ============================================================ */
.sauna-scene {
  /* scene-local palette */
  --scene-ink: #f5ead8;
  --scene-ink-dim: rgba(245, 234, 216, 0.62);
  --scene-ink-faint: rgba(245, 234, 216, 0.42);
  --scene-line: rgba(245, 234, 216, 0.13);
  --scene-glass: rgba(15, 9, 5, 0.68);
  --scene-glass-deep: rgba(10, 6, 3, 0.6);
  --bubble-ink: #2b1c10;

  position: relative;
  overflow: hidden;
  padding: 12px;
  justify-content: center;
  /* dim cedar interior: warm dark planks (shows if the photo fails) */
  background:
    repeating-linear-gradient(
      180deg,
      rgba(255, 255, 255, 0.02) 0 46px,
      rgba(0, 0, 0, 0.22) 46px 49px
    ),
    linear-gradient(180deg, #241812 0%, #2d1c12 55%, #38200f 100%);
}

/* ---- the character on stage (left side, desktop only) ---- */
.stage {
  display: none;
  position: relative;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  width: 270px;
  flex-shrink: 0;
  padding: 0 12px 26px;
  text-align: center;
}
.stage-portrait {
  width: 220px;
  height: 220px;
  filter: drop-shadow(0 18px 26px rgba(0, 0, 0, 0.55));
  animation: breathe 5s ease-in-out infinite;
}
.stage-emoji {
  font-size: 130px;
  line-height: 1;
  filter: drop-shadow(0 18px 26px rgba(0, 0, 0, 0.55));
  animation: breathe 5s ease-in-out infinite;
}
@keyframes breathe {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
@media (prefers-reduced-motion: reduce) {
  .stage-portrait, .stage-emoji { animation: none; }
}
.stage-name {
  margin-top: 14px;
  font-size: 21px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: var(--scene-ink);
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}
.stage-sub {
  margin-top: 3px;
  max-width: 220px;
  font-size: 13px;
  line-height: 1.45;
  color: var(--scene-ink-dim);
  text-shadow: 0 1px 6px rgba(0, 0, 0, 0.5);
}
.stage-tip {
  margin-top: 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--scene-ink-faint);
  background: var(--scene-glass-deep);
  border: 1px solid var(--scene-line);
  border-radius: var(--radius-pill);
  padding: 5px 12px;
  backdrop-filter: blur(6px);
}

/* ---- the chat panel ---- */
.chat-panel {
  position: relative;
  flex: 1;
  min-width: 0;
  max-width: 820px;
  /* Without this the panel refuses to shrink below its content in the
     mobile column layout, so .bubbles never scrolls and the composer
     gets clipped by the scene's overflow: hidden. */
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--scene-glass);
  border: 1px solid var(--scene-line);
  border-radius: 22px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(18px);
  overflow: hidden;
}

/* side-by-side once there's room */
@media (min-width: 760px) {
  .sauna-scene { flex-direction: row; gap: 14px; padding: 20px; }
  .stage { display: flex; }
}

/* Below 900px the fixed tab bar overlays the bottom edge: pad the scene so
   the composer clears it (the sauna glow still shows through the bar's blur).
   The scene starts at the very top, so clear the notch too. */
@media (max-width: 899px) {
  .sauna-scene {
    padding-top: calc(12px + env(safe-area-inset-top, 0px));
    padding-bottom: calc(82px + env(safe-area-inset-bottom, 0px));
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
  .wisp, .kiuas-glow, .fog, .bubble { animation: none; }
  .loyly { display: none; }
}

/* ---- panel header ---- */
.panel-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--scene-line);
  background: rgba(0, 0, 0, 0.18);
}
.head-id { flex: 1; min-width: 0; }
.avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid rgba(245, 158, 11, 0.45);
  background: rgba(245, 158, 11, 0.12);
  display: grid;
  place-items: center;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-fallback { font-size: 22px; }
.avatar.small { width: 30px; height: 30px; border-width: 1px; align-self: flex-end; }
.avatar.small .avatar-fallback { font-size: 15px; }
.who {
  font-weight: 800;
  font-size: 15.5px;
  color: var(--scene-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.who-sub {
  font-size: 12px;
  color: var(--scene-ink-dim);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.head-actions { display: flex; gap: 6px; flex-shrink: 0; }
.hbtn {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  background: rgba(245, 234, 216, 0.07);
  border: 1px solid var(--scene-line);
  border-radius: 10px;
  color: var(--scene-ink-dim);
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}
.hbtn:hover { color: var(--scene-ink); border-color: rgba(245, 234, 216, 0.3); }
.hbtn.on {
  color: var(--accent);
  background: var(--accent-soft);
  border-color: rgba(245, 158, 11, 0.45);
}
.hbtn.warn {
  color: #fca5a5;
  background: rgba(248, 113, 113, 0.14);
  border-color: rgba(248, 113, 113, 0.5);
  animation: warn-pulse 1.2s ease-in-out infinite;
}
@keyframes warn-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.3); }
  50% { box-shadow: 0 0 0 6px rgba(248, 113, 113, 0); }
}
.hbtn-ico { width: 17px; height: 17px; }

/* ---- the mission strip ---- */
.mission {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(245, 158, 11, 0.13);
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 12px;
  padding: 8px 12px;
  margin: 10px 12px 0;
}
.mission.done {
  background: rgba(52, 211, 153, 0.15);
  border-color: rgba(52, 211, 153, 0.45);
}
.mission-icon {
  flex-shrink: 0;
  width: 26px;
  height: 26px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: rgba(245, 158, 11, 0.22);
  color: #fbbf58;
}
.mission.done .mission-icon { background: rgba(52, 211, 153, 0.22); color: #7ce6b8; }
.mi-ico { width: 14px; height: 14px; }
.mission-text { flex: 1; min-width: 0; font-size: 13px; font-weight: 700; color: var(--scene-ink); line-height: 1.35; }
.mission-xp {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 800;
  color: #1a1204;
  background: linear-gradient(180deg, #fcc860, #f59e0b);
  border-radius: var(--radius-pill, 999px);
  padding: 3px 10px;
  white-space: nowrap;
  animation: xp-pop 0.5s cubic-bezier(0.34, 1.5, 0.64, 1);
}
@keyframes xp-pop {
  from { opacity: 0; transform: scale(0.4); }
  to { opacity: 1; transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) {
  .mission-xp { animation: none; }
}
.mission-next {
  flex-shrink: 0;
  font-size: 12.5px;
  font-weight: 800;
  color: #7ce6b8;
  white-space: nowrap;
}

/* ---- bubbles ---- */
.bubbles {
  position: relative;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 14px 14px 10px;
  scrollbar-width: thin;
  scrollbar-color: rgba(245, 234, 216, 0.2) transparent;
}
.row { display: flex; align-items: flex-end; gap: 8px; }
.row.user { justify-content: flex-end; }

.msg {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 82%;
  min-width: 0;
  align-items: flex-start;
}
.msg.user { align-items: flex-end; }

.bubble {
  position: relative;
  padding: 11px 14px;
  line-height: 1.5;
  animation: msg-in 0.3s var(--ease, cubic-bezier(0.22, 1, 0.36, 1));
}
@keyframes msg-in {
  from { opacity: 0; transform: translateY(10px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.bubble.assistant {
  background: #fbf8f2;
  color: var(--bubble-ink);
  border-radius: 18px 18px 18px 6px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.28);
}
.bubble.user {
  background: linear-gradient(135deg, #fcc356, #f0900d);
  color: #201302;
  border-radius: 18px 18px 6px 18px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.28);
}

.bubble-text { font-size: 15px; font-weight: 600; overflow-wrap: anywhere; }

/* tappable words inside Väinö's bubbles */
.w {
  background: none;
  border: none;
  padding: 0 1px;
  margin: 0;
  font: inherit;
  color: inherit;
  cursor: pointer;
  border-radius: 5px;
  transition: background 0.12s ease;
}
.w:hover { background: rgba(240, 144, 13, 0.2); }
.w:active { background: rgba(240, 144, 13, 0.35); }
.w:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }

.bubble-translation {
  font-size: 13px;
  font-weight: 500;
  margin-top: 7px;
  padding-top: 7px;
  border-top: 1px dashed rgba(43, 28, 16, 0.28);
  color: rgba(43, 28, 16, 0.72);
}

/* listen / slow / English under each of Väinö's bubbles */
.msg-tools { display: flex; gap: 5px; padding-left: 4px; }
.tool {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  background: rgba(245, 234, 216, 0.08);
  border: 1px solid var(--scene-line);
  border-radius: 9px;
  color: var(--scene-ink-dim);
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}
.tool:hover { color: var(--scene-ink); border-color: rgba(245, 234, 216, 0.32); }
.tool.on {
  color: var(--accent);
  background: var(--accent-soft);
  border-color: rgba(245, 158, 11, 0.45);
}
.tool-ico { width: 14px; height: 14px; }

/* ---- the coach card (a correction, said kindly) ---- */
.coach {
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 14px;
  padding: 10px 12px;
  backdrop-filter: blur(8px);
  animation: msg-in 0.3s var(--ease, cubic-bezier(0.22, 1, 0.36, 1));
}
.coach-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #fbbf58;
}
.coach-ico { width: 13px; height: 13px; flex-shrink: 0; }
.coach-text { font-size: 14.5px; font-weight: 700; line-height: 1.45; color: var(--scene-ink); overflow-wrap: anywhere; }
.coach-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.coach-listen {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(245, 158, 11, 0.18);
  border: 1px solid rgba(245, 158, 11, 0.4);
  color: #fbbf58;
  border-radius: var(--radius-pill);
  padding: 4px 11px;
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease;
}
.coach-listen:hover { background: rgba(245, 158, 11, 0.3); }
.coach-listen-ico { width: 12px; height: 12px; }
.coach-saved {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: var(--scene-ink-faint);
  white-space: nowrap;
}

/* ---- typing indicator ---- */
.bubble.typing {
  font-style: italic;
  font-size: 14px;
  color: rgba(43, 28, 16, 0.6);
  display: flex;
  align-items: center;
  gap: 8px;
}
/* thinking = little steam puffs rising */
.tdots { display: inline-flex; gap: 4px; align-items: flex-end; }
.tdot {
  width: 6px;
  height: 6px;
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

/* ---- end of chat ---- */
.full-note {
  flex-shrink: 0;
  text-align: center;
  padding: 14px 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
  border-top: 1px solid var(--scene-line);
}
.full-note p { font-size: 14px; font-weight: 600; color: var(--scene-ink-dim); }
.scene-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(245, 234, 216, 0.1);
  border: 1px solid rgba(245, 234, 216, 0.28);
  color: var(--scene-ink);
  border-radius: var(--radius-pill);
  padding: 10px 20px;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.scene-btn:hover { background: rgba(245, 234, 216, 0.18); border-color: var(--accent); }
.scene-btn-ico { width: 15px; height: 15px; }

/* ---- the dock: hints + composer ---- */
.dock { flex-shrink: 0; padding: 8px 12px 12px; display: flex; flex-direction: column; gap: 8px; }

.turns-note {
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: var(--scene-ink-faint);
}

.hint-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 8px;
  animation: msg-in 0.25s var(--ease, cubic-bezier(0.22, 1, 0.36, 1));
}
.hint-chip {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: left;
  background: var(--scene-glass-deep);
  border: 1px solid rgba(245, 234, 216, 0.2);
  border-radius: 12px;
  padding: 9px 12px;
  cursor: pointer;
  font-family: inherit;
  backdrop-filter: blur(6px);
  transition: border-color 0.15s ease, background 0.15s ease;
}
.hint-chip:hover { border-color: var(--accent); background: rgba(20, 12, 6, 0.75); }
.hint-fi { color: var(--scene-ink); font-size: 13.5px; font-weight: 700; }
.hint-en { color: var(--scene-ink-dim); font-size: 11.5px; }

.composer {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--scene-glass-deep);
  border: 1px solid rgba(245, 234, 216, 0.18);
  border-radius: var(--radius-pill);
  padding: 6px;
  backdrop-filter: blur(10px);
  transition: border-color 0.15s ease;
}
.composer:focus-within { border-color: rgba(245, 158, 11, 0.55); }

.cbtn {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  background: none;
  border: none;
  border-radius: 50%;
  color: var(--scene-ink-dim);
  cursor: pointer;
  transition: color 0.15s ease, background 0.15s ease;
}
.cbtn:hover { color: var(--scene-ink); background: rgba(245, 234, 216, 0.08); }
.cbtn.on { color: var(--accent); background: var(--accent-soft); }
.cbtn.listening {
  color: var(--accent);
  background: var(--accent-soft);
  animation: mic-pulse 1.2s ease-in-out infinite;
}
@keyframes mic-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.35); }
  50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
}
.cbtn-ico { width: 18px; height: 18px; }

.chat-input {
  flex: 1;
  min-width: 0;
  background: none;
  border: none;
  padding: 9px 6px;
  font-size: 16px;
  font-family: inherit;
  color: var(--scene-ink);
  outline: none;
}
.chat-input::placeholder { color: var(--scene-ink-faint); }

.send {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  border: none;
  border-radius: 50%;
  color: var(--accent-contrast);
  cursor: pointer;
  box-shadow: var(--shadow-accent);
  transition: transform 0.08s ease, filter 0.15s ease, opacity 0.15s ease;
}
.send:hover:not(:disabled) { filter: brightness(1.08); }
.send:active:not(:disabled) { transform: scale(0.94); }
.send:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }
.send-ico { width: 17px; height: 17px; margin-left: -1px; }
</style>
