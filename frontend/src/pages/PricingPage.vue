<script setup>
// Public pricing: the answer to "what's the catch?" before anyone signs up.
// Free column is the product; Löyly+ is the AI layer on top.
const FREE = [
  'The full lesson path (A0 → B1, growing)',
  'Daily Sauna Sessions with spaced repetition',
  'Audio on every sentence and word',
  'Word bank + flashcard reviews',
  'Level checkpoints and streaks'
]

const PLUS = [
  'Sauna Chat - free AI conversation with Väinö',
  'Situations - real-life roleplay missions',
  'AI feedback on your written and spoken attempts',
  'Weekly insights into your learning'
]
</script>

<template>
  <div class="pricing">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
      <router-link to="/login" class="home-link">Log in</router-link>
    </div>

    <div class="hero">
      <img class="hero-vaino" src="/vaino-loyly.png" alt="Väinö throwing löyly on the stones" />
      <h1>The learning path is <em>free forever</em></h1>
      <p class="muted lede">
        No trial, no lesson caps, no ads. Löyly+ adds the AI conversation layer
        on top - for less than one coffee a month.
      </p>
    </div>

    <div class="plans">
      <div class="card plan">
        <p class="plan-name">Free</p>
        <p class="plan-price">€0<span class="per"> forever</span></p>
        <ul>
          <li v-for="f in FREE" :key="f">✓ {{ f }}</li>
        </ul>
        <router-link to="/register" class="btn btn-ghost btn-block">Create free account</router-link>
      </div>

      <div class="card plan plus">
        <p class="plan-badge">♨️ Löyly+</p>
        <p class="plan-name">Löyly+</p>
        <p class="plan-price">€4.99<span class="per"> / month</span></p>
        <p class="yearly muted">or €39.99 / year - two months free</p>
        <ul>
          <li>✓ Everything in Free, plus:</li>
          <li v-for="f in PLUS" :key="f">✓ {{ f }}</li>
        </ul>
        <router-link to="/register" class="btn btn-primary btn-block">Start free, upgrade inside</router-link>
      </div>
    </div>

    <p class="muted fine">
      Cancel any time - you keep Löyly+ until the end of the period you paid for.
      Payments are handled by Stripe; SaunaSpeak never sees your card number.
    </p>

    <p class="muted fine links">
      <router-link to="/try">Try it first - no account</router-link> ·
      <router-link to="/terms">Terms</router-link> ·
      <router-link to="/privacy">Privacy</router-link>
    </p>
  </div>
</template>

<style scoped>
.pricing { max-width: 720px; margin: 0 auto; padding: 16px 4px 60px; }
.page-top { display: flex; justify-content: space-between; margin-bottom: 20px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }

.hero { text-align: center; margin-bottom: 28px; }
.hero-vaino { width: 110px; height: 110px; margin-bottom: 8px; }
.hero h1 { font-size: clamp(26px, 5vw, 34px); line-height: 1.2; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { max-width: 440px; margin: 10px auto 0; font-size: 15.5px; line-height: 1.55; }

.plans { display: grid; grid-template-columns: 1fr; gap: 14px; }
@media (min-width: 560px) { .plans { grid-template-columns: 1fr 1fr; align-items: start; } }

.plan { display: flex; flex-direction: column; gap: 6px; position: relative; }
.plan.plus { border-color: var(--accent); }
.plan-badge {
  position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
  background: var(--accent); color: #1a1204;
  font-size: 12px; font-weight: 800;
  border-radius: var(--radius-pill); padding: 3px 12px; white-space: nowrap;
}
.plan-name { font-weight: 800; font-size: 15px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; }
.plan-price { font-size: 32px; font-weight: 800; }
.plan-price .per { font-size: 14px; font-weight: 600; color: var(--text-dim); }
.yearly { font-size: 13px; margin-top: -4px; }
.plan ul { list-style: none; padding: 0; margin: 10px 0 16px; display: flex; flex-direction: column; gap: 8px; }
.plan li { font-size: 14px; line-height: 1.45; color: var(--text-dim); }
.plan .btn { margin-top: auto; }

.fine { text-align: center; font-size: 13px; line-height: 1.6; margin-top: 22px; max-width: 460px; margin-left: auto; margin-right: auto; }
.links a { color: var(--accent); }
</style>
