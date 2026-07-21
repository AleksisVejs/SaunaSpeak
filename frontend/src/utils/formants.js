// Formant estimation: the acoustic half of Kuulo's production step.
//
// Kuulo drills the three contrasts natives named as comprehension-breaking -
// y/u, ä/a, ö/o. All three are the SAME articulatory contrast: front vs back
// tongue position, with rounding held constant. That contrast has one direct
// acoustic correlate, the second formant (F2): front vowels resonate high
// (y ~1900 Hz), back vowels low (u ~800 Hz). The gap is enormous - roughly an
// octave - which is why this is measurable honestly with plain DSP and no
// model, where "did they pronounce it correctly" in general is not.
//
// So we do not attempt phoneme recognition, a pronunciation score, or any
// judgement of overall accent. We measure one number that separates the two
// words the learner was asked to distinguish, and we say only what that number
// supports. Everything else is out of scope on purpose.
//
// Method: LPC (autocorrelation + Levinson-Durbin) on the steady-state vowel,
// then peak-pick the spectral envelope. Frame-wise with a median across
// frames, because a single frame lands on a glottal pulse often enough to
// throw F2 by hundreds of Hz.

/** LPC order. Rule of thumb: 2 + sampleRate/1000, so ~12 at the 10 kHz we resample to. */
function lpcOrder(sampleRate) {
  return Math.min(20, Math.max(8, Math.round(2 + sampleRate / 1000)))
}

/**
 * Levinson-Durbin: autocorrelation r[0..p] → prediction coefficients a[1..p],
 * where x[n] ≈ Σ a[k]·x[n-k]. Returns null if the recursion goes unstable
 * (silence, or a frame with no periodic structure to model).
 */
function levinson(r, p) {
  if (!(r[0] > 0)) return null

  const a = new Float64Array(p + 1)
  const tmp = new Float64Array(p + 1)
  let err = r[0]

  for (let i = 1; i <= p; i++) {
    let acc = r[i]
    for (let j = 1; j < i; j++) acc -= a[j] * r[i - j]

    const k = acc / err
    if (!Number.isFinite(k) || Math.abs(k) >= 1) return null // unstable filter

    tmp[i] = k
    for (let j = 1; j < i; j++) tmp[j] = a[j] - k * a[i - j]
    for (let j = 1; j <= i; j++) a[j] = tmp[j]

    err *= 1 - k * k
    if (err <= 0) return null
  }

  return a
}

/** Autocorrelation r[0..p] of a windowed frame. */
function autocorrelate(frame, p) {
  const r = new Float64Array(p + 1)
  for (let k = 0; k <= p; k++) {
    let sum = 0
    for (let n = k; n < frame.length; n++) sum += frame[n] * frame[n - k]
    r[k] = sum
  }
  return r
}

/**
 * Formants of one frame, by peak-picking the LPC spectral envelope.
 *
 * Evaluating |1/A(e^jw)| on a grid rather than root-solving the polynomial:
 * root-finding a degree-12 polynomial in JS is fiddly and buys precision we
 * have no use for - the y/u gap is ~1000 Hz and the grid is 10 Hz.
 *
 * @returns {number[]} peak frequencies in Hz, ascending
 */
function frameFormants(frame, sampleRate) {
  const p = lpcOrder(sampleRate)
  const a = levinson(autocorrelate(frame, p), p)
  if (!a) return []

  // 90 Hz floor keeps the search above f0 for any adult voice; 4 kHz ceiling
  // is well above F2 for every vowel and avoids the noisy top octave.
  const LO = 90
  const HI = 4000
  const STEP = 10

  const mags = []
  for (let f = LO; f <= HI; f += STEP) {
    const w = (2 * Math.PI * f) / sampleRate
    let re = 1
    let im = 0
    for (let k = 1; k <= p; k++) {
      re -= a[k] * Math.cos(w * k)
      im += a[k] * Math.sin(w * k)
    }
    const denom = Math.hypot(re, im)
    mags.push(denom > 0 ? 1 / denom : 0)
  }

  const peaks = []
  for (let i = 1; i < mags.length - 1; i++) {
    if (mags[i] > mags[i - 1] && mags[i] >= mags[i + 1]) peaks.push(LO + i * STEP)
  }
  return peaks
}

