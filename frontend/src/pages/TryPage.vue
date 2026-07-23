<script setup>
// Guest "try it now" taste - no account needed. Five real sentences, each one
// an ear test: hear it, commit to what you heard, then get the meaning and the
// textbook form. Ends on a score and the signup invite.
// Self-contained sample content so it works without the backend/auth.
import { ref, computed, onMounted } from 'vue'
import { BookOpen, EyeOff, Flame, Lightbulb, Mic, Turtle, Volume2 } from 'lucide-vue-next'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { TRY_SAMPLES } from '../utils/trySamples'

const { playSentence } = useFinnishAudio()

// Card content lives in utils/ so its decoy quality is unit-testable - see
// trySamples.test.js, which fails if a decoy ever becomes guessable by shape.
const samples = TRY_SAMPLES

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
// Which option the visitor tapped on this card (null = not yet).
const picked = ref(null)
// Whether this card's audio has been played. Cards 2+ set it from next(), which
// runs inside the tap that advanced them - so only card 1 makes the visitor
// press play, and it has to, because a fresh navigation carries no user
// activation and the browser would refuse to autoplay anyway. The options stay
// hidden until then: the first thing a visitor does here is HEAR Finnish, not
// read it. Card 1 was previously the only silent card in the whole demo.
const heard = ref(false)
const score = ref(0)

const current = computed(() => samples[index.value])
// True once the upgrade above swapped in an approved human recording.
const nativeAudio = computed(() => !!current.value.audio?.startsWith('/audio/human/'))
const isLast = computed(() => index.value === samples.length - 1)
const pickedRight = computed(() => picked.value === current.value.fi)
// Card 1 pits spoken against written; the rest pit two spoken sentences against
// each other, so a miss there means "heard a different sentence", not "reached
// for the textbook". The wrong-answer copy has to say the right thing.
const decoyIsWritten = computed(() => current.value.options.includes(current.value.book))
const done = ref(false)

function play(rate = null) {
  heard.value = true
  playSentence(current.value.fi, current.value.audio, rate)
}

// Escape hatch for silent playback - the Facebook in-app browser is a large
// slice of this page's traffic and its audio is unreliable. Without this a
// visitor whose sound never fires is stuck on a card with nothing to tap.
// Skipping forfeits the point rather than guessing on the visitor's behalf.
function revealWithoutGuessing() {
  heard.value = true
  revealed.value = true
}

function pick(option) {
  if (picked.value) return
  picked.value = option
  revealed.value = true
  if (option === current.value.fi) score.value++
}

function next() {
  if (isLast.value) {
    done.value = true
    // Mid-funnel step between try_start and register. The score rides along so
    // the drop-off can be read against how well people actually did.
    window.umami?.track('try_complete', { score: `${score.value}/${samples.length}` })
  } else {
    index.value++
    revealed.value = false
    picked.value = null
    heard.value = false
    // Fired on ENTERING each card, so the counts are a drop-off curve: card 1
    // is try_start, and 250 try_start against 80 try_complete used to be the
    // whole picture, with no way to tell which card lost them.
    window.umami?.track('try_card', { index: index.value + 1 })
    play()
  }
}
</script>

