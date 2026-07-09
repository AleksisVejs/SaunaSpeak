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
  const error = ref('')

  let recorder = null
  let chunks = []
  let stream = null

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
    recorder.ondataavailable = (e) => chunks.push(e.data)
    recorder.onstop = () => {
      if (takeUrl.value) URL.revokeObjectURL(takeUrl.value)
      takeUrl.value = URL.createObjectURL(new Blob(chunks, { type: recorder.mimeType }))
      stream?.getTracks().forEach((t) => t.stop())
      stream = null
    }
    recorder.start()
    recording.value = true
  }

  function stop() {
    if (recorder && recording.value) recorder.stop()
    recording.value = false
  }

  function discard() {
    stop()
    if (takeUrl.value) URL.revokeObjectURL(takeUrl.value)
    takeUrl.value = null
  }

  onBeforeUnmount(discard)

  return { supported, recording, takeUrl, error, start, stop, discard }
}
