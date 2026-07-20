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

// Strip a word to its comparable core: case and punctuation carry no weight
// when checking an assembled sentence.
const normalizeWord = (w) => w.toLowerCase().replace(/[^\p{L}\p{N}'-]/gu, '')

export function sentenceWords(text) {
  return (text ?? '').split(/\s+/).map(normalizeWord).filter(Boolean)
}

/**
 * Grade an assembled sentence (the beginner "build it" scaffold).
 *
 * Finnish marks grammatical roles with case endings, not position, so several
 * orders of the same words are genuinely correct - only the emphasis moves.
 * Marking those wrong would be teaching learners a rule the language doesn't
 * have, so a correct word set in another order is 'reordered' (accepted, with
 * the everyday order shown), never 'wrong'.
 *
 * @returns {'perfect'|'reordered'|'wrong'}
 */
export function assemblyVerdict(attempt, expected) {
  const a = attempt.map(normalizeWord).filter(Boolean)
  const b = expected.map(normalizeWord).filter(Boolean)

  if (a.length === b.length && a.every((w, i) => w === b[i])) return 'perfect'
  if (a.length === b.length && [...a].sort().join(' ') === [...b].sort().join(' ')) return 'reordered'
  return 'wrong'
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

// Edit distance with early exit: we only ever care about tiny distances, so
// bail as soon as a whole row exceeds the cap (keeps long inputs cheap).
export function editDistance(a, b, cap = 3) {
  if (a === b) return 0
  if (Math.abs(a.length - b.length) > cap) return cap + 1
  let prev = Array.from({ length: b.length + 1 }, (_, i) => i)
  for (let i = 1; i <= a.length; i++) {
    const curr = [i]
    let rowMin = i
    for (let j = 1; j <= b.length; j++) {
      curr[j] = Math.min(
        prev[j] + 1, // deletion
        curr[j - 1] + 1, // insertion
        prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1) // substitution
      )
      rowMin = Math.min(rowMin, curr[j])
    }
    if (rowMin > cap) return cap + 1
    prev = curr
  }
  return prev[b.length]
}

/**
 * Is the attempt the expected sentence give or take a slip of the finger?
 * One missing/extra/wrong letter (two on longer sentences) counts as a typo,
 * not a mistake worth an AI round-trip. Both inputs should be pre-normalized.
 */
export function typoDistance(attempt, expected) {
  const allowed = expected.length >= 12 ? 2 : 1
  const d = editDistance(attempt, expected, allowed)
  return d <= allowed ? d : null
}
