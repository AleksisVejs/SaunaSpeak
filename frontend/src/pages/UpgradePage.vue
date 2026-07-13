<script setup>
// Löyly+ upgrade page. Checkout happens on Stripe's hosted page (redirect);
// the webhook flips the account to premium. Cancelling happens right here -
// confirmed in-page, access runs until the paid period ends, reversible
// until then via Resume.
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const auth = useAuthStore()

const billing = ref(null)
const starting = ref(false)
const error = ref('')
const justPaid = ref(route.query.status === 'success')

// Two-step cancel: button arms the inline confirmation, nothing happens
// until the learner confirms.
const confirmingCancel = ref(false)
const busyCancel = ref(false)

const PERKS = [
  { icon: '💬', title: 'Sauna Chat with Väinö', text: 'Free-form conversation that knows you: your name, your goal, and the words you keep forgetting.' },
  { icon: '🎭', title: 'Situations', text: 'Real-life missions - buy groceries, order coffee, meet the neighbor - played out in spoken Finnish.' },
  { icon: '🧠', title: 'AI feedback on every attempt', text: 'Not just right/wrong: what went wrong and why, in plain English.' },
  { icon: '📈', title: 'Weekly insights', text: 'Your reviews, recall rate and momentum, week by week.' }
]

// Billing intervals for the same Löyly+ tier. Display prices are set here;
// the amounts Stripe actually charges live on the price objects behind
// STRIPE_PRICE_ID / STRIPE_PRICE_ID_YEARLY - keep them in sync.
const PLANS = [
  { id: 'monthly', label: 'Monthly', price: '€4.99', per: '/ month', note: null },
  { id: 'yearly', label: 'Yearly', price: '€39.99', per: '/ year', note: '2 months free' }
]
const plan = ref('monthly')

onMounted(async () => {
  try {
    const [billingRes] = await Promise.all([api.get('/billing'), auth.fetchUser()])
    billing.value = billingRes.data
    // Yearly is the better deal - preselect it when it's on offer.
    if (billing.value.plans?.yearly) plan.value = 'yearly'
  } catch {
    error.value = 'Could not load subscription status.'
  }
})

// Only offer intervals the backend has a Stripe price for.
const availablePlans = computed(() =>
  PLANS.filter((p) => billing.value?.plans?.[p.id])
)

async function upgrade() {
  starting.value = true
  error.value = ''
  try {
    const { data } = await api.post('/billing/checkout', { plan: plan.value })
    window.umami?.track('checkout_start', { plan: plan.value })
    window.location.href = data.url
  } catch (e) {
    error.value = e?.response?.status === 429
      ? 'Slow down a little - too many checkout attempts. Wait a minute and try again.'
      : 'Could not start checkout. Please try again.'
    starting.value = false
  }
}

async function cancelSubscription() {
  busyCancel.value = true
  error.value = ''
  try {
    const { data } = await api.post('/billing/cancel')
    billing.value.cancel_at_period_end = data.cancel_at_period_end
    billing.value.premium_until = data.premium_until
    confirmingCancel.value = false
  } catch (e) {
    error.value = e?.response?.status === 429
      ? 'Too many changes in a row - wait a minute and try again.'
      : 'Could not cancel. Please try again.'
  } finally {
    busyCancel.value = false
  }
}

async function resumeSubscription() {
  busyCancel.value = true
  error.value = ''
  try {
    const { data } = await api.post('/billing/resume')
    billing.value.cancel_at_period_end = data.cancel_at_period_end
    billing.value.premium_until = data.premium_until
  } catch (e) {
    error.value = e?.response?.status === 429
      ? 'Too many changes in a row - wait a minute and try again.'
      : 'Could not resume. Please try again.'
  } finally {
    busyCancel.value = false
  }
}

