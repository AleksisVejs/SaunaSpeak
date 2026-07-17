import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// The store touches window.umami and the API; stub both so the flow logic can
// be tested in plain node without a DOM or a server.
globalThis.window = globalThis.window ?? {}

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

describe('lapse requeue', () => {
  it('re-inserts an "again" sentence before the woven tail and delays the commit', async () => {
    mockApi([sentence(1), sentence(2)], { listening: { id: 'a', title: 'A' } })
    const store = useSessionStore()
    await store.loadToday()
    const before = store.steps.length

    await store.completeSentence('again') // sentence 1 lapses
    expect(store.steps.length).toBe(before + 1) // one more sentence queued
    expect(store.wovenStart).toBe(3) // block grew, woven pushed back
    expect(store.steps[2].type).toBe('sentence') // requeued before the listen
    expect(store.committed).toBe(false) // block isn't done yet
  })
})
