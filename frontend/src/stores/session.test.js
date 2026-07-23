import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// The store touches window.umami, localStorage and the API; stub them all so
// the flow logic can be tested in plain node without a DOM or a server.
globalThis.window = globalThis.window ?? {}

const store = new Map()
globalThis.localStorage = {
  getItem: (k) => (store.has(k) ? store.get(k) : null),
  setItem: (k, v) => store.set(k, String(v)),
  removeItem: (k) => store.delete(k),
  clear: () => store.clear()
}

vi.mock('../api', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('../composables/usePrefs', () => ({ usePrefs: () => ({ dailyGoal: () => 6 }) }))

import api from '../api'
import { useSessionStore } from './session'

const sentence = (id) => ({ id, finnish_text: `Lause ${id}`, english_text: `S ${id}`, status: 'new' })

function mockApi(sentences, woven) {
  api.get.mockResolvedValue({ data: { sentences, woven, due_count: 0 } })
  api.post.mockImplementation((url) => {
    if (url === '/progress/complete') return Promise.resolve({ data: { xp_gained: 10, status: 'learning' } })
    if (url === '/session/complete') return Promise.resolve({ data: { xp_gained: 50 } })
    return Promise.resolve({ data: {} })
  })
}

const sessionCompletes = () => api.post.mock.calls.filter((c) => c[0] === '/session/complete').length

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  localStorage.clear()
})

describe('buildSteps', () => {
  it('orders the day as sentences → listen → bend → listen → use', () => {
    const store = useSessionStore()
    const steps = store.buildSteps([sentence(1), sentence(2)], {
      listening: { id: 'a', title: 'A' },
      transform: { id: 'b', title: 'B' },
      listening2: { id: 'c', title: 'C' },
      use: null
    })
    expect(steps.map((s) => s.type)).toEqual(['sentence', 'sentence', 'listening', 'transform', 'listening', 'use'])
  })

  it('is empty on a caught-up day so the "all caught up" state shows', () => {
    const store = useSessionStore()
    expect(store.buildSteps([], { listening: { id: 'a' } })).toEqual([])
  })

  it('skips woven extras that were not supplied but always ends on a use step', () => {
    const store = useSessionStore()
    const steps = store.buildSteps([sentence(1)], {})
    expect(steps.map((s) => s.type)).toEqual(['sentence', 'use'])
  })
})

describe('session length', () => {
  it('follows the learner daily goal by default', async () => {
    mockApi([sentence(1)], {})
    await useSessionStore().loadToday()
    expect(api.get).toHaveBeenCalledWith('/today-session', { params: { size: 6 } })
  })

  // The reminder email's button names a number of sentences ("Review 5
  // sentences · about 3 min") and links to /session?size=5. If the store
  // ignored that the app would serve the full daily goal instead, and the
  // mail's one concrete promise - a small, timed ask - would be false.
  it('honours an explicit size so the email keeps its promise', async () => {
    mockApi([sentence(1)], {})
    await useSessionStore().loadToday({ size: 5 })
    expect(api.get).toHaveBeenCalledWith('/today-session', { params: { size: 5 } })
  })

  it('falls back to the daily goal when no size is given', async () => {
    mockApi([sentence(1)], {})
    await useSessionStore().loadToday({ fresh: true })
    expect(api.get).toHaveBeenCalledWith('/today-session', { params: { size: 6 } })
  })
})

describe('streak commit gate', () => {
  it('commits the day when the sentence block clears, not at the very end', async () => {
    mockApi([sentence(1), sentence(2)], { listening: { id: 'a', title: 'A' } })
    const store = useSessionStore()
    await store.loadToday()
    // steps: [sentence, sentence, listening, use]; wovenStart = 2
    expect(store.wovenStart).toBe(2)

    await store.completeSentence('good') // sentence 1 → no commit yet
    expect(sessionCompletes()).toBe(0)
    expect(store.committed).toBe(false)

    await store.completeSentence('good') // sentence 2 → block done → COMMIT
    expect(sessionCompletes()).toBe(1)
    expect(store.committed).toBe(true)
    expect(store.bonusXp).toBe(50)
    expect(store.finished).toBe(false) // listening + use still ahead

    await store.completeWoven(20) // listening
    await store.completeWoven(0) // use → session finished
    expect(store.finished).toBe(true)
    expect(sessionCompletes()).toBe(1) // still committed exactly once
  })

  it('still commits when there are no woven extras', async () => {
    mockApi([sentence(1)], {}) // steps: [sentence, use]; wovenStart = 1
    const store = useSessionStore()
    await store.loadToday()

    await store.completeSentence('good') // block (1 sentence) done → commit
    expect(sessionCompletes()).toBe(1)
    await store.completeWoven(0) // use → finished
    expect(store.finished).toBe(true)
    expect(sessionCompletes()).toBe(1)
  })
})

