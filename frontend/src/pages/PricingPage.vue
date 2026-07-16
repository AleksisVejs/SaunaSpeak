<script setup>
// Public pricing: the answer to "what's the catch?" before anyone signs up.
// Free column is the product; Löyly+ is the AI layer on top.
import { computed, ref } from 'vue'

const FREE = [
  'The full lesson path (starts at zero, growing)',
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

// Display prices mirror UpgradePage's PLANS and the Stripe price objects
// behind STRIPE_PRICE_ID / STRIPE_PRICE_ID_YEARLY - keep all three in sync.
const INTERVALS = {
  monthly: { price: '€4.99', note: 'billed monthly' },
  yearly: { price: '€3.33', note: '€39.99 billed once a year' }
}
// Yearly is the better deal - it's the default, same as on the upgrade page.
const interval = ref('yearly')
const plusPrice = computed(() => INTERVALS[interval.value])

const FAQ = [
  {
    q: 'Is Free really free forever?',
    a: 'Yes. Every lesson, session, review and checkpoint - no trial clock, no lesson caps, no ads. Löyly+ only adds the AI conversation features on top.'
  },
  {
    q: 'Do I need Löyly+ to learn Finnish here?',
    a: 'No. The whole learning path is on the free plan. Löyly+ is for when you want to practice talking - open conversation, roleplay missions, and AI feedback on your own sentences.'
  },
  {
    q: 'How does the 3-day Löyly+ trial work?',
    a: 'Your first Löyly+ subscription starts with 3 free days. You add a card at checkout but pay €0 up front - the first charge happens when the trial ends. Cancel any time during those 3 days and you pay nothing. The trial applies once per account.'
  },
  {
    q: 'Can I cancel any time?',
    a: 'Any time, in one tap, from inside the app. You keep Löyly+ until the end of the period you already paid for - no partial locks, no retention hoops.'
  },
  {
    q: 'How do payments work?',
    a: 'Checkout and card handling happen on Stripe. SaunaSpeak never sees or stores your card number.'
  }
]
</script>

<template>
  <div class="pricing">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
      <router-link to="/login" class="home-link">Log in</router-link>
    </div>

    <div class="hero">
      <div class="hero-vaino-wrap">
        <span class="hero-glow" aria-hidden="true"></span>
        <img class="hero-vaino" src="/vaino-loyly.png" alt="Väinö throwing löyly on the stones" />
      </div>
      <h1>The learning path is <em>free forever</em></h1>
      <p class="muted lede">
        No trial, no lesson caps, no ads. Löyly+ adds the AI conversation layer
        on top - for less than one coffee a month.
      </p>
    </div>

    <div class="toggle-row">
      <div class="toggle" role="radiogroup" aria-label="Billing interval">
        <button
          class="toggle-btn"
          :class="{ on: interval === 'monthly' }"
          role="radio"
          :aria-checked="interval === 'monthly'"
          @click="interval = 'monthly'"
        >
          Monthly
        </button>
        <button
          class="toggle-btn"
          :class="{ on: interval === 'yearly' }"
          role="radio"
          :aria-checked="interval === 'yearly'"
          @click="interval = 'yearly'"
        >
          Yearly <span class="toggle-save">2 months free</span>
        </button>
      </div>
    </div>

    <div class="plans">
      <div class="card plan">
        <p class="plan-name">Free</p>
        <p class="plan-price">€0<span class="per"> forever</span></p>
        <p class="plan-note muted">no card needed</p>
        <ul>
          <li v-for="f in FREE" :key="f">✓ {{ f }}</li>
        </ul>
        <router-link to="/register" class="btn btn-ghost btn-block">Create free account</router-link>
      </div>

      <div class="card plan plus">
        <p class="plan-badge">♨️ Löyly+</p>
        <p class="plan-name">Löyly+</p>
        <p class="plan-price">{{ plusPrice.price }}<span class="per"> / month</span></p>
        <p class="plan-note muted">{{ plusPrice.note }}</p>
        <ul>
          <li>✓ Everything in Free, plus:</li>
          <li v-for="f in PLUS" :key="f">✓ {{ f }}</li>
        </ul>
        <router-link to="/register" class="btn btn-primary btn-block">Start free, upgrade inside</router-link>
        <p class="plan-trial muted">First subscription starts with 3 free days</p>
      </div>
    </div>

    <div class="faq">
      <details v-for="item in FAQ" :key="item.q" class="card faq-item">
        <summary>{{ item.q }}</summary>
        <p class="muted">{{ item.a }}</p>
      </details>
    </div>

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

.hero { text-align: center; margin-bottom: 22px; }
.hero-vaino-wrap { position: relative; display: inline-block; }
.hero-glow {
  position: absolute;
  inset: -18%;
  background: radial-gradient(closest-side, var(--accent-soft), transparent 72%);
  filter: blur(6px);
}
.hero-vaino { position: relative; width: 110px; height: 110px; margin-bottom: 8px; }
.hero h1 { font-size: clamp(26px, 5vw, 34px); line-height: 1.2; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { max-width: 440px; margin: 10px auto 0; font-size: 15.5px; line-height: 1.55; }

.toggle-row { display: flex; justify-content: center; margin-bottom: 18px; }
.toggle {
  display: inline-flex;
  gap: 4px;
  padding: 4px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
}
.toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: none;
  background: none;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  color: var(--text-dim);
  padding: 7px 14px;
  border-radius: var(--radius-pill);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.toggle-btn.on { background: var(--accent-soft); color: var(--accent); }
.toggle-save {
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  background: var(--green);
  border-radius: var(--radius-pill);
  padding: 2px 8px;
  white-space: nowrap;
}

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
.plan-note { font-size: 13px; margin-top: -4px; }
.plan-trial { font-size: 12.5px; text-align: center; margin-top: 8px; }
.plan ul { list-style: none; padding: 0; margin: 10px 0 16px; display: flex; flex-direction: column; gap: 8px; }
.plan li { font-size: 14px; line-height: 1.45; color: var(--text-dim); }
.plan .btn { margin-top: auto; }

.faq { display: flex; flex-direction: column; gap: 10px; margin-top: 26px; }
.faq-item { padding: 14px 16px; }
.faq-item summary {
  font-weight: 700;
  font-size: 14.5px;
  cursor: pointer;
  list-style: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after { content: '+'; color: var(--text-dim); font-weight: 600; font-size: 18px; flex-shrink: 0; }
.faq-item[open] summary::after { content: '−'; }
.faq-item p { font-size: 14px; line-height: 1.55; margin-top: 8px; }

.fine { text-align: center; font-size: 13px; line-height: 1.6; margin-top: 22px; max-width: 460px; margin-left: auto; margin-right: auto; }
.links a { color: var(--accent); }
</style>
