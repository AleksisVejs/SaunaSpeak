import { createRouter, createWebHistory } from 'vue-router'

const SITE = 'https://saunaspeak.com'
const DEFAULT_DESCRIPTION =
  "Learn spoken Finnish (puhekieli) - 'mä oon', not 'minä olen'. Daily 5-minute sessions with audio, spaced repetition, AI conversation practice and real-life roleplay. The learning path is free, forever."

const routes = [
  // Guests get the marketing front door; logged-in users skip to the app.
  // meta.title/description feed the head tags below - public pages carry the
  // SEO-relevant ones, app pages just get readable tab names. Every public
  // route also gets its own canonical URL: without it the SPA shell's static
  // tag declares every page a duplicate of the homepage and none get indexed.
  { path: '/', name: 'home', component: () => import('../pages/LandingPage.vue'), meta: { full: true, title: 'SaunaSpeak - Learn Spoken Finnish (Puhekieli), the Finnish Finns Actually Speak', description: DEFAULT_DESCRIPTION } },
  { path: '/login', name: 'login', component: () => import('../pages/LoginPage.vue'), meta: { guest: true, title: 'Log in - SaunaSpeak', description: 'Log in to SaunaSpeak and continue your spoken-Finnish streak.' } },
  { path: '/register', name: 'register', component: () => import('../pages/RegisterPage.vue'), meta: { guest: true, title: 'Create a free account - SaunaSpeak', description: 'Create a free SaunaSpeak account: daily 5-minute spoken-Finnish sessions with audio and spaced repetition, free forever.' } },
  // OAuth landing: no guest/auth meta - it flips from logged-out to logged-in
  // mid-visit and must not be bounced by either guard while doing so.
  { path: '/auth/google', name: 'auth-google', component: () => import('../pages/AuthGooglePage.vue'), meta: { title: 'Signing in - SaunaSpeak' } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('../pages/ForgotPasswordPage.vue'), meta: { guest: true, title: 'Reset your password - SaunaSpeak', description: 'Request a password-reset link for your SaunaSpeak account.' } },
  { path: '/reset-password', name: 'reset-password', component: () => import('../pages/ResetPasswordPage.vue'), meta: { guest: true, title: 'Choose a new password - SaunaSpeak', description: 'Choose a new password for your SaunaSpeak account.' } },
  { path: '/try', name: 'try', component: () => import('../pages/TryPage.vue'), meta: { title: 'Try spoken Finnish - no account needed - SaunaSpeak', description: 'Hear and learn six real spoken-Finnish sentences right now - no account, no signup. This is the Finnish Finns actually speak.' } },
  { path: '/lessons', name: 'lessons-index', component: () => import('../pages/LessonsIndexPage.vue'), meta: { title: 'Spoken Finnish lessons, free to read - SaunaSpeak', description: 'Browse every SaunaSpeak lesson: real spoken Finnish (puhekieli) with the written form, word-by-word explanations and audio - A0 to C1, free to read.' } },
  // Title/description are placeholders here - the page overwrites them with
  // the fetched lesson's own SEO text (see usePageHead).
  { path: '/lessons/:slug', name: 'lesson-preview', component: () => import('../pages/LessonPreviewPage.vue'), meta: { title: 'Spoken Finnish lesson - SaunaSpeak', description: DEFAULT_DESCRIPTION } },
  { path: '/pricing', name: 'pricing', component: () => import('../pages/PricingPage.vue'), meta: { title: 'Pricing - the learning path is free forever - SaunaSpeak', description: 'SaunaSpeak pricing: the full spoken-Finnish learning path is free forever. Löyly+ (€4.99/month) adds AI conversation practice and real-life roleplay.' } },
  // full: the comparison table needs more width than the 560px reading column.
  { path: '/compare', name: 'compare', component: () => import('../pages/ComparePage.vue'), meta: { full: true, title: 'Best apps to learn Finnish, compared honestly (2026) - SaunaSpeak', description: 'SaunaSpeak vs Duolingo vs Pimsleur vs SuomiSpeak - an honest comparison of Finnish learning apps: spoken Finnish (puhekieli), free tiers, AI practice, grammar depth and price.' } },
  { path: '/privacy', name: 'privacy', component: () => import('../pages/PrivacyPage.vue'), meta: { title: 'Privacy policy - SaunaSpeak', description: 'What SaunaSpeak stores, why, and your rights over your data.' } },
  { path: '/terms', name: 'terms', component: () => import('../pages/TermsPage.vue'), meta: { title: 'Terms of service - SaunaSpeak', description: 'The terms for using SaunaSpeak, the spoken-Finnish learning app.' } },
  { path: '/onboarding', name: 'onboarding', component: () => import('../pages/OnboardingFlow.vue'), meta: { auth: true } },
  { path: '/dashboard', name: 'dashboard', component: () => import('../pages/DashboardPage.vue'), meta: { auth: true, title: 'Learn - SaunaSpeak' } },
  { path: '/lesson/:id', name: 'lesson', component: () => import('../pages/LessonPage.vue'), meta: { auth: true } },
  { path: '/session', name: 'session', component: () => import('../pages/SessionPage.vue'), meta: { auth: true } },
  { path: '/words', name: 'words', component: () => import('../pages/WordBankPage.vue'), meta: { auth: true } },
  { path: '/words/review', name: 'words-review', component: () => import('../pages/WordReviewPage.vue'), meta: { auth: true } },
  { path: '/mistakes/review', name: 'mistakes-review', component: () => import('../pages/MistakeReviewPage.vue'), meta: { auth: true, title: 'Mistake review - SaunaSpeak' } },
  { path: '/profile', name: 'profile', component: () => import('../pages/ProfilePage.vue'), meta: { auth: true } },
  { path: '/checkpoint/:level', name: 'checkpoint', component: () => import('../pages/CheckpointPage.vue'), meta: { auth: true } },
  { path: '/chat', name: 'chat', component: () => import('../pages/ChatPage.vue'), meta: { auth: true, full: true, title: 'Sauna Chat - SaunaSpeak' } },
  { path: '/scenarios', name: 'scenarios', component: () => import('../pages/ScenariosPage.vue'), meta: { auth: true, title: 'Situations - SaunaSpeak' } },
  { path: '/upgrade', name: 'upgrade', component: () => import('../pages/UpgradePage.vue'), meta: { auth: true } },
  { path: '/record', name: 'record', component: () => import('../pages/RecorderPage.vue'), meta: { auth: true, title: 'Recording studio - SaunaSpeak' } },
  { path: '/admin', name: 'admin', component: () => import('../pages/AdminPage.vue'), meta: { auth: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  // New page starts at the top; back/forward restores where the user was.
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, top: 12 }
    return { top: 0 }
  }
})

