import { ref } from 'vue'
import api from '../api'

// Learner preferences captured in the intake flow. Local-first (instant),
// mirrored to the backend `user.preferences` column so they follow the
// learner across devices.
const STORAGE_KEY = 'ss_prefs'

// minutes/day → target sentences per session (backend clamps to 3–12).
const GOAL_BY_MINUTES = { 2: 3, 5: 6, 15: 12 }

function load() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}
  } catch {
    return {}
  }
}

const prefs = ref(load())

export function usePrefs() {
  // `placement` gates the backend's coarse level-seeding. The intake passes
  // false to defer it - the placement test is the accurate placement, and the
  // seed is only applied (via seedPlacement) if the learner skips the test.
  function savePrefs(patch, { placement = true } = {}) {
    const next = { ...prefs.value, ...patch }
    if (patch.minutes != null) next.dailyGoal = GOAL_BY_MINUTES[patch.minutes] ?? 6
    prefs.value = next
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    localStorage.setItem('ss_onboarded', '1')

    // Mirror to the server (fire-and-forget) so prefs survive device switches.
    if (localStorage.getItem('token')) {
      api.post('/preferences', { preferences: next, apply_placement: placement }).catch(() => {})
    }
  }

  // Apply the coarse level placement now - used when a learner declines the
  // intake placement test (or doesn't test out of the first level), so they
  // still get the head start their self-reported level earns. No-ops on the
  // server for a blank level or an account that already has progress.
  function seedPlacement() {
    if (!localStorage.getItem('token')) return Promise.resolve()
    return api.post('/preferences', { preferences: prefs.value, apply_placement: true }).catch(() => {})
  }

  // New device, existing account: adopt the server copy when local is empty.
  function adoptServerPrefs(serverPrefs) {
    if (!serverPrefs || Object.keys(prefs.value).length) return
    prefs.value = serverPrefs
    localStorage.setItem(STORAGE_KEY, JSON.stringify(serverPrefs))
    localStorage.setItem('ss_onboarded', '1')
  }

  function hasOnboarded() {
    return !!localStorage.getItem('ss_onboarded')
  }

  // Deliberate logout: drop the local mirror so the next account on this
  // device doesn't inherit these prefs or skip its own intake.
  function clearPrefs() {
    prefs.value = {}
    localStorage.removeItem(STORAGE_KEY)
    localStorage.removeItem('ss_onboarded')
  }

  const dailyGoal = () => prefs.value.dailyGoal ?? 6

  // Global audio playback speed (0.5x–2x, default 1x), set in the profile.
  const audioRate = () => Math.min(2, Math.max(0.5, prefs.value.audioRate ?? 1))

  return { prefs, savePrefs, seedPlacement, adoptServerPrefs, hasOnboarded, clearPrefs, dailyGoal, audioRate }
}
