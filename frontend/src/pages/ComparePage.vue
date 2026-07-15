<script setup>
// Honest comparison page: the SEO answer to "best app to learn Finnish".
// Deliberately NOT a ranked medal list where we crown ourselves #1 - each
// competitor gets a genuine "choose this if" verdict, and the table admits
// where they beat us (grammar depth, audio-only learning, price). The one
// axis we own outright is puhekieli, so that's the framing of the page.

const APPS = ['SaunaSpeak', 'Duolingo', 'Pimsleur', 'SuomiSpeak']

// rows[n].cells align with APPS; first cell (ours) gets the accent treatment.
const ROWS = [
  {
    label: 'Spoken Finnish (puhekieli)',
    cells: [
      'The whole point - "mä oon", not "minä olen"',
      'No - textbook forms only',
      'No - formal speech',
      'A few lessons'
    ]
  },
  {
    label: 'Free tier',
    cells: [
      'Full learning path - no caps, no ads',
      'Full course, with ads and hearts',
      '7-day trial only',
      'Capped at 2 lessons a day'
    ]
  },
  {
    label: 'AI conversation practice',
    cells: [
      'Chat + real-life roleplay (Löyly+)',
      'Not on the Finnish course',
      'No',
      'AI feedback on writing'
    ]
  },
  {
    label: 'Grammar depth',
    cells: [
      'Patterns taught when you need them',
      'Light',
      'Light - audio only',
      'Deepest - drills all 15 noun cases'
    ]
  },
  {
    label: 'How far it takes you',
    cells: ['A0 → B1, growing', 'Around A2', 'Beginner course', 'Advertises A1 → C1']
  },
  {
    label: 'Built only for Finnish',
    cells: ['Yes', 'No - 40+ languages', 'No - 50+ languages', 'Yes']
  },
  {
    label: 'Paid plan',
    cells: ['€4.99/mo', '~€13/mo (Super)', '~$20/mo', '$2.99/mo']
  }
]

const VERDICTS = [
  {
    app: 'Duolingo',
    icon: '🦉',
    pick: 'Pick it if you want a fun free habit and a language-learning giant behind it.',
    but: 'But its Finnish course teaches written kirjakieli and stops around A2 - real Finns will sound like a different language.'
  },
  {
    app: 'Pimsleur',
    icon: '🎧',
    pick: 'Pick it if you learn best by listening - it\'s built for the car and the commute.',
    but: 'But Finnish gets one beginner course, in formal register, at a premium price.'
  },
  {
    app: 'SuomiSpeak',
    icon: '📚',
    pick: 'Pick it if you want systematic grammar drills - it goes deeper into the 15 noun cases than anyone.',
    but: 'But it\'s grammar-first: you\'ll know the case tables before you understand a Finn saying "emmä tiiä".'
  },
  {
    app: 'SaunaSpeak',
    icon: '♨️',
    pick: 'Pick it if you want to understand and speak the Finnish you\'ll actually hear in Finland - from the first lesson.',
    but: 'But if you want C-level grammar theory or a 4,000-word vocabulary tracker, pair it with a grammar resource.'
  }
]
</script>

