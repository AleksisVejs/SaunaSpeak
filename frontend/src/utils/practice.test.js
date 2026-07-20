import { describe, expect, it } from 'vitest'
import { assemblyVerdict, sentenceWords } from './practice'

// The builder scaffold grades an assembled sentence. Finnish word order is
// flexible (case endings carry the roles), so the one thing these tests must
// pin is that a correct word set in another order is never called wrong.
describe('assemblyVerdict', () => {
  const expected = sentenceWords('Mä otan kahvin.')

  it('calls the everyday order perfect', () => {
    expect(assemblyVerdict(sentenceWords('Mä otan kahvin.'), expected)).toBe('perfect')
  })

  it('ignores case and punctuation', () => {
    expect(assemblyVerdict(['mä', 'otan', 'kahvin'], expected)).toBe('perfect')
  })

  it('accepts a valid reordering instead of failing it', () => {
    expect(assemblyVerdict(sentenceWords('Kahvin mä otan.'), expected)).toBe('reordered')
    expect(assemblyVerdict(sentenceWords('Otan mä kahvin.'), expected)).toBe('reordered')
  })

  it('rejects a wrong or missing word', () => {
    expect(assemblyVerdict(sentenceWords('Mä otan teen.'), expected)).toBe('wrong')
    expect(assemblyVerdict(sentenceWords('Mä otan.'), expected)).toBe('wrong')
    expect(assemblyVerdict(sentenceWords('Mä otan kahvin kiitos.'), expected)).toBe('wrong')
  })

  it('treats an empty attempt as wrong, not perfect', () => {
    expect(assemblyVerdict([], expected)).toBe('wrong')
  })
})

describe('sentenceWords', () => {
  it('splits and strips punctuation', () => {
    expect(sentenceWords('Moi, onks tääl tilaa?')).toEqual(['moi', 'onks', 'tääl', 'tilaa'])
  })

  it('survives empty input', () => {
    expect(sentenceWords('')).toEqual([])
    expect(sentenceWords(null)).toEqual([])
  })
})
