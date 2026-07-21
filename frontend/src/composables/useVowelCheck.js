// The production half of Kuulo: record both words of a contrast, measure
// whether they came out as two different vowels.
//
// Runs entirely in the browser - Web Audio decode, then plain arithmetic in
// utils/formants.js. No model download, no API call, no audio leaving the
// device, and nothing to pay for. That last point is not incidental: a
// pronunciation feature that costs per attempt is one we'd have to ration or
// put behind Löyly+, and the contrast this checks is the one three natives
// said actually breaks comprehension. It belongs on the free path.

import { computed, ref } from 'vue'
import { estimateFormants } from '../utils/formants'
import { contrastFeedback, contrastKey, judgeContrast, orderContrast } from '../utils/vowelContrast'

/**
 * LPC wants a sample rate where the formants of interest occupy a decent
 * share of the spectrum. At 44.1 kHz the order needed to model up to 4 kHz is
 * ~46, which is slow and numerically fragile; at 10 kHz it's 12 and stable.
 * Nyquist at 5 kHz is still comfortably above F2 for every Finnish vowel.
 */
const ANALYSIS_RATE = 10000

/** Decode a recorded Blob to mono Float32 PCM at ANALYSIS_RATE. */
async function decodeToMono(blob) {
  const bytes = await blob.arrayBuffer()

  // Decode at the device's own rate first: OfflineAudioContext will not decode
  // some browsers' MediaRecorder output at an arbitrary target rate, so we
  // decode natively and resample as a second step.
  const ctx = new (window.AudioContext || window.webkitAudioContext)()
  let decoded
  try {
    decoded = await ctx.decodeAudioData(bytes)
  } finally {
    ctx.close()
  }

  const frames = Math.max(1, Math.ceil((decoded.duration * ANALYSIS_RATE)))
  const offline = new OfflineAudioContext(1, frames, ANALYSIS_RATE)
  const src = offline.createBufferSource()
  src.buffer = decoded
  src.connect(offline.destination)
  src.start()

  const resampled = await offline.startRendering()
  return resampled.getChannelData(0)
}

/**
 * @param {object} set the Kuulo set - needs `contrast` (front/back vowel pair)
 */
export function useVowelCheck(set) {
  const takes = ref({ front: null, back: null }) // { blob, url, f2 }
  const analysing = ref(false)
  const result = ref(null)
  const error = ref('')

  const ordered = computed(() => orderContrast(set?.contrast))

  /** Production checking only applies to front/back vowel sets. */
  const applicable = computed(
    () => ordered.value !== null && typeof window !== 'undefined' && 'AudioContext' in window
  )

  const ready = computed(() => !!(takes.value.front && takes.value.back))

  /**
   * Store one side's recording and measure its F2 immediately, so a take that
   * can't be analysed is reported while the learner still remembers making it
   * - not at the end, after they've recorded the other word too.
   *
   * @param {'front'|'back'} side
   */
  async function addTake(side, blob) {
    error.value = ''
    result.value = null
    analysing.value = true

    try {
      const samples = await decodeToMono(blob)
      const formants = estimateFormants(samples, ANALYSIS_RATE)

      if (!formants) {
        // No usable vowel. This is a recording problem, never a verdict on the
        // learner - saying "wrong" here would be measuring the microphone.
        error.value = 'That take was too quiet or too short to read. Say the word again, a bit longer.'
        return false
      }

      const prev = takes.value[side]
      if (prev?.url) URL.revokeObjectURL(prev.url)

      takes.value = {
        ...takes.value,
        [side]: { blob, url: URL.createObjectURL(blob), f2: formants.f2, f1: formants.f1 },
      }
      return true
    } catch {
      error.value = "Couldn't read that recording. Try once more."
      return false
    } finally {
      analysing.value = false
    }
  }

  /** Compare the two stored takes. Only meaningful once both exist. */
  function judge(frontWord, backWord) {
    if (!ready.value || !ordered.value) return null

    const judgement = judgeContrast({
      frontF2: takes.value.front.f2,
      backF2: takes.value.back.f2,
      contrastKey: contrastKey(ordered.value.front, ordered.value.back),
    })

    result.value = {
      ...judgement,
      ...contrastFeedback(judgement, {
        front: ordered.value.front,
        back: ordered.value.back,
        frontWord,
        backWord,
      }),
    }
    return result.value
  }

  function reset() {
    for (const t of Object.values(takes.value)) if (t?.url) URL.revokeObjectURL(t.url)
    takes.value = { front: null, back: null }
    result.value = null
    error.value = ''
  }

  return { takes, analysing, result, error, applicable, ready, ordered, addTake, judge, reset }
}
