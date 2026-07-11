// Scaffolding fade: as a sentence climbs the SRS ladder the exercise gets
// harder - recognition → gap-fill → listening dictation → full recall.
export function cardKind(status) {
  switch (status) {
    case 'learning':
      return 'cloze'
    case 'review':
      return 'dictation'
    case 'mastered':
      return 'recall'
    default:
      return 'study'
  }
}

// Trivial function words that make bad cloze gaps.
const STOP_WORDS = new Set([
  'mä', 'sä', 'se', 'on', 'oo', 'ei', 'en', 'ja', 'no', 'joo',
  'ihan', 'mun', 'sun', 'sul', 'meil', 'toi', 'tää', 'vaan'
])

// Deterministic: the longest non-trivial word is the one worth recalling.
export function clozeWord(text) {
  const words = text.match(/[\p{L}\p{N}'-]+/gu) ?? []
  const candidates = words.filter((w) => !STOP_WORDS.has(w.toLowerCase()))
  const pool = candidates.length ? candidates : words
  return pool.reduce((best, w) => (w.length > best.length ? w : best), pool[0] ?? '')
}

export function clozeText(text) {
  const word = clozeWord(text)
  return word ? text.replace(word, '_____') : text
}
