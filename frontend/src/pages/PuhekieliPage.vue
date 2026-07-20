<script setup>
// The three-registers explainer: kirjakieli / puhekieli / yleispuhekieli.
//
// Every claim here came from native speakers in the July 2026 r/Finland,
// r/Helsinki and r/LearnFinnish threads - including the ones that contradict
// how this site used to pitch itself. "Yleispuhekieli" is the term we were
// missing: it names what SaunaSpeak actually teaches, and it's why "nobody
// speaks kirjakieli" was the wrong thing to say. Source notes and the full
// variant corpus live in backend/database/reference/regional-variants.json.

const REGISTERS = [
  {
    name: 'Kirjakieli',
    tag: 'the written standard',
    body: 'What books, news and official letters use, and what almost every course teaches. Nobody chats in it - but every Finn can produce it, and they will switch to it the moment they realise they are talking to a learner, a child, or someone who cannot hear them well.',
    example: { fi: 'Minä olen. Onko sinulla nälkä?', en: '"I am. Are you hungry?"' }
  },
  {
    name: 'Puhekieli',
    tag: 'spoken Finnish - but not one thing',
    body: 'The catch-all name for how people actually talk. There is no single puhekieli: vocabulary shifts between towns and between social circles, and the same speaker will pick different words depending on who they are talking to. You cannot learn "the" spoken Finnish because there is not one.',
    example: { fi: 'Mä oon. Onks sul nälkä?', en: 'The same two lines, said out loud.' }
  },
  {
    name: 'Yleispuhekieli',
    tag: 'the common spoken register',
    body: 'The one worth learning first. It is the spoken language of news broadcasts and public speaking: it has the everyday reductions, but drops regional vocabulary and strong dialect markers, staying close to kirjakieli. It is understood everywhere and marks you as being from nowhere in particular - which, as a learner, is exactly what you want.',
    example: { fi: 'Mä oon. Onks sul nälkä?', en: 'Reductions, yes. Local slang, no.' },
    ours: true
  }
]

// The reductions are the teachable part: what changes is near-universal, even
// though the exact result varies. The eastern exception is real - do not
// quietly drop it to make the table tidier.
const REDUCTIONS = [
  { written: 'minä', spoken: 'mä', also: 'mää, mie, mnää' },
  { written: 'sinä', spoken: 'sä', also: 'sää, sie, snää' },
  { written: 'onko', spoken: 'onks', also: '' },
  { written: 'onko sinulla', spoken: 'onks sul', also: '' },
  { written: 'en minä tiedä', spoken: 'emmä tiiä', also: '' }
]

// One object, five words, no geographic pattern that survives contact with
// actual speakers. This table exists to kill the idea that there is a right
// answer to memorise.
const TRAM = [
  { word: 'raitiovaunu', note: 'The standard word. Correct everywhere, used in speech rarely.' },
  { word: 'ratikka', note: 'The most widely reported spoken form, including from Helsinki and Turku speakers.' },
  { word: 'spora', note: 'From Swedish spårvagn. Associated with Helsinki - but attested from Tampere, and plenty of long-term Helsinki residents never use it.' },
  { word: 'raitsikka', note: 'Reported as older, or deliberately ornate.' },
  { word: 'skuru', note: 'The oldest Helsinki slang form. Mostly historical, but not extinct.' }
]
</script>

