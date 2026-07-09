import { ref } from 'vue'

// Learner preferences captured in the intake flow. Stored locally for now;
// a backend `user.preferences` column can mirror this later without changing
// the call sites.
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
  function savePrefs(patch) {
    const next = { ...prefs.value, ...patch }
    if (patch.minutes != null) next.dailyGoal = GOAL_BY_MINUTES[patch.minutes] ?? 6
    prefs.value = next
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    localStorage.setItem('ss_onboarded', '1')
  }

  function hasOnboarded() {
    return !!localStorage.getItem('ss_onboarded')
  }

  const dailyGoal = () => prefs.value.dailyGoal ?? 6

  return { prefs, savePrefs, hasOnboarded, dailyGoal }
}