describe('resuming an abandoned session', () => {
  it('picks up at the same step instead of fetching a whole new block', async () => {
    mockApi([sentence(1), sentence(2), sentence(3)], { listening: { id: 'a', title: 'A' } })
    const first = useSessionStore()
    await first.loadToday()
    await first.completeSentence('good')
    expect(first.index).toBe(1)
    expect(first.xpEarned).toBe(10)

    // A fresh page load (new pinia, same browser storage).
    setActivePinia(createPinia())
    api.get.mockClear()
    const resumed = useSessionStore()
    await resumed.loadToday()

    expect(api.get).not.toHaveBeenCalled()
    expect(resumed.resumed).toBe(true)
    expect(resumed.index).toBe(1)
    expect(resumed.xpEarned).toBe(10)
    expect(resumed.steps.length).toBe(first.steps.length)
  })

  it('forgets the session once it is finished, so the next visit is fresh', async () => {
    mockApi([sentence(1)], {})
    const store = useSessionStore()
    await store.loadToday()
    await store.completeSentence('good')
    await store.completeWoven(0) // use step → finished
    expect(store.finished).toBe(true)

    setActivePinia(createPinia())
    api.get.mockClear()
    const next = useSessionStore()
    await next.loadToday()

    expect(api.get).toHaveBeenCalled()
    expect(next.resumed).toBe(false)
    expect(next.index).toBe(0)
  })

  it('ignores a saved session from another day', async () => {
    mockApi([sentence(1), sentence(2)], {})
    const store = useSessionStore()
    await store.loadToday()
    await store.completeSentence('good')

    // Age the stored session by a day.
    const saved = JSON.parse(localStorage.getItem('ss_session'))
    localStorage.setItem('ss_session', JSON.stringify({ ...saved, day: 'Tue Jan 01 2019' }))

    setActivePinia(createPinia())
    api.get.mockClear()
    const next = useSessionStore()
    await next.loadToday()

    expect(api.get).toHaveBeenCalled()
    expect(next.index).toBe(0)
  })

  it('fresh: true skips the saved session (the "another round" button)', async () => {
    mockApi([sentence(1), sentence(2)], {})
    const store = useSessionStore()
    await store.loadToday()
    await store.completeSentence('good')

    api.get.mockClear()
    await store.loadToday({ fresh: true })

    expect(api.get).toHaveBeenCalled()
    expect(store.index).toBe(0)
    expect(store.resumed).toBe(false)
  })
})

describe('lapse requeue', () => {
  it('re-inserts an "again" sentence before the woven tail', async () => {
    mockApi([sentence(1), sentence(2)], { listening: { id: 'a', title: 'A' } })
    const store = useSessionStore()
    await store.loadToday()
    const before = store.steps.length

    await store.completeSentence('again') // sentence 1 lapses
    expect(store.steps.length).toBe(before + 1) // one more sentence queued
    expect(store.wovenStart).toBe(3) // block grew, woven pushed back
    expect(store.steps[2].type).toBe('sentence') // requeued before the listen
    expect(store.committed).toBe(false) // still one original sentence to go
  })

  // The requeue moves the splice boundary but must not move the day's credit:
  // a learner who gets everything wrong does more cards, not a longer wait for
  // the streak. Every retention hook (streak, "N due tomorrow", the reminder
  // anchor, the free Situation offer) fires off this commit.
  it('commits the day after the block as served, not after the requeued cards', async () => {
    mockApi([sentence(1), sentence(2)], { listening: { id: 'a', title: 'A' } })
    const store = useSessionStore()
    await store.loadToday()

    expect(store.commitAt).toBe(2)

    await store.completeSentence('again') // sentence 1 lapses, requeued at the end
    expect(store.wovenStart).toBe(3) // boundary moved
    expect(store.commitAt).toBe(2) // credit did not

    await store.completeSentence('again') // sentence 2 lapses too
    expect(store.committed).toBe(true) // both served sentences cleared
    expect(sessionCompletes()).toBe(1)
    expect(store.finished).toBe(false) // requeues and the listen still ahead
  })

  it('restores a session saved before commitAt existed', async () => {
    mockApi([sentence(1), sentence(2)], {})
    const store = useSessionStore()
    await store.loadToday()

    // A session persisted by the previous build: wovenStart, no commitAt.
    const saved = JSON.parse(localStorage.getItem('ss_session'))
    delete saved.commitAt
    localStorage.setItem('ss_session', JSON.stringify(saved))

    const resumed = useSessionStore()
    await resumed.loadToday()
    expect(resumed.commitAt).toBe(saved.wovenStart)
  })
})
