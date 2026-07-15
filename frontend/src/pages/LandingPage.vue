<script setup>
// Public front door: the only page a search engine or a shared link shows.
// Sells the one thing competitors don't have - real SPOKEN Finnish - and
// funnels to /try (no account needed) or /register.
import { onBeforeUnmount, onMounted } from 'vue'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playSentence } = useFinnishAudio()

// Spoken-vs-textbook contrast is the product in one glance. These are real
// course sentences, so their pre-generated MP3s exist (this app never falls
// back to browser TTS - without a URL the buttons would be silent).
const SAMPLES = [
  { fi: 'Puhuksä englantia?', book: 'Puhutko sinä englantia?', en: 'Do you speak English?', audio: '/audio/sentence-7.mp3' },
  { fi: 'Onks sul nälkä?', book: 'Onko sinulla nälkä?', en: 'Are you hungry?', audio: '/audio/sentence-14.mp3' },
  { fi: 'Emmä tiiä.', book: 'En minä tiedä.', en: "I don't know.", audio: '/audio/sentence-10.mp3' }
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

// A Situations round in one glance: mission, character, real puhekieli.
const DEMO_CHAT = [
  { who: 'them', text: 'Moi! Mitä sulle saisi olla?', en: 'Hi! What can I get you?' },
  { who: 'you', text: 'Yks kahvi ja korvapuusti, kiitos!', en: 'One coffee and a cinnamon bun, please!' },
  { who: 'them', text: 'Selvä! Täällä vai mukaan?', en: 'Sure! For here or to go?' }
]

const FEATURES = [
  { icon: '🎧', title: 'Audio on every sentence', body: 'One consistent Finnish voice across the whole app - listen, shadow, speak.' },
  { icon: '🔁', title: 'Spaced repetition', body: 'Sentences and your own saved words come back right on time.' },
  { icon: '⭐', title: 'Word bank', body: 'Tap any word to save it; review your collection as flashcards.' },
  { icon: '🏁', title: 'Checkpoints', body: 'Prove each level with a low-stakes recall quiz - retake it any time.' },
  { icon: '💬', title: 'Sauna Chat with Väinö', body: 'Free-form AI conversation with a patient old Finn who knows your level - and your weak words. Löyly+' },
  { icon: '🎭', title: 'Situations', body: 'Real-life missions - buy groceries, order coffee, meet the neighbor - played out in spoken Finnish. Löyly+' }
]

// Rendered FAQ and the FAQPage JSON-LD are built from the same array, so the
// structured data always matches the visible page (a Google requirement).
const FAQ = [
  {
    q: 'Is SaunaSpeak free?',
    a: 'Yes - the whole learning path (lessons, daily sessions, spaced repetition, flashcards, audio and checkpoints) is free, forever. Löyly+ (€4.99/month) adds AI conversation practice, real-life roleplay Situations, AI feedback and weekly insights.'
  },
  {
    q: 'What is puhekieli?',
    a: 'Puhekieli is spoken, colloquial Finnish - what Finns actually say: "mä oon" instead of the textbook "minä olen", "onks" instead of "onko". Most courses teach only the formal written language (kirjakieli), which is why learners freeze when a real Finn speaks. SaunaSpeak teaches the spoken form first and keeps the written form one tap away.'
  },
  {
    q: 'Will Finns everywhere understand me?',
    a: 'Yes. SaunaSpeak teaches the capital-region spoken standard - the neutral everyday Finnish used in media and cities, understood in the whole country. Regional dialects like Savo or Oulu differ, but every Finn understands what you learn here.'
  },
  {
    q: 'I\'m a complete beginner - is this for me?',
    a: 'Absolutely. The path starts at zero with listen-and-repeat, and exercises grow with you: fill-the-gap, dictation, then full recall from English. Already know a few words? The intake quiz skips you past the very first lessons. You can try six real sentences right now without an account.'
  },
  {
    q: 'How much time does it take per day?',
    a: 'You choose: 2, 5 or 15 minutes a day. Short daily sessions scheduled by spaced repetition beat long weekly cramming - that\'s the science the app is built on.'
  }
]

// FAQ structured data only belongs on this route - inject on mount, clean up
// on leave so it never leaks onto app pages.
let faqLd = null
onMounted(() => {
  faqLd = document.createElement('script')
  faqLd.type = 'application/ld+json'
  faqLd.textContent = JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: FAQ.map((f) => ({
      '@type': 'Question',
      name: f.q,
      acceptedAnswer: { '@type': 'Answer', text: f.a }
    }))
  })
  document.head.appendChild(faqLd)
})
onBeforeUnmount(() => faqLd?.remove())
</script>