<template>
  <div class="compare">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
      <router-link to="/login" class="home-link">Log in</router-link>
    </div>

    <div class="hero">
      <h1>Finnish learning apps, compared <em>honestly</em></h1>
      <p class="muted lede">
        Yes, we make one of these apps - so instead of a ranked list where we
        conveniently win gold, here's what each one is genuinely good at.
        The one thing we'll claim outright: if you want the Finnish that
        Finns actually speak, that's the whole reason SaunaSpeak exists.
      </p>
    </div>

    <div class="table-wrap card">
      <table>
        <thead>
          <tr>
            <th class="row-label" aria-label="Feature"></th>
            <th v-for="(app, i) in APPS" :key="app" :class="{ ours: i === 0 }">{{ app }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in ROWS" :key="row.label">
            <th class="row-label">{{ row.label }}</th>
            <td v-for="(cell, i) in row.cells" :key="i" :class="{ ours: i === 0 }">{{ cell }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="muted fine">
      Competitor details checked July 2026 from their public sites and store
      listings - prices and free tiers change, so double-check before you buy.
    </p>

    <section class="verdicts">
      <h2>The honest picks</h2>
      <div class="verdict-grid">
        <div v-for="v in VERDICTS" :key="v.app" class="card verdict" :class="{ ours: v.app === 'SaunaSpeak' }">
          <p class="verdict-app">{{ v.icon }} {{ v.app }}</p>
          <p class="verdict-pick">{{ v.pick }}</p>
          <p class="verdict-but muted">{{ v.but }}</p>
        </div>
      </div>
    </section>

    <section class="why card">
      <h2>Why spoken Finnish first?</h2>
      <p>
        Most courses teach kirjakieli - the written standard. It's correct,
        and nobody talks like it. Learners arrive in Finland after months of
        "minä olen" and can't parse "mä oon" at the counter of the K-market.
        SaunaSpeak flips the order: you learn the spoken form first, with the
        written form alongside as a reference, so real Finns are understandable
        from week one.
      </p>
      <router-link to="/try" class="btn btn-primary cta">Hear real spoken Finnish - no account</router-link>
    </section>

    <p class="muted fine links">
      <router-link to="/lessons">Browse the lessons</router-link> ·
      <router-link to="/pricing">Pricing</router-link> ·
      <router-link to="/register">Create a free account</router-link>
    </p>
  </div>
</template>

<style scoped>
/* meta.full route: the shell adds no width cap or padding, so we own both.
   Bottom padding clears the mobile tab bar for logged-in visitors. */
.compare { max-width: 860px; margin: 0 auto; padding: 16px 20px calc(84px + env(safe-area-inset-bottom)); }
.page-top { display: flex; justify-content: space-between; margin-bottom: 20px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }

.hero { text-align: center; margin-bottom: 28px; }
.hero h1 { font-size: clamp(26px, 5vw, 34px); line-height: 1.2; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { max-width: 560px; margin: 10px auto 0; font-size: 15.5px; line-height: 1.55; }

.table-wrap { overflow-x: auto; padding: 6px; }
table { border-collapse: collapse; width: 100%; min-width: 640px; }
th, td { padding: 10px 12px; font-size: 13.5px; line-height: 1.4; text-align: left; vertical-align: top; }
thead th { font-size: 14px; font-weight: 800; border-bottom: 1px solid var(--border); }
tbody tr + tr th, tbody tr + tr td { border-top: 1px solid var(--border); }
.row-label { font-weight: 700; color: var(--text-dim); min-width: 140px; }
td { color: var(--text-dim); }
.ours { background: var(--accent-soft); color: var(--text); }
thead .ours { color: var(--accent); }

.fine { text-align: center; font-size: 13px; line-height: 1.6; margin-top: 14px; }

.verdicts { margin-top: 34px; }
.verdicts h2, .why h2 { font-size: 20px; margin-bottom: 14px; }
.verdict-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
@media (min-width: 560px) { .verdict-grid { grid-template-columns: 1fr 1fr; } }
.verdict { display: flex; flex-direction: column; gap: 8px; }
.verdict.ours { border-color: var(--accent); }
.verdict-app { font-weight: 800; font-size: 15px; }
.verdict-pick { font-size: 14px; line-height: 1.5; }
.verdict-but { font-size: 13px; line-height: 1.5; }

.why { margin-top: 34px; text-align: center; padding: 26px 22px; }
.why p { max-width: 560px; margin: 0 auto; font-size: 14.5px; line-height: 1.6; color: var(--text-dim); }
.why .cta { margin-top: 18px; display: inline-block; }

.links { margin-top: 26px; }
.links a { color: var(--accent); }
</style>
