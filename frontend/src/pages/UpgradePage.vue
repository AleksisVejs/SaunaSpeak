<script setup>
// Löyly+ upgrade page. Checkout happens on Stripe's hosted page (redirect);
// the webhook flips the account to premium. Cancelling happens right here -
// confirmed in-page, access runs until the paid period ends, reversible
// until then via Resume.
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

// Two-step cancel: button arms the inline confirmation, nothing happens
// until the learner confirms.
const confirmingCancel = ref(false)
const busyCancel = ref(false)

const PERKS = [
  { icon: '💬', title: 'Sauna Chat with Väinö', text: 'Unlimited free-form conversation practice - the fastest way to find your gaps.' },
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
  } catch {
    error.value = 'Could not cancel. Please try again.'
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
  } catch {
    error.value = 'Could not resume. Please try again.'
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
      <img class="hero-vaino" src="/vaino-loyly.png" alt="Väinö throwing löyly" />
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

      <button v-else class="btn btn-primary btn-block cta" :disabled="starting" @click="upgrade">
        {{ starting ? 'Opening checkout…' : '♨️ Upgrade to Löyly+ - €4.99/month' }}
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