<template>
  <div class="explainer">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
      <router-link to="/try" class="home-link">Try a lesson</router-link>
    </div>

    <h1>Puhekieli, kirjakieli and yleispuhekieli</h1>
    <p class="lede muted">
      Finnish learners are usually told there are two Finnishes: the one in the
      textbook and the one people speak. That is nearly right, and the missing
      third word is the one that makes it make sense.
    </p>

    <section class="registers">
      <article v-for="r in REGISTERS" :key="r.name" class="card register" :class="{ ours: r.ours }">
        <h2>{{ r.name }}</h2>
        <p class="tag">{{ r.tag }}</p>
        <p class="body">{{ r.body }}</p>
        <p class="example"><span class="fi">{{ r.example.fi }}</span><span class="en muted">{{ r.example.en }}</span></p>
      </article>
    </section>

    <section class="prose">
      <h2>Why "nobody speaks kirjakieli" is misleading</h2>
      <p>
        It is true that Finns do not chat in kirjakieli. It does not follow that
        learning it is wasted. Kirjakieli is the variety every Finnish speaker
        understands regardless of where they grew up - that is the whole reason
        it exists. It is also what Finns switch <em>to</em> when they hear a
        learner, so a beginner's early conversations are often conducted in it.
      </p>
      <p>
        The better way to think about it: kirjakieli is the basis you build on,
        and the spoken forms are what you layer over it. What a beginner is
        missing is not the standard language - it is the ability to follow the
        conversation happening <em>next</em> to them, where nobody is adjusting
        anything for their benefit.
      </p>

      <h2>The part that is genuinely universal</h2>
      <p>
        Spoken Finnish contracts. Which contraction you hear depends on where
        you are, but the contraction itself happens almost everywhere, and it is
        the single biggest reason a learner who aced the textbook cannot follow
        a real conversation.
      </p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Written</th><th>Spoken</th><th>Also heard</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in REDUCTIONS" :key="r.written">
              <td class="written">{{ r.written }}</td>
              <td class="spoken">{{ r.spoken }}</td>
              <td class="muted">{{ r.also || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="fine muted">
        One honest exception: <strong>minä</strong> is not reduced everywhere.
        It is traditionally kept in Savo, Kainuu and Eastern Lapland, where
        <em>mie</em> has also been gaining ground. Treat the reductions as a
        strong tendency, not a law.
      </p>

      <h2>The part that is local, and why you should not copy it</h2>
      <p>
        Everyday vocabulary is where the regional variation actually lives. Ask
        Finns what they call a tram and you get at least five answers, and the
        pattern behind them is not geography - it is who a person spent time
        around, which keeps changing through their life. People report picking
        up new words after moving across a city, and losing old ones because
        nobody in the new place understood them.
      </p>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Word</th><th>What it signals</th></tr></thead>
          <tbody>
            <tr v-for="t in TRAM" :key="t.word">
              <td class="spoken">{{ t.word }}</td>
              <td class="muted">{{ t.note }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p>
        The practical rule, and Finnish speakers are blunt about this:
        <strong>learn to recognise all of them, and say the neutral one.</strong>
        Local words mark the speaker. Using a town's own word when you are not
        from there does not read as fluent - it reads as trying to sound like
        a local, which is a worse outcome than simply saying
        <em>ratikka</em> or <em>bussi</em> and being understood by everyone.
      </p>

      <h2>So what should a beginner actually learn?</h2>
      <ol>
        <li><strong>Yleispuhekieli first.</strong> The reductions, the everyday phrasings, no local vocabulary.</li>
        <li><strong>Kirjakieli alongside it,</strong> not after it - you need to read, and you will be spoken to in it.</li>
        <li><strong>Local variants as listening only.</strong> Understand <em>spora</em>, <em>dösä</em> and <em>linkki</em>; do not put them in your own mouth until somewhere has become home.</li>
      </ol>
      <p>
        That is the order SaunaSpeak teaches in, and the pairing is on every
        sentence: the spoken form you will hear, with the written form one tap
        away.
      </p>
    </section>

    <router-link to="/try" class="btn btn-primary cta">Hear it - no account needed</router-link>

    <p class="muted fine links">
      <router-link to="/lessons">Browse the lessons</router-link> ·
      <router-link to="/compare">Compare Finnish apps</router-link>
    </p>
  </div>
</template>

<style scoped>
.explainer { max-width: 720px; margin: 0 auto; padding: 16px 20px calc(84px + env(safe-area-inset-bottom)); text-align: left; }
.page-top { display: flex; justify-content: space-between; margin-bottom: 20px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }

h1 { font-size: clamp(25px, 5vw, 33px); line-height: 1.2; }
.lede { margin-top: 10px; font-size: 15.5px; line-height: 1.6; }

.registers { display: grid; gap: 14px; margin: 28px 0 34px; }
@media (min-width: 700px) { .registers { grid-template-columns: repeat(3, 1fr); } }
.register { display: flex; flex-direction: column; gap: 6px; padding: 18px; }
.register.ours { border-color: var(--accent); background: var(--accent-soft); }
.register h2 { font-size: 17px; }
.register .tag { font-size: 12.5px; font-weight: 700; color: var(--accent); text-transform: lowercase; }
.register .body { font-size: 13.5px; line-height: 1.55; color: var(--text-dim); }
.register .example { display: flex; flex-direction: column; gap: 2px; margin-top: auto; padding-top: 10px; }
.example .fi { font-size: 13.5px; font-weight: 700; }
.example .en { font-size: 12.5px; }

.prose h2 { font-size: 19px; margin: 30px 0 10px; }
.prose p { font-size: 14.5px; line-height: 1.65; color: var(--text-dim); margin-bottom: 12px; }
.prose ol { margin: 0 0 12px 20px; }
.prose li { font-size: 14.5px; line-height: 1.65; color: var(--text-dim); margin-bottom: 8px; }
.prose strong { color: var(--text); }

.table-wrap { overflow-x: auto; margin: 14px 0 12px; border: 1px solid var(--border); border-radius: 12px; }
table { border-collapse: collapse; width: 100%; min-width: 380px; }
th, td { padding: 9px 12px; font-size: 13.5px; line-height: 1.45; text-align: left; }
th { font-weight: 700; color: var(--text-dim); border-bottom: 1px solid var(--border); }
tbody tr:not(:last-child) td { border-bottom: 1px solid var(--border); }
td.written { color: var(--text-dim); text-decoration: line-through; text-decoration-thickness: 1px; }
td.spoken { font-weight: 700; color: var(--accent); }

.fine { font-size: 13px; line-height: 1.6; }
.cta { display: inline-block; margin-top: 26px; }
.links { margin-top: 24px; }
.links a { color: var(--accent); }
</style>
