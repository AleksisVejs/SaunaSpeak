import { defineStore } from 'pinia'
import api from '../api'
import { usePrefs } from '../composables/usePrefs'

// A Sauna Session is no longer a flat list of sentence cards - it's a woven
// sequence of STEPS: the sentence block first (recall), then the extras that
// make it a four-skill session - a whole conversation to LISTEN to, a Taivutus
// set to BEND, and a USE step (a roleplay for premium learners, a self-graded
// production prompt otherwise). The backend picks the woven extras by level
// (see App\Support\Themes); this store stitches them onto the end of the
// sentence steps and walks the learner through the lot.

// Leaving mid-session (a call, a notification, a closed tab) used to throw the
// whole session away: loadToday() refetched, the graded sentences were no
// longer due, and the learner got a NEW block of sentences plus the woven
// steps all over again - abandoning halfway made the day bigger, not smaller.
// The session is therefore mirrored to localStorage and resumed for the rest
// of the local day. Cleared the moment it finishes, so "another round" is a
// genuinely fresh fetch.
const SESSION_STORE = 'ss_session'

const localDay = () => new Date().toDateString()

function readSavedSession() {
  try {
    const raw = JSON.parse(localStorage.getItem(SESSION_STORE))
    // Same day, still has steps left to do, and shaped like a session.
    if (!raw || raw.day !== localDay() || !Array.isArray(raw.steps) || !raw.steps.length) return null
    if (typeof raw.index !== 'number' || raw.index >= raw.steps.length) return null

    return raw
  } catch {
    // No storage, or a payload from an older shape - start fresh.
    return null
  }
}

export function clearSavedSession() {
  try {
    localStorage.removeItem(SESSION_STORE)
  } catch {
    // storage blocked - nothing to clear
  }
}

