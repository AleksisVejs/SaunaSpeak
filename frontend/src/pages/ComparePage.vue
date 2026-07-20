<script setup>
// Honest comparison page: the SEO answer to "best app to learn Finnish".
// Deliberately NOT a ranked medal list where we crown ourselves #1 - each
// competitor gets a genuine "choose this if" verdict, and the table admits
// where they beat us (grammar depth, audio-only learning, price). The one
// axis we own outright is puhekieli, so that's the framing of the page.

// Icons echo the verdict cards below so the same app reads the same everywhere.
const APPS = [
  { name: 'SaunaSpeak', icon: '♨️' },
  { name: 'Duolingo', icon: '🦉' },
  { name: 'Pimsleur', icon: '🎧' },
  { name: 'SuomiSpeak', icon: '📚' }
]

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
      'Patterns in context + inflection drills (Taivutus)',
      'Light',
      'Light - audio only',
      'Deepest - drills all 15 noun cases'
    ]
  },
  {
    label: 'Extensive listening',
    cells: [
      'Whole conversations at natural speed (Kuuntelu)',
      'Single sentences',
      'Audio-only course',
      'Single sentences'
    ]
  },
  {
    label: 'How far it takes you',
    cells: ['Zero → solid everyday speech (A2 done properly), upper lessons growing', 'Around A2', 'Beginner course', 'Advertises A1 → C1']
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
    but: 'But its Finnish course teaches written kirjakieli and stops around A2 - you\'ll be understood, but everyday speech will still be hard to follow.'
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
    but: 'But it\'s grammar-first: you\'ll know the case tables before you understand a Finn saying "emmä tiiä". (SaunaSpeak now drills the endings too, in Taivutus - just in spoken forms and in context.)'
  },
  {
    app: 'SaunaSpeak',
    icon: '♨️',
    pick: 'Pick it if you want to understand and speak the Finnish you\'ll actually hear in Finland - from the first lesson.',
    but: 'But if you want C-level grammar theory or a 4,000-word vocabulary tracker, pair it with a grammar resource - and while real native recordings roll out sentence by sentence, the rest of the audio is a studio-grade AI voice (ElevenLabs), not a human.'
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
      <span class="hero-glow" aria-hidden="true"></span>
      <h1>Finnish learning apps, compared <em>honestly</em></h1>
      <p class="muted lede">
        Yes, we make one of these apps - so instead of a ranked list where we
        conveniently win gold, here's what each one is genuinely good at.
        The one thing we'll claim outright: if you want the Finnish that
        Finns actually speak, that's the whole reason SaunaSpeak exists.
      </p>
    </div>

    <p class="swipe-hint muted" aria-hidden="true">SaunaSpeak's column is shown first - swipe sideways to compare the others →</p>
    <div class="table-wrap card">
      <table>
        <caption class="sr-only">Feature comparison of SaunaSpeak, Duolingo, Pimsleur and SuomiSpeak</caption>
        <thead>
          <tr>
            <th class="row-label" aria-label="Feature"></th>
            <th v-for="(app, i) in APPS" :key="app.name" scope="col" :class="{ ours: i === 0 }">
              <span v-if="i === 0" class="best-badge">★ Best for spoken Finnish</span>
              <span class="app-head">
                <span class="app-emoji" aria-hidden="true">{{ app.icon }}</span>
                <span class="app-name">{{ app.name }}</span>
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in ROWS" :key="row.label">
            <th class="row-label" scope="row">{{ row.label }}</th>
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
        Most courses teach kirjakieli - the written standard. It's correct, it's
        understood everywhere, and that's exactly why it's taught. But almost
        nobody speaks it. Learners arrive after months of "minä olen" and can't
        parse "mä oon" at the counter of the K-market.
      </p>
      <p>
        There's no single spoken Finnish to teach instead - vocabulary shifts
        between towns and between social circles. What barely shifts is the
        reductions: "minä" becomes "mä" or "mää" or "mie", but it becomes
        something everywhere. SaunaSpeak teaches those first, keeps the written
        form alongside as a reference, and marks the words that are local rather
        than pretending there's one right answer.
      </p>
      <router-link to="/try" class="btn btn-primary cta">Hear real spoken Finnish - no account</router-link>
    </section>

    <p class="muted fine links">
      <router-link to="/puhekieli-vs-kirjakieli">Puhekieli vs kirjakieli, explained</router-link> ·
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

.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
}

