import { describe, expect, it } from 'vitest'
import { estimateFormants, voicedSpan } from './formants'
import { contrastKey, judgeContrast, orderContrast } from './vowelContrast'

const RATE = 10000

/**
 * Synthesise a vowel: an impulse train (the glottal source) through a cascade
 * of two-pole resonators (the vocal tract). This is the classic source-filter
 * model, and it gives us signals whose true formants we know exactly - the
 * only way to check a formant estimator without a labelled speech corpus.
 */
function synthVowel(formants, { f0 = 120, seconds = 0.4, rate = RATE, bandwidth = 80 } = {}) {
  const n = Math.round(seconds * rate)
  const x = new Float64Array(n)

  const period = Math.round(rate / f0)
  for (let i = 0; i < n; i += period) x[i] = 1

  let out = x
  for (const f of formants) {
    const r = Math.exp((-Math.PI * bandwidth) / rate)
    const theta = (2 * Math.PI * f) / rate
    const a1 = 2 * r * Math.cos(theta)
    const a2 = -(r * r)

    const y = new Float64Array(n)
    for (let i = 0; i < n; i++) {
      y[i] = out[i] + (i >= 1 ? a1 * y[i - 1] : 0) + (i >= 2 ? a2 * y[i - 2] : 0)
    }
    out = y
  }

  // Normalise, then pad with silence so the voiced-span finder has something
  // to actually trim - a bare vowel would pass even a broken implementation.
  let peak = 0
  for (const v of out) peak = Math.max(peak, Math.abs(v))

  const pad = Math.round(0.1 * rate)
  const padded = new Float32Array(pad + n + pad)
  for (let i = 0; i < n; i++) padded[pad + i] = out[i] / peak

  return padded
}

describe('voicedSpan', () => {
  it('trims the silent padding down to the vowel', () => {
    const rate = RATE
    const span = voicedSpan(synthVowel([700, 1200]), rate)

    // The vowel occupies samples 1000..5000; the middle-half rule should land
    // us comfortably inside that, not in the padding.
    expect(span).not.toBeNull()
    expect(span.start).toBeGreaterThan(1000)
    expect(span.end).toBeLessThan(5000)
    expect(span.end - span.start).toBeGreaterThan(0.05 * rate)
  })

  it('returns null for silence rather than inventing a span', () => {
    expect(voicedSpan(new Float32Array(5000), RATE)).toBeNull()
  })
})

describe('estimateFormants', () => {
  // Finnish monophthongs, front/back pairs that Kuulo drills.
  const VOWELS = {
    y: [300, 1900],
    u: [350, 800],
    'ä': [700, 1700],
    a: [650, 1100],
    'ö': [450, 1500],
    o: [500, 900],
  }

  for (const [vowel, [f1, f2]] of Object.entries(VOWELS)) {
    it(`recovers F2 of synthetic /${vowel}/`, () => {
      const got = estimateFormants(synthVowel([f1, f2, 2800]), RATE)

      expect(got).not.toBeNull()

      // F2 is the load-bearing measurement - the whole front/back verdict
      // rests on it - so it gets a tight bound. 15% still leaves the y/u gap
      // (an octave) unmistakable.
      expect(got.f2).toBeGreaterThan(f2 * 0.85)
      expect(got.f2).toBeLessThan(f2 * 1.15)

      // F1 gets a looser one, honestly rather than to make the suite pass.
      // For close vowels F1 sits near f0 (/y/: 300 Hz over a 120 Hz source),
      // where it collides with the first harmonics and LPC reliably reads it
      // high - /y/ comes back around 370 Hz here. That is a real limit of
      // this method, and the reason nothing downstream uses F1: it is exposed
      // for diagnostics only, never fed into judgeContrast.
      expect(got.f1).toBeGreaterThan(f1 * 0.75)
      expect(got.f1).toBeLessThan(f1 * 1.3)
    })
  }

  it('separates /y/ from /u/ by close to the expected octave gap', () => {
    const y = estimateFormants(synthVowel([300, 1900, 2800]), RATE)
    const u = estimateFormants(synthVowel([350, 800, 2800]), RATE)

    expect(Math.log2(y.f2 / u.f2)).toBeGreaterThan(1.0)
  })

  it('tracks a higher-pitched speaker, whose formants are unchanged', () => {
    // f0 is the voice, not the vowel. A 220 Hz speaker saying /y/ must still
    // measure ~1900 Hz F2, or the whole speaker-independence claim collapses.
    const got = estimateFormants(synthVowel([300, 1900, 2800], { f0: 220 }), RATE)

    expect(got).not.toBeNull()
    expect(got.f2).toBeGreaterThan(1900 * 0.85)
    expect(got.f2).toBeLessThan(1900 * 1.15)
  })

  it('returns null for silence', () => {
    expect(estimateFormants(new Float32Array(8000), RATE)).toBeNull()
  })

  it('returns null for a take too short to hold a vowel', () => {
    expect(estimateFormants(synthVowel([700, 1200], { seconds: 0.01 }), RATE)).toBeNull()
  })
})

describe('judgeContrast', () => {
  const key = contrastKey('y', 'u')

  it('calls a native-sized gap clear', () => {
    expect(judgeContrast({ frontF2: 1900, backF2: 800, contrastKey: key }).verdict).toBe('clear')
  })

  it('calls identical vowels merged', () => {
    expect(judgeContrast({ frontF2: 850, backF2: 800, contrastKey: key }).verdict).toBe('merged')
  })

  it('flags a reversed contrast as swapped, not merged', () => {
    expect(judgeContrast({ frontF2: 800, backF2: 1900, contrastKey: key }).verdict).toBe('swapped')
  })

  it('passes an intelligible gap smaller than a native one', () => {
    // Two thirds of native separation - accented but unambiguous. Failing
    // this learner would be the estimator being stricter than a Finn.
    expect(judgeContrast({ frontF2: 1500, backF2: 800, contrastKey: key }).verdict).toBe('clear')
  })

  it('is speaker-independent: scaling both vowels changes nothing', () => {
    const adult = judgeContrast({ frontF2: 1900, backF2: 800, contrastKey: key })
    const child = judgeContrast({ frontF2: 1900 * 1.3, backF2: 800 * 1.3, contrastKey: key })

    expect(child.verdict).toBe(adult.verdict)
    expect(child.octaves).toBeCloseTo(adult.octaves, 6)
  })
})

describe('orderContrast', () => {
  it('finds the front vowel whichever way the set lists it', () => {
    expect(orderContrast(['y', 'u'])).toEqual({ front: 'y', back: 'u' })
    expect(orderContrast(['a', 'ä'])).toEqual({ front: 'ä', back: 'a' })
  })

  it('declines pairs that are not a front/back contrast', () => {
    expect(orderContrast(['t', 'tt'])).toBeNull()
    expect(orderContrast(['y'])).toBeNull()
  })
})
