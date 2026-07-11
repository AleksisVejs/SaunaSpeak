<script setup>
// Löyly+ upgrade page. Checkout happens on Stripe's hosted page; we just
// redirect there and Stripe's webhook flips the account to premium.
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const auth = useAuthStore()

const billing = ref(null)
const starting = ref(false)
const error = ref('')
const justPaid = ref(route.query.status === 'success')

const PERKS = [
  { icon: '💬', title: 'Sauna Chat with Väinö', text: 'Unlimited free-form conversation practice — the fastest way to find your gaps.' },
  { icon: '🧠', title: 'AI feedback on every attempt', text: 'Not just right/wrong: what went wrong and why, in plain English.' },
  { icon: '📈', title: 'Weekly insights', text: 'Your reviews, recall rate and momentum, week by week.' }
]

onMounted(async () => {
  try {
    const [billingRes] = await Promise.all([api.get('/billing'), auth.fetchUser()])
    billing.value = billingRes.data
  } catch {
    error.value = 'Could not load subscription status.'
  }
})

async function upgrade() {
  starting.value = true
  error.value = ''
  try {
    const { data } = await api.post('/billing/checkout')
    window.location.href = data.url
  } catch {
    error.value = 'Could not start checkout. Please try again.'
    starting.value = false
  }
}

async function managePortal() {
  try {
    const { data } = await api.post('/billing/portal')
    window.location.href = data.url
  } catch {
    error.value = 'Could not open the billing portal.'
  }
}
</script>

<template>
  <div class="upgrade">
    <div class="hero">
      <img class="hero-vaino" src="/vaino-loyly.png" alt="Väinö throwing löyly" />
      <h1>Löyly+</h1>
      <p class="muted">More steam for your Finnish.</p>
    </div>

    <div v-if="justPaid" class="card paid">
      🎉 <b>Kiitos!</b> Your payment went through — Löyly+ unlocks within a minute.
      Pull the dashboard to refresh if it hasn't yet.
    </div>

    <div class="card perks">
      <div v-for="p in PERKS" :key="p.title" class="perk">
        <span class="perk-icon">{{ p.icon }}</span>
        <div>
          <p class="perk-title">{{ p.title }}</p>
          <p class="perk-text muted">{{ p.text }}</p>
        </div>
      </div>
    </div>

    <p class="free-note muted">
      The full learning path — all lessons, sessions, spaced repetition, flashcards,
      audio and checkpoints — stays free, forever.
    </p>

    <div v-if="error" class="error-msg">{{ error }}</div>

    <template v-if="billing">
      <div v-if="!billing.billing_enabled" class="card founder">
        🔓 Billing isn't live yet — every Löyly+ feature is currently <b>free for everyone</b>. Enjoy!
      </div>

      <template v-else-if="billing.is_premium">
        <div class="card active-plan">
          ✅ You're on <b>Löyly+</b><span v-if="billing.premium_until"> until {{ new Date(billing.premium_until).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>.
        </div>
        <button v-if="billing.has_subscription" class="btn btn-ghost btn-block" @click="managePortal">
          Manage subscription
        </button>
      </template>

      <button v-else class="btn btn-primary btn-block cta" :disabled="starting" @click="upgrade">
        {{ starting ? 'Opening checkout…' : '♨️ Upgrade to Löyly+ — €4.99/month' }}
      </button>
    </template>

    <router-link to="/dashboard" class="back-link muted">‹ Back to learning</router-link>
  </div>
</template>

<style scoped>
.upgrade { display: flex; flex-direction: column; gap: 16px; padding-top: 8px; }
.hero { text-align: center; }
.hero-vaino { width: 130px; height: 130px; }
.hero h1 { font-size: 30px; margin-top: 4px; }

.paid { background: var(--green-soft); border-color: var(--green); line-height: 1.5; }

.perks { display: flex; flex-direction: column; gap: 16px; }
.perk { display: flex; gap: 12px; align-items: flex-start; }
.perk-icon { font-size: 24px; }
.perk-title { font-weight: 800; }
.perk-text { font-size: 14px; line-height: 1.45; margin-top: 2px; }

.free-note { text-align: center; font-size: 13px; line-height: 1.5; padding: 0 12px; }

.founder { text-align: center; line-height: 1.5; }
.active-plan { text-align: center; background: var(--green-soft); border-color: var(--green); }

.cta { font-size: 16px; padding: 16px; }
.back-link { text-align: center; font-size: 14px; }
</style>
