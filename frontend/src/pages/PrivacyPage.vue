<script setup>
// Plain-language privacy policy. Everything here must stay true to the code:
// update this page whenever data collection actually changes.
const UPDATED = 'July 14, 2026'
</script>

<template>
  <div class="legal">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
    </div>

    <h1>Privacy policy</h1>
    <p class="muted updated">Last updated {{ UPDATED }}</p>

    <p>
      SaunaSpeak is a web app for learning spoken Finnish, operated as an independent
      product from Finland's neighborhood (EU). This page explains what we store, why,
      and what you can do about it - in plain language, because nobody learns Finnish
      to read legalese.
    </p>

    <h2>What we store</h2>
    <ul>
      <li><b>Account:</b> your name, email address and a hashed password (we can never read the password itself).</li>
      <li><b>Learning data:</b> which sentences and words you've studied, your self-grades, review schedule, streak, XP, checkpoints, preferences (goal, daily time, audio speed) and timezone. This is the product - spaced repetition can't work without it.</li>
      <li><b>Chat messages:</b> what you write to Väinö or a scenario character is sent to an AI provider to generate the reply (see below). Practice-attempt corrections are cached so identical mistakes get instant answers.</li>
      <li><b>Billing (Löyly+ only):</b> payments run entirely through <a href="https://stripe.com/privacy" rel="noopener" target="_blank">Stripe</a>. We store your Stripe customer and subscription IDs and your subscription status - never your card number.</li>
    </ul>

    <h2>What we deliberately don't do</h2>
    <ul>
      <li>No advertising, no selling or sharing data with data brokers - ever.</li>
      <li>No tracking cookies. Analytics run on <a href="https://umami.is/privacy" rel="noopener" target="_blank">Umami</a>, a cookieless, EU-hosted service that counts page views without identifying you.</li>
      <li>No social-media pixels, no fingerprinting.</li>
    </ul>

    <h2>Processors we rely on</h2>
    <p>
      A few services process data on our behalf, only to make features work:
      our hosting provider (servers and database), Stripe (payments),
      an email delivery provider (verification, password-reset and optional
      review-reminder mails), an AI provider (Anthropic, Google or OpenRouter -
      chat replies and practice corrections; messages are sent for processing,
      not used by us to train anything), and a speech-synthesis provider
      (Microsoft or Google) for generating Finnish audio.
    </p>

    <h2>Emails</h2>
    <p>
      We send account emails (verification, password reset) and - if you keep the
      toggle on in your profile - a daily reminder when reviews are due. The reminder
      can be switched off in your profile at any time.
    </p>

    <h2>Your rights</h2>
    <ul>
      <li><b>Export:</b> download everything we hold about you as JSON from your profile ("Download my data").</li>
      <li><b>Deletion:</b> delete your account from your profile - it removes your account, progress, word bank and review history immediately, and cancels any active subscription. This is irreversible.</li>
      <li><b>Correction & access:</b> for anything else (GDPR articles 15-21), email us and we'll sort it out.</li>
    </ul>

    <h2>Retention</h2>
    <p>
      We keep your data for as long as your account exists. Delete the account and
      it's gone from the live database immediately; encrypted backups rotate out
      within 30 days.
    </p>

    <h2>Contact</h2>
    <p>
      Questions, requests, complaints:
      <a href="mailto:mail@saunaspeak.com">mail@saunaspeak.com</a>.
      You also have the right to lodge a complaint with your local data-protection authority.
    </p>

    <p class="muted footer-links">
      <router-link to="/terms">Terms of service</router-link> ·
      <router-link to="/">Home</router-link>
    </p>
  </div>
</template>

<style scoped>
.legal { max-width: 640px; margin: 0 auto; padding: 16px 4px 60px; text-align: left; }
.page-top { margin-bottom: 20px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.legal h1 { font-size: 28px; margin-bottom: 4px; }
.updated { font-size: 13px; margin-bottom: 20px; }
.legal h2 { font-size: 18px; margin: 26px 0 8px; }
.legal p { font-size: 14.5px; line-height: 1.65; color: var(--text-dim); margin-bottom: 10px; }
.legal ul { padding-left: 20px; display: flex; flex-direction: column; gap: 8px; margin: 8px 0 12px; }
.legal li { font-size: 14.5px; line-height: 1.6; color: var(--text-dim); }
.legal b { color: var(--text); }
.legal a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
.footer-links { margin-top: 30px; font-size: 13px; }
</style>
