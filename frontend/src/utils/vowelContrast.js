// Turning two F2 measurements into something honest to tell a learner.
//
// The hard problem with absolute formant targets is that F2 depends on the
// speaker's vocal tract length as much as on the vowel. A child's /u/ can sit
// higher than a large adult male's /y/, so any fixed Hz boundary either fails
// half the users or is so wide it catches nothing. Published Finnish formant
// tables don't rescue this - they're averages over one speaker population.
//
// So we never judge one vowel in isolation. The learner says BOTH words of the
// pair, and we compare their two F2 values to each other. Vocal tract length
// cancels out, because it is the same tract twice. What's left is the only
// question worth asking anyway: can they make these two words sound different?
// A learner whose /y/ and /u/ come out identical is exactly the learner a Finn
// cannot follow - and that shows up here regardless of who is speaking.
//
// Measured in octaves (log2 of the ratio) rather than Hz, because a 300 Hz gap
// means something completely different down at 800 Hz than up at 2200 Hz.

/**
 * How far apart the two vowels sit in a native speaker, in octaves of F2.
 *
 * These are approximate working figures (front y≈1900, ä≈1700, ö≈1500; back
 * u≈800, a≈1100, o≈900 Hz), NOT values read off a published Finnish corpus -
 * the standard measurements (Wiik 1965; Iivonen & Laukkanen 1993) are behind
 * paywalls we haven't checked them against. Treat them as calibration, and if
 * a native's recordings ever disagree, trust the recordings.
 *
 * What the method does NOT depend on is their precision. They only scale the
 * thresholds below; the direction is what carries the verdict, and that part
 * is definitional rather than empirical - a front vowel has a higher F2 than
 * a back one, which is what "front" means acoustically. Getting these numbers
 * somewhat wrong makes the check slightly strict or slightly lenient. It
 * cannot make it say front when the learner said back.
 */
const NATIVE_SEPARATION = {
  'y|u': 1.25,
  'a-umlaut|a': 0.63,
  'o-umlaut|o': 0.74,
}

/** Fallback when a set declares a contrast we have no reference for. */
const DEFAULT_SEPARATION = 0.8

/**
 * Fraction of native separation that counts as clearly distinct. Deliberately
 * well under 1.0: the goal is intelligibility, not sounding native, and
 * demanding a native-sized gap would fail learners a Finn would understand
 * perfectly. Below MERGED the two words are effectively the same word.
 */
const CLEAR = 0.5
const MERGED = 0.2

/**
 * Judge a produced contrast from the two F2 measurements.
 *
 * @param {object} o
 * @param {number} o.frontF2 F2 of the front-vowel word (y/ä/ö)
 * @param {number} o.backF2  F2 of the back-vowel word (u/a/o)
 * @param {string} o.contrastKey e.g. "y|u" - keys NATIVE_SEPARATION
 * @returns {{verdict: 'clear'|'close'|'merged'|'swapped', octaves: number, ratio: number}}
 */
export function judgeContrast({ frontF2, backF2, contrastKey }) {
  const target = NATIVE_SEPARATION[contrastKey] ?? DEFAULT_SEPARATION
  const octaves = Math.log2(frontF2 / backF2)

  // Negative means the front vowel came out LOWER than the back one: the two
  // are the wrong way round, not merely close. Worth its own message, because
  // the fix is different - it isn't "exaggerate more", it's "you have these
  // two swapped".
  if (octaves < -MERGED * target) {
    return { verdict: 'swapped', octaves, ratio: octaves / target }
  }

  const ratio = octaves / target
  const verdict = ratio >= CLEAR ? 'clear' : ratio >= MERGED ? 'close' : 'merged'

  return { verdict, octaves, ratio }
}

/**
 * What to actually say to the learner.
 *
 * Every string is scoped to the one thing we measured. None of them claims the
 * word was "correct" or "well pronounced" - we checked tongue position on one
 * vowel, and the wording has to stay inside that.
 *
 * @param {ReturnType<typeof judgeContrast>} judgement
 * @param {{front: string, back: string, frontWord: string, backWord: string}} labels
 */
export function contrastFeedback(judgement, labels) {
  const { front, back, frontWord, backWord } = labels

  switch (judgement.verdict) {
    case 'clear':
      return {
        tone: 'good',
        headline: `Clear difference between ${front} and ${back}.`,
        detail: `Your ${frontWord} and ${backWord} came out as two distinct words - that's the part a Finn needs to hear.`,
      }
    case 'close':
      return {
        tone: 'warn',
        headline: `${front} and ${back} are close together.`,
        detail: `They're different, but not by much. For ${front}, keep your tongue forward - say "ee", then round your lips without moving your tongue. ${back} is made further back.`,
      }
    case 'merged':
      return {
        tone: 'bad',
        headline: `${frontWord} and ${backWord} sounded like the same word.`,
        detail: `Both came out near ${back}. This is the substitution that stops Finns understanding a learner - ${front} needs the tongue much further forward.`,
      }
    case 'swapped':
      return {
        tone: 'bad',
        headline: `These came out the wrong way round.`,
        detail: `Your ${frontWord} sounded further back than your ${backWord}. Try them one at a time against the native audio.`,
      }
  }
}

/**
 * Which of a set's two vowels is the front one.
 *
 * Sets declare their contrast as ["y", "u"] and so on, front first by
 * convention - this makes that convention checkable rather than assumed.
 */
const FRONT_VOWELS = new Set(['y', 'ä', 'ö'])

export function orderContrast(contrast) {
  if (!Array.isArray(contrast) || contrast.length !== 2) return null
  const [a, b] = contrast
  if (FRONT_VOWELS.has(a) && !FRONT_VOWELS.has(b)) return { front: a, back: b }
  if (FRONT_VOWELS.has(b) && !FRONT_VOWELS.has(a)) return { front: b, back: a }
  return null // not a front/back pair - production check doesn't apply
}

/** Stable key for NATIVE_SEPARATION, avoiding non-ASCII in object keys. */
export function contrastKey(front, back) {
  const slug = (v) => ({ ä: 'a-umlaut', ö: 'o-umlaut' })[v] ?? v
  return `${slug(front)}|${slug(back)}`
}