.hero { position: relative; text-align: center; margin-bottom: 28px; }
/* soft löyly glow behind the title, echoing the landing hero */
.hero-glow {
  position: absolute; top: -30px; left: 50%; transform: translateX(-50%);
  width: 320px; height: 200px; border-radius: 50%;
  background: radial-gradient(closest-side, var(--accent-soft), transparent 72%);
  pointer-events: none; z-index: 0;
}
.hero h1, .hero .lede { position: relative; z-index: 1; }
.hero h1 { font-size: clamp(26px, 5vw, 34px); line-height: 1.2; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { max-width: 560px; margin: 10px auto 0; font-size: 15.5px; line-height: 1.55; }

/* Scroll affordance: the table scrolls sideways on phones, and the leftmost
   column (SaunaSpeak) is already in view - this just tells narrow screens to swipe. */
.swipe-hint { display: none; font-size: 12.5px; text-align: center; margin-bottom: 8px; }
@media (max-width: 559px) { .swipe-hint { display: block; } }

.table-wrap { overflow-x: auto; padding: 6px; }
table { border-collapse: separate; border-spacing: 0; width: 100%; min-width: 660px; }
th, td { padding: 11px 13px; font-size: 13.5px; line-height: 1.4; text-align: left; vertical-align: top; }
tbody th, tbody td { border-bottom: 1px solid var(--border); }
tbody tr:last-child th, tbody tr:last-child td { border-bottom: none; }

/* sticky feature column: keeps the row label in view while the table scrolls
   sideways on narrow screens. Solid card bg so cells don't bleed through. */
.row-label {
  position: sticky; left: 0; z-index: 2;
  background: var(--card);
  font-weight: 700; color: var(--text-dim); min-width: 148px;
}
thead .row-label { z-index: 3; }
td { color: var(--text-dim); }

/* header: emoji + name, badge stacked above ours */
thead th { vertical-align: bottom; padding-bottom: 12px; }
thead th:not(.ours) { border-bottom: 1px solid var(--border); }
.app-head { display: inline-flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 800; }
.app-emoji { font-size: 16px; line-height: 1; }
.best-badge {
  display: table; margin-bottom: 8px;
  font-size: 10.5px; font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase;
  color: var(--accent-contrast); background: linear-gradient(135deg, var(--accent), var(--accent-2));
  padding: 3px 8px; border-radius: var(--radius-pill); white-space: nowrap;
}

/* the SaunaSpeak column, framed as its own highlighted card */
.ours { background: var(--accent-soft); color: var(--text); }
td.ours, th.ours {
  border-left: 1.5px solid var(--accent);
  border-right: 1.5px solid var(--accent);
}
thead th.ours { border-top: 1.5px solid var(--accent); border-top-left-radius: 12px; border-top-right-radius: 12px; }
tbody tr:last-child td.ours { border-bottom: 1.5px solid var(--accent); border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; }
thead th.ours .app-name { color: var(--accent); }

.fine { text-align: center; font-size: 13px; line-height: 1.6; margin-top: 14px; }

.verdicts { margin-top: 34px; }
.verdicts h2, .why h2 { font-size: 20px; margin-bottom: 14px; }
.verdict-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
@media (min-width: 560px) { .verdict-grid { grid-template-columns: 1fr 1fr; } }
.verdict { display: flex; flex-direction: column; gap: 8px; transition: transform 0.15s var(--ease), box-shadow 0.15s var(--ease); }
.verdict:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.verdict.ours { border-color: var(--accent); background: var(--accent-soft); }
.verdict-app { font-weight: 800; font-size: 15px; }
.verdict-pick { font-size: 14px; line-height: 1.5; }
.verdict-but { font-size: 13px; line-height: 1.5; }

.why { margin-top: 34px; text-align: center; padding: 26px 22px; }
.why p { max-width: 560px; margin: 0 auto; font-size: 14.5px; line-height: 1.6; color: var(--text-dim); }
.why p + p { margin-top: 12px; }
.why .cta { margin-top: 18px; display: inline-block; }

.links { margin-top: 26px; }
.links a { color: var(--accent); }
</style>
