import { defineStore } from 'pinia'
import api from '../api'
import { usePrefs } from '../composables/usePrefs'

export const useSessionStore = defineStore('session', {
  state: () => ({
    sentences: [],
    index: 0,
    xpEarned: 0,
    bonusXp: 0,
    finished: false,
    // starts true so navigating to /session shows the spinner, not a flash of "all caught up"
    loading: true,
    requeuedIds: [],
    // Tracks whether /progress/complete already succeeded for the current
    // sentence, so a retry (after /session/complete fails) doesn't double-submit it.
    progressRecorded: false
  }),

  getters: {
    current: (s) => s.sentences[s.index] ?? null,
    total: (s) => s.sentences.length,
    progressPct: (s) => (s.sentences.length ? Math.round((s.index / s.sentences.length) * 100) : 0)
  },

  actions: {
    async loadToday() {
      this.loading = true
      this.index = 0
      this.xpEarned = 0
      this.bonusXp = 0
      this.finished = false
      this.requeuedIds = []
      this.progressRecorded = false
      try {
        // Session length follows the learner's daily goal from the intake quiz.
        const { dailyGoal } = usePrefs()
        const { data } = await api.get('/today-session', { params: { size: dailyGoal() } })
        this.sentences = data.sentences
      } finally {
        this.loading = false
      }
    },

    async completeCurrent(grade = 'good') {
      const sentence = this.current
      if (!sentence) return

      if (!this.progressRecorded) {
        const { data } = await api.post('/progress/complete', { sentence_id: sentence.id, grade })
        this.xpEarned += data.xp_gained
        this.progressRecorded = true

        // A lapsed sentence comes back once more at the end of this session -
        // retrying within the session is where the learning happens.
        if (grade === 'again' && !this.requeuedIds.includes(sentence.id)) {
          this.requeuedIds.push(sentence.id)
          this.sentences.push({ ...sentence, status: data.status })
        }
      }

      if (this.index + 1 >= this.sentences.length) {
        const res = await api.post('/session/complete')
        this.bonusXp = res.data.xp_gained
        this.finished = true
        // The retention event that matters: a full session, start to finish.
        window.umami?.track('session_complete')
      } else {
        this.index++
      }
      this.progressRecorded = false
    }
  }
})
