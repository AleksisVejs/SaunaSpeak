<script setup>
// Guest "try it now" taste - no account needed. Runs six real sentences with
// listen + reveal (plus the textbook form for contrast), then invites signup.
// Self-contained sample content so it works without the backend/auth.
import { ref, computed, onMounted } from 'vue'
import { BookOpen, Eye, Flame, Lightbulb, Mic, Turtle, Volume2 } from 'lucide-vue-next'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playSentence } = useFinnishAudio()

// The audio files are the committed course MP3s (sentence ids from the seed
// order), so the demo voice is exactly the voice inside the app. On mount we
// ask the backend for each sentence's CURRENT audio - once a human recording
// is approved, the demo plays the human voice too (see tryAudio upgrade below).
const samples = [
  { fi: 'Moi! Mä oon Anna.', book: 'Hei! Minä olen Anna.', en: "Hi! I'm Anna.", note: '“Mä” is spoken Finnish for “minä” (I).', audio: '/audio/try-1.mp3' },
  { fi: 'Onks sul nälkä?', book: 'Onko sinulla nälkä?', en: 'Are you hungry?', note: '“Onks” = “onko” (is), “sul” = “sinulla” (you have).', audio: '/audio/try-2.mp3' },
  { fi: 'Otetaanks kahvit?', book: 'Otetaanko kahvit?', en: 'Shall we grab a coffee?', note: '“-ks” turns a statement into a casual question.', audio: '/audio/try-3.mp3' },
  { fi: 'Mitä kuuluu?', book: null, en: 'How are you?', note: 'Literally “what is heard?” - the everyday how-are-you.', audio: '/audio/sentence-4.mp3' },
  { fi: 'Emmä tiiä.', book: 'En minä tiedä.', en: "I don't know.", note: 'Three textbook words melt into two spoken ones - you\'ll hear this daily.', audio: '/audio/sentence-10.mp3' },
  { fi: 'Moikka, nähään!', book: 'Hei hei, nähdään!', en: 'Bye, see you!', note: '“Nähään” = “nähdään” - literally “we\'ll be seen”.', audio: '/audio/sentence-8.mp3' },
  // A taste from further down the path - slang the textbooks never touch.
  { fi: 'Nyt meni kyl överiks.', book: 'Nyt se meni kyllä liian pitkälle.', en: 'Now that went too far.', note: 'From further down the same course - “överiks” is Helsinki slang, borrowed from Swedish “över”. The path keeps going into Finnish the textbooks never touch.', audio: '/audio/sentence-369.mp3' }
]

// Upgrade the hardcoded MP3s to whatever the course currently plays for the
// same sentences (human takes once approved). Failure changes nothing - the
// committed files keep the page fully self-contained.
onMounted(async () => {
  // Funnel entry - the marketing plan's activation funnel is Visitors ->
  // try_start -> try_complete -> register (no-op in dev; script is domain-locked).
  window.umami?.track('try_start')
  try {
    const { data } = await api.get('/public/try-audio', {
      params: { texts: samples.map((s) => s.fi) }
    })
    for (const s of samples) {
      if (data.audio?.[s.fi]) s.audio = data.audio[s.fi]
    }
  } catch {
    /* offline or backend down: demo stays on the committed MP3s */
  }
})

const index = ref(0)
const revealed = ref(false)

const current = computed(() => samples[index.value])
// True once the upgrade above swapped in an approved human recording.
const nativeAudio = computed(() => !!current.value.audio?.startsWith('/audio/human/'))
const isLast = computed(() => index.value === samples.length - 1)
const done = ref(false)

function play(rate = null) {
  playSentence(current.value.fi, current.value.audio, rate)
}

function reveal() {
  revealed.value = true
}

function next() {
  if (isLast.value) {
    done.value = true
    // Reached the finish screen - the mid-funnel step between try_start and register.
    window.umami?.track('try_complete')
  } else {
    index.value++
    revealed.value = false
    play()
  }
}
</script>