function median(xs) {
  if (xs.length === 0) return null
  const s = [...xs].sort((x, y) => x - y)
  const mid = s.length >> 1
  return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2
}

/**
 * The loud, steady middle of a recording - where the vowel lives.
 *
 * Learners leave silence at both ends and clip the consonant on at the front
 * ("suu" starts with a fricative whose spectrum is nothing like the vowel's),
 * so analysing the whole take measures the wrong thing. Take the span above a
 * fraction of peak energy, then keep only its middle half.
 *
 * @returns {{start: number, end: number}|null} sample offsets, or null if silent
 */
export function voicedSpan(samples, sampleRate) {
  const win = Math.max(1, Math.round(0.025 * sampleRate)) // 25 ms
  const hop = Math.max(1, Math.round(0.010 * sampleRate)) // 10 ms

  const energies = []
  for (let i = 0; i + win <= samples.length; i += hop) {
    let e = 0
    for (let n = i; n < i + win; n++) e += samples[n] * samples[n]
    energies.push({ at: i, e: e / win })
  }
  if (energies.length === 0) return null

  const peak = Math.max(...energies.map((f) => f.e))
  // Below this the take is silence or room tone, not speech. Absolute floor
  // as well as relative: a silent recording still has a "peak".
  if (peak < 1e-6) return null

  const loud = energies.filter((f) => f.e >= peak * 0.25)
  if (loud.length === 0) return null

  const first = loud[0].at
  const last = loud[loud.length - 1].at + win
  const quarter = (last - first) / 4

  return { start: Math.round(first + quarter), end: Math.round(last - quarter) }
}

/**
 * Estimate F1/F2 of the vowel in a mono recording.
 *
 * @param {Float32Array} samples mono PCM, ideally resampled to ~10 kHz
 * @param {number} sampleRate
 * @returns {{f1: number, f2: number, frames: number}|null} null when the take
 *          is too quiet, too short, or has no analysable vowel - callers must
 *          treat null as "say it again", never as a failed attempt.
 */
export function estimateFormants(samples, sampleRate) {
  const span = voicedSpan(samples, sampleRate)
  if (!span) return null

  const win = Math.round(0.030 * sampleRate) // 30 ms analysis window
  const hop = Math.round(0.010 * sampleRate)
  if (span.end - span.start < win) return null

  const f1s = []
  const f2s = []

  for (let i = span.start; i + win <= span.end; i += hop) {
    // Pre-emphasis flattens the -6 dB/octave glottal rolloff so the higher
    // formants are modelled as well as F1 - without it F2 peaks get swamped.
    const frame = new Float64Array(win)
    for (let n = 0; n < win; n++) {
      const cur = samples[span.start + (i - span.start) + n]
      const prev = n === 0 ? cur : samples[span.start + (i - span.start) + n - 1]
      const emphasised = cur - 0.97 * prev
      // Hamming window: rectangular framing leaks badly enough to invent peaks.
      frame[n] = emphasised * (0.54 - 0.46 * Math.cos((2 * Math.PI * n) / (win - 1)))
    }

    const peaks = frameFormants(frame, sampleRate)
    // F1 below 200 Hz is almost always f0 leaking through rather than a
    // formant; require two real peaks before trusting the frame at all.
    const usable = peaks.filter((f) => f >= 200)
    if (usable.length >= 2) {
      f1s.push(usable[0])
      f2s.push(usable[1])
    }
  }

  // Three good frames is 50 ms of vowel - less than that and the median is
  // not doing the job we added it for.
  if (f2s.length < 3) return null

  return { f1: median(f1s), f2: median(f2s), frames: f2s.length }
}