export const useSessionStore = defineStore('session', {
  state: () => ({
    steps: [],
    // The studied sentences, kept apart from `steps` for the end-of-session
    // recap (unique, in the order first seen).
    sentences: [],
    index: 0,
    xpEarned: 0, // from sentence grades
    wovenXp: 0, // from listening / transform / scenario completions
    bonusXp: 0, // daily finish bonus
    finished: false,
    // starts true so navigating to /session shows the spinner, not a flash of "all caught up"
    loading: true,
    requeuedIds: [],
    // Where the woven steps begin - a re-queued lapsed sentence is spliced in
    // just before them, so it comes back at the end of the sentence block, not
    // after the listening.
    wovenStart: 0,
    // Whether the day has been committed (streak + daily bonus). Fires when the
    // sentence block clears, NOT at the very end - see advance().
    committed: false,
    // Tracks whether /progress/complete already succeeded for the current
    // sentence, so a retry (after /session/complete fails) doesn't double-submit it.
    progressRecorded: false,
    // True when this session was picked up from a previous visit - the page
    // says so once, so landing mid-session doesn't read as a glitch.
    resumed: false
  }),

  getters: {
    current: (s) => s.steps[s.index] ?? null,
    total: (s) => s.steps.length,
    progressPct: (s) => (s.steps.length ? Math.round((s.index / s.steps.length) * 100) : 0),
    // How many sentence cards are in play (for the "N sentences" copy).
    sentenceCount: (s) => s.steps.filter((st) => st.type === 'sentence').length
  },

  actions: {
    /**
     * Today's session: resumed from where the learner left off, or fetched
     * fresh. `fresh: true` forces a new block - that's the "another round"
     * button, which must never restore the session it just finished.
     *
     * `size` overrides the learner's daily goal for this one session. The
     * reminder email promises a specific, small number of sentences ("Review 5
     * sentences · about 3 min") to answer the real objection of a lapsed
     * learner, which is time - so the link has to deliver exactly that many,
     * not the 8 their intake goal asks for.
     */
    async loadToday({ fresh = false, size = null } = {}) {
      this.loading = true
      this.index = 0
      this.xpEarned = 0
      this.wovenXp = 0
      this.bonusXp = 0
      this.finished = false
      this.requeuedIds = []
      this.progressRecorded = false
      this.committed = false
      this.resumed = false

      const saved = fresh ? null : readSavedSession()
      if (saved) {
        this.steps = saved.steps
        this.sentences = saved.sentences ?? []
        this.index = saved.index
        this.xpEarned = saved.xpEarned ?? 0
        this.wovenXp = saved.wovenXp ?? 0
        this.bonusXp = saved.bonusXp ?? 0
        this.wovenStart = saved.wovenStart ?? saved.steps.length
        this.requeuedIds = saved.requeuedIds ?? []
        this.committed = saved.committed ?? false
        this.resumed = true
        this.loading = false

        return
      }

      try {
        // Session length follows the learner's daily goal from the intake quiz
        // unless a caller asked for a specific length (see the doc above).
        const { dailyGoal } = usePrefs()
        const { data } = await api.get('/today-session', { params: { size: size ?? dailyGoal() } })
        this.sentences = data.sentences
        this.steps = this.buildSteps(data.sentences, data.woven)
        this.wovenStart = data.sentences.length
        this.save()
      } finally {
        this.loading = false
      }
    },

    /** Mirror the live session so leaving the page doesn't lose the place. */
    save() {
      if (this.finished || !this.steps.length) {
        clearSavedSession()

        return
      }

      try {
        localStorage.setItem(
          SESSION_STORE,
          JSON.stringify({
            day: localDay(),
            steps: this.steps,
            sentences: this.sentences,
            index: this.index,
            xpEarned: this.xpEarned,
            wovenXp: this.wovenXp,
            bonusXp: this.bonusXp,
            wovenStart: this.wovenStart,
            requeuedIds: this.requeuedIds,
            committed: this.committed
          })
        )
      } catch {
        // storage blocked or full - the session just won't survive a reload
      }
    },

    // Sentence steps first (in the interleaved order the backend chose), then
    // the woven extras: listen → bend → use. Any extra the backend didn't
    // supply (light "2-minute" sessions, or a level with no matching asset) is
    // simply skipped. The USE step falls back to a self-graded production of a
    // sentence just studied when there's no roleplay to offer.
    buildSteps(sentences, woven = {}) {
      // No sentences due or new → a genuinely caught-up day. Keep the "all
      // caught up" state rather than spinning up a listening-only session.
      if (!sentences.length) return []

      const steps = sentences.map((s) => ({ type: 'sentence', sentence: s }))

      if (woven.listening) steps.push({ type: 'listening', data: woven.listening })
      if (woven.transform) steps.push({ type: 'transform', data: woven.transform })
      // A second conversation on the longest sessions (backend gates it by size)
      // - spaced after the drill so the input is distributed, not massed.
      if (woven.listening2) steps.push({ type: 'listening', data: woven.listening2 })

      // The USE beat: produce a sentence from today out loud, from memory - the
      // one activity in the loop that makes you generate rather than recall or
      // recognize (the output hypothesis). Self-graded, checked by the same AI
      // corrector the sentence cards use. When the backend matched a roleplay
      // scenario (premium), it rides along as a non-blocking "take it further"
      // CTA rather than pulling the learner off to another page mid-session.
      if (sentences.length) {
        const pick = sentences[Math.floor(Math.random() * sentences.length)]
        steps.push({ type: 'use', data: { sentence: pick, scenario: woven.use ?? null } })
      }

      return steps
    },

    // Grade the current sentence step. Drives the SRS schedule; a lapse re-queues
    // the sentence once, at the end of the sentence block.
    async completeSentence(grade = 'good') {
      const step = this.current
      if (!step || step.type !== 'sentence') return
      const sentence = step.sentence

      if (!this.progressRecorded) {
        const { data } = await api.post('/progress/complete', { sentence_id: sentence.id, grade })
        this.xpEarned += data.xp_gained
        this.progressRecorded = true

        // A lapsed sentence comes back once more before the woven steps -
        // retrying within the session is where the learning happens.
        if (grade === 'again' && !this.requeuedIds.includes(sentence.id)) {
          this.requeuedIds.push(sentence.id)
          this.steps.splice(this.wovenStart, 0, { type: 'sentence', sentence: { ...sentence, status: data.status } })
          this.wovenStart++
        }
      }

      await this.advance()
      this.progressRecorded = false
    },

    // A woven step (listening / transform / use) reports done, optionally with
    // XP its own endpoint already awarded.
    async completeWoven(xp = 0) {
      this.wovenXp += xp
      await this.advance()
    },

    // Commit the day: streak + daily bonus. Fires the moment the SENTENCE block
    // is done, not at the end of the woven tail. Streaks run on loss aversion,
    // so withholding the streak after the core daily work is the exact churn
    // trigger to avoid - the listen/bend/produce steps are enrichment, not a
    // gate on the streak. Idempotent server-side (once per local day).
    async commitDay() {
      const res = await api.post('/session/complete')
      this.bonusXp = res.data.xp_gained
      this.committed = true
    },

    // Move to the next step. Commits the day once the sentence block clears, and
    // marks the session finished (celebration) when the last step is done.
    async advance() {
      // The resume notice is for the moment of landing only.
      this.resumed = false

      // index+1 reaching wovenStart means the last sentence just cleared.
      if (!this.committed && this.index + 1 >= this.wovenStart) {
        await this.commitDay()
      }

      if (this.index + 1 >= this.steps.length) {
        this.finished = true
        // The retention event that matters: a full session, start to finish.
        window.umami?.track('session_complete')
      } else {
        this.index++
      }

      // Persist the new position (or clear it, now that it's finished).
      this.save()
    }
  }
})