const fmtDate = (d) =>
  new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

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
      <div class="hero-vaino-wrap">
        <span class="hero-glow" aria-hidden="true"></span>
        <img class="hero-vaino" src="/vaino-loyly.png" alt="Väinö throwing löyly" />
      </div>
      <h1>Löyly+</h1>
      <p class="muted">More steam for your Finnish.</p>
    </div>

    <div v-if="justPaid" class="card paid">
      🎉 <b>Kiitos!</b> Your payment went through - Löyly+ unlocks within a minute.
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
      The full learning path - all lessons, sessions, spaced repetition, flashcards,
      audio and checkpoints - stays free, forever.
    </p>

    <div v-if="error" class="error-msg">{{ error }}</div>

    <template v-if="billing">
      <div v-if="!billing.billing_enabled" class="card founder">
        🔓 Billing isn't live yet - every Löyly+ feature is currently <b>free for everyone</b>. Enjoy!
      </div>

      <template v-else-if="billing.is_premium">
        <!-- pending cancellation: access runs out at period end, offer resume -->
        <template v-if="billing.cancel_at_period_end">
          <div class="card ending-plan">
            ⏳ Löyly+ is <b>cancelled</b> - you keep everything<span v-if="billing.premium_until"> until <b>{{ fmtDate(billing.premium_until) }}</b></span>, then it won't renew.
          </div>
          <button class="btn btn-primary btn-block" :disabled="busyCancel" @click="resumeSubscription">
            {{ busyCancel ? 'One moment…' : '♨️ Resume Löyly+' }}
          </button>
        </template>

        <template v-else>
          <div class="card active-plan">
            ✅ You're on <b>Löyly+</b><span v-if="billing.premium_until"> until {{ fmtDate(billing.premium_until) }}</span>.
          </div>

          <!-- two-step cancel: nothing happens without the confirmation -->
          <div v-if="confirmingCancel" class="card cancel-confirm">
            <p class="cancel-q">Cancel your subscription?</p>
            <p class="muted cancel-note">
              You keep Löyly+<span v-if="billing.premium_until"> until <b>{{ fmtDate(billing.premium_until) }}</b></span> -
              it just won't renew. You can resume any time before then.
            </p>
            <div class="cancel-row">
              <button class="btn btn-ghost cancel-yes" :disabled="busyCancel" @click="cancelSubscription">
                {{ busyCancel ? 'Cancelling…' : 'Yes, cancel' }}
              </button>
              <button class="btn btn-primary" :disabled="busyCancel" @click="confirmingCancel = false">
                Keep Löyly+
              </button>
            </div>
          </div>
          <button
            v-else-if="billing.has_subscription"
            class="btn btn-ghost btn-block"
            @click="confirmingCancel = true"
          >
            Cancel subscription
          </button>

          <button v-if="billing.has_subscription" class="portal-link muted" @click="managePortal">
            Payment details & invoices ›
          </button>
        </template>
      </template>

      <div v-else class="buy">
        <!-- interval picker: shown when more than one Stripe price exists -->
        <div v-if="availablePlans.length > 1" class="plan-row">
          <button
            v-for="p in availablePlans"
            :key="p.id"
            class="plan-card"
            :class="{ selected: plan === p.id }"
            @click="plan = p.id"
          >
            <span v-if="p.note" class="plan-note">{{ p.note }}</span>
            <span class="plan-label">{{ p.label }}</span>
            <span class="plan-price">{{ p.price }}<small>{{ p.per }}</small></span>
          </button>
        </div>

        <div v-else class="price">
          <span class="price-amount">€4.99</span>
          <span class="price-per">/ month</span>
        </div>

        <button class="btn btn-primary btn-block cta" :disabled="starting" @click="upgrade">
          {{ starting ? 'Opening checkout…' : '♨️ Upgrade to Löyly+' }}
        </button>
        <p class="reassure muted">Cancel anytime - you keep access until the period ends.</p>
      </div>
    </template>

    <router-link to="/dashboard" class="back-link muted">‹ Back to learning</router-link>
  </div>
</template>

<style scoped>
.upgrade { display: flex; flex-direction: column; gap: 16px; padding-top: 8px; }
.hero { text-align: center; }
.hero-vaino-wrap { position: relative; display: inline-block; }
.hero-glow {
  position: absolute;
  inset: -18%;
  background: radial-gradient(closest-side, var(--accent-soft), transparent 72%);
  filter: blur(6px);
}
.hero-vaino { position: relative; width: 130px; height: 130px; }
.hero h1 { font-size: 30px; margin-top: 4px; }

.paid { background: var(--green-soft); border-color: var(--green); line-height: 1.5; }

.ending-plan { text-align: center; background: var(--accent-soft); border-color: var(--accent); line-height: 1.5; }

.cancel-confirm { display: flex; flex-direction: column; gap: 10px; }
.cancel-q { font-weight: 800; font-size: 16px; }
.cancel-note { font-size: 14px; line-height: 1.5; }
.cancel-row { display: grid; grid-template-columns: 1fr 1.2fr; gap: 10px; }
.cancel-yes:hover:not(:disabled) { border-color: var(--red); color: var(--red); }

.portal-link {
  background: none;
  border: none;
  font-family: inherit;
  font-size: 13px;
  cursor: pointer;
  text-align: center;
  padding: 4px;
}
.portal-link:hover { color: var(--text); }

.perks { display: flex; flex-direction: column; gap: 16px; }
.perk { display: flex; gap: 14px; align-items: flex-start; }
.perk-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  font-size: 22px;
  background: var(--accent-soft);
  border-radius: var(--radius-sm);
}
.perk-title { font-weight: 800; }
.perk-text { font-size: 14px; line-height: 1.45; margin-top: 2px; }

.free-note { text-align: center; font-size: 13px; line-height: 1.5; padding: 0 12px; }

.founder { text-align: center; line-height: 1.5; }
.active-plan { text-align: center; background: var(--green-soft); border-color: var(--green); }

.buy { display: flex; flex-direction: column; align-items: center; gap: 12px; }

.plan-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
.plan-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px 12px 14px;
  background: var(--card);
  border: 2px solid var(--border);
  border-radius: var(--radius);
  font-family: inherit;
  color: var(--text);
  cursor: pointer;
  transition: border-color 0.15s ease;
}
.plan-card.selected { border-color: var(--accent); background: var(--accent-soft); }
.plan-label { font-size: 13px; font-weight: 700; color: var(--text-dim); }
.plan-card.selected .plan-label { color: var(--accent); }
.plan-price { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
.plan-price small { font-size: 12px; font-weight: 600; color: var(--text-dim); margin-left: 2px; }
.plan-note {
  position: absolute;
  top: -9px;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: #fff;
  background: var(--green);
  border-radius: var(--radius-pill);
  padding: 2px 9px;
  white-space: nowrap;
}

.price { display: flex; align-items: baseline; gap: 6px; }
.price-amount { font-size: 34px; font-weight: 800; letter-spacing: -0.02em; }
.price-per { font-size: 15px; color: var(--text-dim); font-weight: 600; }
.cta { font-size: 16px; padding: 16px; width: 100%; }
.reassure { font-size: 13px; text-align: center; }
.back-link { text-align: center; font-size: 14px; }
</style>