<template>
  <div class="try">
    <template v-if="!done">
      <div class="try-top">
        <router-link to="/" class="skip">‹ Home</router-link>
        <div class="dots">
          <span v-for="(s, i) in samples" :key="i" class="dot" :class="{ active: i <= index }"></span>
        </div>
        <!-- Skipping the taste = ready to start: that's the register page. -->
        <router-link to="/register" class="skip">Skip</router-link>
      </div>

      <p class="kicker">Try a real sentence <Flame class="kicker-ico" aria-hidden="true" /></p>

      <div class="card sample-card">
        <div class="audio-row">
          <button class="audio" @click="play()" aria-label="Play audio"><Volume2 class="audio-ico" aria-hidden="true" /> Listen</button>
          <button class="audio slow" @click="play(0.65)" aria-label="Play audio slowly" title="Play slowly"><Turtle class="audio-ico" aria-hidden="true" /> Slow</button>
        </div>
        <p v-if="nativeAudio" class="native-note"><Mic class="note-ico" aria-hidden="true" /> recorded by a native Finnish speaker</p>
        <p class="fi">{{ current.fi }}</p>

        <!-- :duration guarantees the leave element is removed even if the tab
             is throttled and transition events never fire (stale text would
             otherwise linger for screen readers). -->
        <transition name="fade" :duration="200">
          <div v-if="revealed" class="reveal">
            <p class="en">{{ current.en }}</p>
            <!-- Plain text, no strikethrough: the written form isn't wrong, it's
                 the register of every sign, email and form - you need both. -->
            <p v-if="current.book" class="book"><BookOpen class="note-ico" aria-hidden="true" /> In writing: {{ current.book }}</p>
            <p class="note"><Lightbulb class="note-ico" aria-hidden="true" /> {{ current.note }}</p>
          </div>
        </transition>
      </div>

      <button v-if="!revealed" class="btn btn-ghost btn-block reveal-btn" @click="reveal"><Eye class="audio-ico" aria-hidden="true" /> Show meaning</button>
      <button v-else class="btn btn-primary btn-block" @click="next">
        {{ isLast ? 'See how it works →' : 'Next sentence →' }}
      </button>
    </template>

    <template v-else>
      <div class="finish">
        <img class="finish-icon" src="/vaino-wave.png" alt="Väinö waving hello" />
        <h1>That's spoken Finnish.</h1>
        <p class="finish-affirm">
          You just understood {{ samples.length }} sentences of the Finnish textbooks
          skip - the everyday Finnish people actually say to you.
        </p>
        <p class="finish-text">
          From here, each sentence comes back right when you'd forget it - listen, fill
          the gap, say it from memory - all the way to real conversations. Free, and a
          placement test skips you ahead if you already know some.
        </p>
        <router-link to="/register" class="btn btn-primary btn-block">Create free account</router-link>
        <router-link to="/login" class="btn btn-ghost btn-block login-link">I already have an account</router-link>
        <router-link to="/" class="home-below">‹ Back to the home page</router-link>
      </div>
    </template>
  </div>
</template>

<style scoped>
.try {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  padding: max(20px, 5vh) 4px 28px;
}
.try-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.skip { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.dots { display: flex; gap: 6px; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: background 0.2s ease; }
.dot.active { background: var(--accent); }

.kicker { font-size: 13px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 5px; }
.kicker-ico { width: 14px; height: 14px; }
.audio-ico { width: 15px; height: 15px; flex-shrink: 0; }
.note-ico { width: 12px; height: 12px; vertical-align: -2px; }
.reveal-btn { display: flex; align-items: center; justify-content: center; gap: 7px; }

.sample-card { text-align: center; margin-bottom: 16px; }
.audio-row { display: flex; justify-content: center; gap: 8px; margin-bottom: 16px; }
.audio {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--accent-soft);
  color: var(--accent);
  border: none;
  border-radius: var(--radius-pill);
  padding: 9px 18px;
  font-weight: 700;
  font-family: inherit;
  font-size: 14px;
  cursor: pointer;
}
.audio.slow { background: var(--bg-soft); color: var(--text-dim); }
.native-note { font-size: 12px; font-weight: 700; color: var(--green); margin: -8px 0 12px; }
.fi { font-size: 26px; font-weight: 700; line-height: 1.35; }
.reveal { margin-top: 16px; }
.en { font-size: 17px; color: var(--text-dim); }
.book { font-size: 13.5px; color: var(--text-dim); margin-top: 10px; }
.note { font-size: 14px; color: var(--text); background: var(--bg-soft); border-radius: var(--radius-sm); padding: 10px 12px; margin-top: 12px; line-height: 1.5; }

.finish { margin: auto 0; text-align: center; display: flex; flex-direction: column; gap: 14px; }
.finish-icon { width: 110px; height: 110px; margin: 0 auto; }
.finish h1 { font-size: 28px; }
.finish-affirm { font-size: 16.5px; font-weight: 700; color: var(--text); line-height: 1.55; }
.finish-text { color: var(--text-dim); font-size: 14.5px; line-height: 1.6; margin-bottom: 8px; }
.login-link { margin-top: 4px; }
.home-below { color: var(--text-dim); font-size: 13.5px; font-weight: 600; margin-top: 6px; }
</style>
