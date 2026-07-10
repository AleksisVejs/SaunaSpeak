<script setup>
// Guest "try it now" taste — no account needed. Runs three real sentences with
// listen + reveal, then invites signup to save progress. Self-contained sample
// content so it works without the backend/auth.
import { ref, computed } from 'vue'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playSentence } = useFinnishAudio()

const samples = [
  { fi: 'Moi! Mä oon Anna.', en: "Hi! I'm Anna.", note: '“Mä” is spoken Finnish for “minä” (I).', audio: '/audio/try-1.mp3' },
  { fi: 'Onks sul nälkä?', en: 'Are you hungry?', note: '“Onks” = “onko” (is), “sul” = “sinulla” (you have).', audio: '/audio/try-2.mp3' },
  { fi: 'Otetaanks kahvit?', en: 'Shall we grab a coffee?', note: '“-ks” turns a statement into a casual question.', audio: '/audio/try-3.mp3' }
]

const index = ref(0)
const revealed = ref(false)

const current = computed(() => samples[index.value])
const isLast = computed(() => index.value === samples.length - 1)
const done = ref(false)

function play() {
  playSentence(current.value.fi, current.value.audio)
}

function reveal() {
  revealed.value = true
}

function next() {
  if (isLast.value) {
    done.value = true
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
        <router-link to="/login" class="skip">Skip</router-link>
        <div class="dots">
          <span v-for="(s, i) in samples" :key="i" class="dot" :class="{ active: i <= index }"></span>
        </div>
      </div>

      <p class="kicker">Try a real sentence 🔥</p>

      <div class="card sample-card">
        <button class="audio" @click="play" aria-label="Play audio">🔊 Listen</button>
        <p class="fi">{{ current.fi }}</p>

        <transition name="fade">
          <div v-if="revealed" class="reveal">
            <p class="en">{{ current.en }}</p>
            <p class="note">💡 {{ current.note }}</p>
          </div>
        </transition>
      </div>

      <button v-if="!revealed" class="btn btn-ghost btn-block" @click="reveal">👁 Show meaning</button>
      <button v-else class="btn btn-primary btn-block" @click="next">
        {{ isLast ? 'See how it works →' : 'Next sentence →' }}
      </button>
    </template>

    <template v-else>
      <div class="finish">
        <img class="finish-icon" src="/vaino-wave.png" alt="Väinö waving hello" />
        <h1>That's spoken Finnish.</h1>
        <p class="finish-text">
          SaunaSpeak brings each sentence back at the right moment — listen, fill the gap,
          then say it from memory — so it actually sticks. Create a free account to keep your
          streak and unlock the full path.
        </p>
        <router-link to="/register" class="btn btn-primary btn-block">Create free account</router-link>
        <router-link to="/login" class="btn btn-ghost btn-block login-link">I already have an account</router-link>
      </div>
    </template>
  </div>
</template>

<style scoped>
.try {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  padding: max(20px, 5vh) 4px 28px;
}
.try-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.skip { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.dots { display: flex; gap: 6px; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: background 0.2s ease; }
.dot.active { background: var(--accent); }

.kicker { font-size: 13px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px; }

.sample-card { text-align: center; margin-bottom: 16px; }
.audio {
  background: var(--accent-soft);
  color: var(--accent);
  border: none;
  border-radius: var(--radius-pill);
  padding: 9px 18px;
  font-weight: 700;
  font-family: inherit;
  font-size: 14px;
  cursor: pointer;
  margin-bottom: 16px;
}
.fi { font-size: 26px; font-weight: 700; line-height: 1.35; }
.reveal { margin-top: 16px; }
.en { font-size: 17px; color: var(--text-dim); }
.note { font-size: 14px; color: var(--text); background: var(--bg-soft); border-radius: var(--radius-sm); padding: 10px 12px; margin-top: 12px; line-height: 1.5; }

.finish { margin: auto 0; text-align: center; display: flex; flex-direction: column; gap: 14px; }
.finish-icon { width: 110px; height: 110px; margin: 0 auto; }
.finish h1 { font-size: 28px; }
.finish-text { color: var(--text-dim); font-size: 16px; line-height: 1.6; margin-bottom: 8px; }
.login-link { margin-top: 4px; }
</style>