router.beforeEach((to) => {
  const loggedIn = !!localStorage.getItem('token')
  const onboarded = !!localStorage.getItem('ss_onboarded')
  if (to.name === 'home' && loggedIn) return { name: 'dashboard' }
  if (to.meta.auth && !loggedIn) return { name: 'login' }
  if (to.meta.guest && loggedIn) return { name: 'dashboard' }
  // Send logged-in users who haven't done the intake through it first.
  if (loggedIn && !onboarded && to.meta.auth && to.name !== 'onboarding') {
    return { name: 'onboarding' }
  }
})

// Per-route head tags. The static ones in index.html are the crawler's first
// impression; these keep them true after client-side navigation (title for
// every route, canonical + description for the public ones).
function setHeadTag(selector, create, value) {
  let el = document.head.querySelector(selector)
  if (!el) {
    el = create()
    document.head.appendChild(el)
  }
  value(el)
}

router.afterEach((to) => {
  document.title = to.meta.title ?? 'SaunaSpeak - Learn Spoken Finnish'

  setHeadTag('link[rel="canonical"]', () => {
    const link = document.createElement('link')
    link.rel = 'canonical'
    return link
  }, (el) => { el.href = SITE + (to.path === '/' ? '/' : to.path) })

  setHeadTag('meta[name="description"]', () => {
    const meta = document.createElement('meta')
    meta.name = 'description'
    return meta
  }, (el) => { el.content = to.meta.description ?? DEFAULT_DESCRIPTION })
})

export default router