<template>
  <div class="try">
    <template v-if="!done">
      <div class="try-top">
        <router-link to="/" class="skip">‹ Home</router-link>
        <div class="dots-wrap">
          <div class="dots">
            <span v-for="(s, i) in samples" :key="i" class="dot" :class="{ active: i <= index }"></span>
          </div>
          <!-- Appears only once there's something to show: a 0 on card one reads
               as failure before the visitor has had a chance to hear anything. -->
          <p v-if="score" class="score-chip">{{ score }} caught 👂</p>
        </div>
        <!-- Skipping the taste = ready to start: that's the register page. -->
        <router-link to="/register" class="skip">Skip</router-link>
      </div>

      <p class="kicker">Can you catch it? <Flame class="kicker-ico" aria-hidden="true" /></p>

      <div class="card sample-card">
        <!-- Card 1 before its first play: one affordance, nothing to read. -->
        <div v-if="!heard" class="listen-gate">
          <button class="listen-big" @click="play()">
            <Volume2 class="listen-big-ico" aria-hidden="true" />
            Tap to hear it
          </button>
          <p class="listen-hint">Real spoken Finnish, at natural speed.</p>
        </div>

        <template v-else>
          <div class="audio-row">
            <button class="audio" @click="play()" aria-label="Play audio"><Volume2 class="audio-ico" aria-hidden="true" /> Again</button>
            <button class="audio slow" @click="play(0.65)" aria-label="Play audio slowly" title="Play slowly"><Turtle class="audio-ico" aria-hidden="true" /> Slow</button>
          </div>
          <p v-if="nativeAudio" class="native-note"><Mic class="note-ico" aria-hidden="true" /> recorded by a native Finnish speaker</p>

          <!-- The ear test: the sentence stays hidden until the visitor commits -->
          <p class="pick-q">Which one did you hear?</p>
          <div class="pick-row">
            <button
              v-for="o in current.options"
              :key="o"
              class="pick-opt"
              :class="{ right: revealed && o === current.fi, wrong: revealed && picked === o && o !== current.fi }"
              :disabled="revealed"
              @click="pick(o)"
            >{{ o }}</button>
          </div>
          <p v-if="revealed && picked" class="pick-verdict" :class="{ good: pickedRight }">
            <template v-if="pickedRight">Your ear caught it! 👂 That instinct is exactly what we train.</template>
            <template v-else-if="decoyIsWritten">You picked the textbook form - but what you heard was the spoken one. That gap is exactly what we train.</template>
            <template v-else>Close - that's a real sentence too, just not this one. Telling them apart at speed is exactly what we train.</template>
          </p>
        </template>

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

      <button v-if="revealed" class="btn btn-primary btn-block" @click="next">
        {{ isLast ? 'See how it works →' : 'Next sentence →' }}
      </button>
      <!-- Only while a guess is still possible: once revealed there's nothing
           left to escape from, and on the gate it would compete with pressing play. -->
      <button v-else-if="heard" class="no-sound" @click="revealWithoutGuessing">
        <EyeOff class="audio-ico" aria-hidden="true" /> No sound? Show the answer
      </button>
    </template>

    <template v-else>
      <div class="finish">
        <img class="finish-icon" src="/vaino-wave.png" alt="Väinö waving hello" />
        <h1>{{ score ? `You caught ${score} of ${samples.length}.` : "That's spoken Finnish." }}</h1>
        <p class="finish-affirm">
          <!-- Both readings sell the same thing: the ear is trainable. A perfect
               score means the instinct is already there, a low one means the gap
               is real and measurable - neither is a reason to stop. -->
          <template v-if="score === samples.length">
            Every one, by ear. That instinct is what most textbook learners are
            missing - you already have it, and the course sharpens it.
          </template>
          <template v-else-if="score">
            You just heard the Finnish textbooks skip - and told it apart from the
            written form {{ score }} {{ score === 1 ? 'time' : 'times' }} without ever having studied it.
          </template>
          <template v-else>
            You just heard {{ samples.length }} sentences of the Finnish textbooks
            skip - the everyday Finnish people actually say to you.
          </template>
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
.try-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 28px; }
.skip { color: var(--text-dim); font-size: 14px; font-weight: 600; white-space: nowrap; }
.dots { display: flex; gap: 6px; }
/* Fixed height so the chip appearing after card 1 doesn't shove the card down. */
.dots-wrap { display: flex; flex-direction: column; align-items: center; gap: 7px; min-height: 30px; }
.score-chip { font-size: 12px; font-weight: 800; color: var(--green); white-space: nowrap; }
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

/* Card 1's opening state: a single tap target, deliberately large, with no
   sentence on screen to read instead of listening to. */
.listen-gate { padding: 14px 0 6px; }
.listen-big {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: var(--accent);
  color: var(--bg);
  border: none;
  border-radius: var(--radius-pill);
  padding: 18px 30px;
  font-family: inherit;
  font-size: 18px;
  font-weight: 800;
  cursor: pointer;
  transition: transform 0.08s ease, filter 0.15s ease;
}
.listen-big:hover { filter: brightness(1.06); }
.listen-big:active { transform: scale(0.98); }
.listen-big-ico { width: 22px; height: 22px; }
.listen-hint { font-size: 13.5px; color: var(--text-dim); margin-top: 14px; }

.no-sound {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
  background: none;
  border: none;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  padding: 12px;
  cursor: pointer;
}
.no-sound:hover { color: var(--text); }

/* the ear test */
.pick-q { font-size: 15px; font-weight: 800; margin-bottom: 12px; }
.pick-row { display: flex; flex-direction: column; gap: 8px; }
.pick-opt {
  background: var(--bg-soft);
  border: 2px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 13px 14px;
  font-family: inherit;
  font-size: 19px;
  font-weight: 700;
  color: var(--text);
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.pick-opt:hover:not(:disabled) { border-color: var(--accent); }
.pick-opt:disabled { cursor: default; }
.pick-opt.right { border-color: var(--green); background: var(--green-soft); }
.pick-opt.wrong { border-color: var(--red, #f87171); opacity: 0.75; }
.pick-verdict { font-size: 13.5px; font-weight: 600; color: var(--text-dim); margin-top: 12px; line-height: 1.5; }
.pick-verdict.good { color: var(--green); }

.finish { margin: auto 0; text-align: center; display: flex; flex-direction: column; gap: 14px; }
.finish-icon { width: 110px; height: 110px; margin: 0 auto; }
.finish h1 { font-size: 28px; }
.finish-affirm { font-size: 16.5px; font-weight: 700; color: var(--text); line-height: 1.55; }
.finish-text { color: var(--text-dim); font-size: 14.5px; line-height: 1.6; margin-bottom: 8px; }
.login-link { margin-top: 4px; }
.home-below { color: var(--text-dim); font-size: 13.5px; font-weight: 600; margin-top: 6px; }
</style>
