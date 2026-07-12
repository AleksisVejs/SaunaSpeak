<script setup>
// Public front door: the only page a search engine or a shared link shows.
// Sells the one thing competitors don't have - real SPOKEN Finnish - and
// funnels to /try (no account needed) or /register.
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playSentence } = useFinnishAudio()

// Spoken-vs-textbook contrast is the product in one glance.
const SAMPLES = [
  { fi: 'Mä oon vähän väsyny.', book: 'Minä olen vähän väsynyt.', en: "I'm a bit tired." },
  { fi: 'Onks sulla nälkä?', book: 'Onko sinulla nälkä?', en: 'Are you hungry?' },
  { fi: 'Emmä tiiä.', book: 'En minä tiedä.', en: "I don't know." }
]

const METHOD = [
  {
    icon: '🗣️',
    title: 'Speak from day one',
    body: 'Every sentence is heard, shadowed and produced out loud - retrieval practice, the single best-evidenced way to make language stick.'
  },
  {
    icon: '📅',
    title: 'Reviews that arrive on time',
    body: 'Spaced repetition schedules each sentence right before you\'d forget it. Five focused minutes a day beats an hour on Sunday.'
  },
  {
    icon: '🧖',
    title: 'Finnish Finns actually speak',
    body: 'Puhekieli first - "mä oon", not "minä olen". The textbook form stays one tap away, but you train the language of real life.'
  }
]
</script>

<template>
  <div class="landing">
    <section class="hero">
      <img class="hero-vaino" src="/vaino.png" alt="Väinö, your Finnish sauna companion" />
      <h1>Learn the Finnish <em>Finns actually speak</em></h1>
      <p class="lede">
        Short daily sauna sessions of spoken Finnish - built on retrieval practice
        and spaced repetition, not textbook drills.
      </p>
      <div class="cta-row">
        <router-link to="/try" class="btn btn-primary cta">🔥 Try it - no account</router-link>
        <router-link to="/register" class="btn btn-ghost cta">Create free account</router-link>
      </div>
      <p class="muted tiny">Free forever for the whole learning path.</p>
    </section>

    <section class="contrast">
      <h2>Textbooks teach a language nobody speaks</h2>
      <div class="samples">
        <button
          v-for="s in SAMPLES"
          :key="s.fi"
          class="sample"
          :title="'Play ' + s.fi"
          @click="playSentence(s.fi)"
        >
          <span class="sample-fi">🔊 {{ s.fi }}</span>
          <span class="sample-book">📖 {{ s.book }}</span>
          <span class="sample-en">{{ s.en }}</span>
        </button>
      </div>
      <p class="muted">Left: what you'll say. Right: what the textbook would have taught you.</p>
    </section>

    <section class="method">
      <h2>Why it works</h2>
      <div class="method-grid">
        <div v-for="m in METHOD" :key="m.title" class="method-card">
          <span class="m-icon">{{ m.icon }}</span>
          <h3>{{ m.title }}</h3>
          <p>{{ m.body }}</p>
        </div>
      </div>
    </section>

    <section class="bottom-cta">
      <h2>Five minutes. Every day. Out loud.</h2>
      <router-link to="/try" class="btn btn-primary cta">Start your first session</router-link>
      <p class="muted tiny">Already learning? <router-link to="/login">Log in</router-link></p>
    </section>
  </div>
</template>

<style scoped>
.landing {
  max-width: 720px;
  margin: 0 auto;
  padding: 40px 20px 80px;
  display: flex;
  flex-direction: column;
  gap: 64px;
  text-align: center;
}

.hero { display: flex; flex-direction: column; align-items: center; gap: 14px; }
.hero-vaino { width: 160px; height: 160px; filter: drop-shadow(0 12px 20px rgba(0, 0, 0, 0.35)); }
.hero h1 { font-size: clamp(30px, 6vw, 42px); line-height: 1.15; letter-spacing: -0.02em; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { font-size: 17px; line-height: 1.55; color: var(--text-dim); max-width: 480px; }

.cta-row { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 8px; }
.cta { padding: 14px 24px; font-size: 16px; }
.tiny { font-size: 12px; }

.contrast h2,
.method h2,
.bottom-cta h2 { font-size: clamp(20px, 4vw, 26px); margin-bottom: 18px; }

.samples { display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px; }
.sample {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px 16px;
  align-items: center;
  text-align: left;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  font-family: inherit;
  cursor: pointer;
  transition: border-color 0.15s ease;
}
.sample:hover { border-color: var(--accent); }
.sample-fi { font-weight: 800; font-size: 16px; color: var(--text); }
.sample-book { font-size: 14px; color: var(--text-dim); text-decoration: line-through; text-decoration-color: rgba(245, 158, 11, 0.5); }
.sample-en { grid-column: 1 / -1; font-size: 13px; color: var(--text-dim); }
@media (max-width: 480px) {
  .sample { grid-template-columns: 1fr; }
}

.method-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.method-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}
.m-icon { font-size: 30px; }
.method-card h3 { font-size: 15px; }
.method-card p { font-size: 13px; line-height: 1.5; color: var(--text-dim); }

.bottom-cta { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.bottom-cta a:not(.btn) { color: var(--accent); }
</style>
