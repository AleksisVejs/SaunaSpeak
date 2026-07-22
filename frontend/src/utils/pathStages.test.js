import { describe, expect, it } from 'vitest'
import { PATH_STAGES, pathStageLevel, pathStageName, pathStageSlug } from './pathStages'

describe('learner-facing path stages', () => {
  it('turns internal curriculum levels into plain-language stage names', () => {
    expect(pathStageName('A0')).toBe('First words')
    expect(pathStageName('A1')).toBe('Everyday life')
    expect(pathStageName('A2')).toBe('Stories & opinions')
    expect(pathStageName('B1')).toBe('Real-world Finnish')
    expect(pathStageName('B2')).toBe('Nuance & expression')
  })

  it('does not expose proficiency codes in any stage name', () => {
    expect(PATH_STAGES.map((stage) => stage.name).join(' ')).not.toMatch(/\b(?:A0|A1|A2|B1|B2|C1)\b/)
  })

  it('has a safe label for future or unknown internal levels', () => {
    expect(pathStageName('future')).toBe('Next stage')
  })

  it('uses friendly checkpoint routes and keeps old bookmarks compatible', () => {
    expect(pathStageSlug('A1')).toBe('everyday-life')
    expect(pathStageLevel('everyday-life')).toBe('A1')
    expect(pathStageLevel('a1')).toBe('A1')
  })
})
