import { describe, expect, it } from 'vitest'
import { TRY_SAMPLES } from './trySamples'

// The /try demo is the top of the funnel: 250 starts to 80 finishes in the
// Jul 12-23 window, more absolute loss than every downstream step combined.
// These guard the one property that makes the ear test an ear test - that the
// answer cannot be reached without listening. An earlier version pitted the
// spoken form against the written one on every card, which made the answer
// reliably the shorter string, so "tap the shorter one" scored 5/5 with the
// sound off. Content edits must not quietly bring that back.

const words = (s) => s.trim().split(/\s+/).length
const decoyOf = (card) => card.options.find((o) => o !== card.fi)
// Card 1 states the thesis (spoken vs textbook) and hands out a free win; the
// rest are real discrimination tests between two spoken sentences.
const earTests = TRY_SAMPLES.filter((c) => !c.options.includes(c.book))

describe('try demo cards', () => {
  it('offers exactly two options, one of which is what was played', () => {
    for (const card of TRY_SAMPLES) {
      expect(card.options).toHaveLength(2)
      expect(card.options).toContain(card.fi)
      expect(decoyOf(card)).toBeTruthy()
    }
  })

  it('keeps every card fully teachable after the guess', () => {
    for (const card of TRY_SAMPLES) {
      expect(card.audio).toMatch(/^\/audio\//)
      expect(card.book).toBeTruthy() // shown in the reveal, not as an option
      expect(card.en).toBeTruthy()
      expect(card.note).toBeTruthy()
    }
  })

  it('does not park the answer in the same slot every time', () => {
    const slots = new Set(TRY_SAMPLES.map((c) => c.options.indexOf(c.fi)))
    expect(slots.size).toBeGreaterThan(1)
  })

  it('cannot be solved by word count on the ear-test cards', () => {
    expect(earTests.length).toBeGreaterThanOrEqual(3)
    for (const card of earTests) {
      expect(words(decoyOf(card))).toBe(words(card.fi))
    }
  })

  it('defeats the "always tap the shorter option" strategy', () => {
    const solved = TRY_SAMPLES.filter((c) => c.fi.length < decoyOf(c).length).length
    // Card 1 is deliberately gettable; anything close to a clean sweep means
    // the decoys have drifted back to being longer written forms.
    expect(solved).toBeLessThanOrEqual(Math.ceil(TRY_SAMPLES.length / 2))
  })

  it('never repeats a decoy as another card\'s answer', () => {
    const answers = new Set(TRY_SAMPLES.map((c) => c.fi))
    for (const card of TRY_SAMPLES) {
      expect(answers.has(decoyOf(card))).toBe(false)
    }
  })
})