<template>
  <div class="landing">
    <!-- slim top bar: brand home-link + log in, so no visitor is ever stuck -->
    <nav class="topbar">
      <router-link to="/" class="tb-brand">
        <img src="/logo-sm.png" alt="" class="tb-logo" />
        <span>SaunaSpeak</span>
      </router-link>
      <div class="tb-actions">
        <router-link to="/login" class="btn btn-ghost tb-btn">Log in</router-link>
        <router-link to="/try" class="btn btn-primary tb-btn">Start free</router-link>
      </div>
    </nav>

    <section class="hero">
      <div class="hero-vaino-wrap">
        <span class="hero-glow" aria-hidden="true"></span>
        <!-- LCP element: WebP (~40% lighter) with PNG fallback; preloaded in index.html -->
        <picture>
          <source srcset="/vaino.webp" type="image/webp" />
          <img class="hero-vaino" src="/vaino.png" alt="Väinö, your Finnish sauna companion" width="160" height="160" fetchpriority="high" />
        </picture>
      </div>
      <h1>Learn the Finnish <em>Finns actually speak</em></h1>
      <p class="lede">
        Spoken Finnish (puhekieli) in short daily sauna sessions - audio-first,
        scheduled by spaced repetition, practiced out loud with an AI Finn.
      </p>
      <div class="cta-row">
        <router-link to="/try" class="btn btn-primary cta">🔥 Try it - no account</router-link>
        <router-link to="/register" class="btn btn-ghost cta">Create free account</router-link>
      </div>
      <p class="muted tiny">
        The learning path is free forever - AI conversation (Sauna Chat &amp; Situations) is
        <router-link to="/pricing" class="tiny-link">Löyly+</router-link>.
      </p>
    </section>

    <section class="contrast">
      <h2>Textbooks teach a language nobody speaks</h2>
      <div class="samples">
        <button
          v-for="s in SAMPLES"
          :key="s.fi"
          class="sample"
          :title="'Play ' + s.fi"
          @click="playSentence(s.fi, s.audio)"
        >
          <span class="sample-fi">🔊 {{ s.fi }}</span>
          <span class="sample-book">📖 {{ s.book }}</span>
          <span class="sample-en">{{ s.en }}</span>
        </button>
      </div>
      <p class="muted">Left: what you'll say. Right: what the textbook would have taught you.</p>
    </section>

    <!-- Situations: show, don't tell - one mission playing out -->
    <section class="situations">
      <h2>Practice real life before it happens</h2>
      <p class="section-lede muted">
        Step into everyday missions - buying groceries, ordering coffee, meeting
        your neighbor - and talk your way through them with an AI character.
      </p>
      <div class="demo">
        <div class="demo-head">
          <span class="demo-emoji">☕</span>
          <div class="demo-meta">
            <p class="demo-title">Ordering at a café</p>
            <p class="demo-sub muted">with Joonas, the barista</p>
          </div>
          <span class="demo-mission">🎯 Order a coffee</span>
        </div>
        <div class="demo-bubbles">
          <div v-for="(m, i) in DEMO_CHAT" :key="i" class="demo-row" :class="m.who">
            <div class="demo-bubble" :class="m.who">
              <p class="demo-fi">{{ m.text }}</p>
              <p class="demo-en">{{ m.en }}</p>
            </div>
          </div>
          <div class="demo-row them">
            <div class="demo-done">✅ Mission accomplished!</div>
          </div>
        </div>
      </div>
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

    <section class="features">
      <h2>Everything inside</h2>
      <div class="feature-grid">
        <div v-for="f in FEATURES" :key="f.title" class="feature">
          <span class="f-icon">{{ f.icon }}</span>
          <div>
            <h3>{{ f.title }}</h3>
            <p>{{ f.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="faq">
      <h2>Questions, answered</h2>
      <details v-for="f in FAQ" :key="f.q" class="faq-item">
        <summary>{{ f.q }}</summary>
        <p>{{ f.a }}</p>
      </details>
    </section>

    <section class="bottom-cta">
      <h2>Five minutes. Every day. Out loud.</h2>
      <router-link to="/try" class="btn btn-primary cta">Start your first session</router-link>
      <p class="muted tiny">Already learning? <router-link to="/login">Log in</router-link></p>
      <p class="muted tiny">Comparing apps? <router-link to="/compare">See how SaunaSpeak stacks up against Duolingo &amp; co →</router-link></p>
    </section>

    <footer class="site-footer">
      <nav class="foot-links">
        <router-link to="/try">Try it</router-link>
        <router-link to="/lessons">Lessons</router-link>
        <router-link to="/pricing">Pricing</router-link>
        <router-link to="/compare">Compare</router-link>
        <router-link to="/privacy">Privacy</router-link>
        <router-link to="/terms">Terms</router-link>
        <a href="mailto:mail@saunaspeak.com">Contact</a>
      </nav>
      <p class="muted foot-note">
        SaunaSpeak - spoken Finnish, five minutes a day.
        Illustrations from <a href="https://openmoji.org" target="_blank" rel="noopener">OpenMoji</a> (CC BY-SA 4.0).
      </p>
    </footer>
  </div>
</template>

<style scoped>
.landing {
  max-width: 720px;
  margin: 0 auto;
  padding: 16px 20px 80px;
  display: flex;
  flex-direction: column;
  gap: 64px;
  text-align: center;
}

/* ---- top bar ---- */
.topbar { display: flex; align-items: center; justify-content: space-between; }
.tb-brand {
  display: flex;
  align-items: center;
  gap: 9px;
  font-weight: 800;
  font-size: 17px;
  color: var(--text);
  letter-spacing: -0.01em;
}
.tb-logo { width: 30px; height: 30px; border-radius: 8px; }
.tb-actions { display: flex; gap: 8px; }
.tb-btn { padding: 9px 16px; font-size: 14px; }

/* ---- hero ---- */
.hero { display: flex; flex-direction: column; align-items: center; gap: 14px; margin-top: 12px; }
.hero-vaino-wrap { position: relative; }
.hero-glow {
  position: absolute;
  inset: -18%;
  background: radial-gradient(closest-side, var(--accent-soft), transparent 72%);
  filter: blur(6px);
}
.hero-vaino { position: relative; width: 160px; height: 160px; filter: drop-shadow(0 12px 20px rgba(0, 0, 0, 0.35)); }
.hero h1 { font-size: clamp(30px, 6vw, 42px); line-height: 1.15; letter-spacing: -0.02em; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { font-size: 17px; line-height: 1.55; color: var(--text-dim); max-width: 480px; }

.cta-row { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 8px; }
.cta { padding: 14px 24px; font-size: 16px; }
.tiny { font-size: 12px; }

.contrast h2,
.situations h2,
.method h2,
.features h2,
.faq h2,
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

/* ---- situations demo ---- */
.section-lede { max-width: 480px; margin: -8px auto 18px; font-size: 15px; line-height: 1.5; }
.demo {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px;
  text-align: left;
}
.demo-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 12px;
}
.demo-emoji { font-size: 26px; }
.demo-meta { flex: 1; min-width: 0; }
.demo-title { font-weight: 800; font-size: 15px; }
.demo-sub { font-size: 12.5px; }
.demo-mission {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: var(--radius-pill);
  padding: 5px 11px;
  white-space: nowrap;
}
.demo-bubbles { display: flex; flex-direction: column; gap: 8px; }
.demo-row { display: flex; }
.demo-row.you { justify-content: flex-end; }
.demo-bubble { max-width: 82%; padding: 10px 14px; border-radius: 16px; }
.demo-bubble.them { background: var(--bg-soft); border: 1px solid var(--border); border-bottom-left-radius: 6px; }
.demo-bubble.you { background: var(--accent-soft); border: 1px solid rgba(245, 158, 11, 0.3); border-bottom-right-radius: 6px; }
.demo-fi { font-weight: 700; font-size: 14.5px; }
.demo-en { font-size: 12px; color: var(--text-dim); margin-top: 2px; }
.demo-done {
  font-size: 13px;
  font-weight: 800;
  color: var(--green);
  background: var(--green-soft);
  border-radius: var(--radius-pill);
  padding: 6px 14px;
  margin-top: 2px;
}

/* ---- method ---- */
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

/* ---- features ---- */
.feature-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 560px) { .feature-grid { grid-template-columns: 1fr 1fr; } }
.feature {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  text-align: left;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 14px;
}
.f-icon { font-size: 22px; margin-top: 1px; }
.feature h3 { font-size: 14px; }
.feature p { font-size: 13px; line-height: 1.45; color: var(--text-dim); margin-top: 2px; }

/* ---- FAQ ---- */
.faq { text-align: left; }
.faq h2 { text-align: center; }
.faq-item {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  margin-bottom: 8px;
}
.faq-item summary {
  font-weight: 700;
  font-size: 14.5px;
  cursor: pointer;
  list-style: none;
  position: relative;
  padding-right: 26px;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after {
  content: '+';
  position: absolute;
  right: 2px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  font-weight: 800;
  color: var(--accent);
}
.faq-item[open] summary::after { content: '−'; }
.faq-item p { font-size: 13.5px; line-height: 1.55; color: var(--text-dim); margin-top: 10px; }

.bottom-cta { display: flex; flex-direction: column; align-items: center; gap: 12px; }
/* Underline, not color alone - links must survive color-blindness (WCAG 1.4.1). */
.bottom-cta a:not(.btn) { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
.tiny-link { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }

/* ---- footer ---- */
.site-footer { border-top: 1px solid var(--border); padding-top: 26px; display: flex; flex-direction: column; gap: 12px; }
.foot-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px 20px; }
.foot-links a { color: var(--text-dim); font-size: 13.5px; font-weight: 600; }
.foot-links a:hover { color: var(--accent); }
.foot-note { font-size: 12px; line-height: 1.6; }
.foot-note a { color: var(--text-dim); text-decoration: underline; }
</style>
