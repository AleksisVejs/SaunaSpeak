import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  // Guests get the marketing front door; logged-in users skip to the app.
  { path: '/', name: 'home', component: () => import('../pages/LandingPage.vue'), meta: { full: true } },
  { path: '/login', name: 'login', component: () => import('../pages/LoginPage.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('../pages/RegisterPage.vue'), meta: { guest: true } },
  { path: '/try', name: 'try', component: () => import('../pages/TryPage.vue') },
  { path: '/onboarding', name: 'onboarding', component: () => import('../pages/OnboardingFlow.vue'), meta: { auth: true } },
  { path: '/dashboard', name: 'dashboard', component: () => import('../pages/DashboardPage.vue'), meta: { auth: true } },
  { path: '/lesson/:id', name: 'lesson', component: () => import('../pages/LessonPage.vue'), meta: { auth: true } },
  { path: '/session', name: 'session', component: () => import('../pages/SessionPage.vue'), meta: { auth: true } },
  { path: '/words', name: 'words', component: () => import('../pages/WordBankPage.vue'), meta: { auth: true } },
  { path: '/words/review', name: 'words-review', component: () => import('../pages/WordReviewPage.vue'), meta: { auth: true } },
  { path: '/profile', name: 'profile', component: () => import('../pages/ProfilePage.vue'), meta: { auth: true } },
  { path: '/checkpoint/:level', name: 'checkpoint', component: () => import('../pages/CheckpointPage.vue'), meta: { auth: true } },
  { path: '/chat', name: 'chat', component: () => import('../pages/ChatPage.vue'), meta: { auth: true, full: true } },
  { path: '/scenarios', name: 'scenarios', component: () => import('../pages/ScenariosPage.vue'), meta: { auth: true } },
  { path: '/upgrade', name: 'upgrade', component: () => import('../pages/UpgradePage.vue'), meta: { auth: true } },
  { path: '/admin', name: 'admin', component: () => import('../pages/AdminPage.vue'), meta: { auth: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes
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

export default router
