import { onBeforeUnmount, ref } from 'vue'

/**
 * Microphone recorder for shadowing practice: record a take, replay it,
 * compare with the native audio. One take at a time; a new recording
 * replaces the previous one.
 */
export function useVoiceRecorder() {
  const supported =
    typeof window !== 'undefined' &&
    !!navigator.mediaDevices?.getUserMedia &&
    'MediaRecorder' in window

  const recording = ref(false)
  const takeUrl = ref(null) // object URL of the last take
  const takeBlob = ref(null) // the same take as data, for callers that analyse it
  const error = ref('')

  let recorder = null
  let chunks = []
  let stream = null
  // Resolved when the current take's blob is ready. MediaRecorder finishes
  // asynchronously, so "stop() then read takeBlob" races; awaiting this does
  // not.
  let pending = null

  async function start() {
    if (!supported || recording.value) return
    error.value = ''
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch {
      error.value = 'Microphone access was blocked.'
      return
    }
    chunks = []
    recorder = new MediaRecorder(stream)
    let settle
    pending = new Promise((resolve) => (settle = resolve))

    recorder.ondataavailable = (e) => chunks.push(e.data)
    recorder.onstop = () => {
      if (takeUrl.value) URL.revokeObjectURL(takeUrl.value)
      const blob = new Blob(chunks, { type: recorder.mimeType })
      takeBlob.value = blob
      takeUrl.value = URL.createObjectURL(blob)
      stream?.getTracks().forEach((t) => t.stop())
      stream = null
      settle(blob)
    }
    recorder.start()
    recording.value = true
  }

  function stop() {
    if (recorder && recording.value) recorder.stop()
    recording.value = false
  }

  /** Stop and resolve with the finished take. Null if nothing was recording. */
  async function stopAndGetBlob() {
    if (!recording.value || !pending) return null
    const wait = pending
    stop()
    return wait
  }

  function discard() {
    stop()
    if (takeUrl.value) URL.revokeObjectURL(takeUrl.value)
    takeUrl.value = null
    takeBlob.value = null
    pending = null
  }

  onBeforeUnmount(discard)

  return { supported, recording, takeUrl, takeBlob, error, start, stop, stopAndGetBlob, discard }
}
