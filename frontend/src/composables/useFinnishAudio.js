// Central Finnish pronunciation helper used everywhere in the app.
//
// Strategy: play a pre-generated native-neural MP3 when one exists, and fall
// back to the browser SpeechSynthesis voice otherwise. This keeps a single
// consistent voice for sentences, tapped words, the recap and the word bank.

let wordManifest = null // { "löylyä": "/audio/words/loylya-ab12cd.mp3", ... }
let manifestPromise = null

// Load the word → MP3 map once (static file from Laravel's public/, no auth).
function loadManifest() {
  if (wordManifest) return Promise.resolve(wordManifest)
  if (manifestPromise) return manifestPromise

  manifestPromise = fetch('/audio/words.json')
    .then((res) => (res.ok ? res.json() : {}))
    .then((data) => (wordManifest = data || {}))
    .catch(() => (wordManifest = {})) // no manifest yet → TTS everywhere
  return manifestPromise
}

// Kick off the fetch eagerly so the first tap is instant.
loadManifest()

function normalizeWord(word) {
  return word.toLowerCase().replace(/[^\p{L}\p{N}'-]/gu, '')
}

let currentAudio = null

function stopAll() {
  if (currentAudio) {
    currentAudio.pause()
    currentAudio = null
  }
  if ('speechSynthesis' in window) speechSynthesis.cancel()
}

function speakTts(text, rate) {
  if (!('speechSynthesis' in window)) return
  const utterance = new SpeechSynthesisUtterance(text)
  utterance.lang = 'fi-FI'
  const voice = speechSynthesis.getVoices().find((v) => v.lang.toLowerCase().startsWith('fi'))
  if (voice) utterance.voice = voice
  utterance.rate = rate
  speechSynthesis.speak(utterance)
}

function playUrl(url, rate, fallbackText) {
  stopAll()
  const audio = new Audio(url)
  audio.playbackRate = rate
  currentAudio = audio
  audio.onerror = () => {
    currentAudio = null
    if (fallbackText) speakTts(fallbackText, rate)
  }
  audio.play().catch(() => {
    currentAudio = null
    if (fallbackText) speakTts(fallbackText, rate)
  })
}

// Promise-based playback for sequential listening (resolves when the clip
// ends, errors, or is stopped externally via stopAll → 'pause').
function playUrlAsync(url, rate, fallbackText) {
  stopAll()
  return new Promise((resolve) => {
    const audio = new Audio(url)
    audio.playbackRate = rate
    currentAudio = audio
    let settled = false
    const done = () => {
      if (settled) return
      settled = true
      if (currentAudio === audio) currentAudio = null
      resolve()
    }
    audio.onended = done
    audio.onpause = done // external stopAll()
    audio.onerror = async () => {
      if (settled) return
      if (fallbackText) await speakTtsAsync(fallbackText, rate)
      done()
    }
    audio.play().catch(audio.onerror)
  })
}

function speakTtsAsync(text, rate) {
  return new Promise((resolve) => {
    if (!('speechSynthesis' in window)) return resolve()
    const utterance = new SpeechSynthesisUtterance(text)
    utterance.lang = 'fi-FI'
    const voice = speechSynthesis.getVoices().find((v) => v.lang.toLowerCase().startsWith('fi'))
    if (voice) utterance.voice = voice
    utterance.rate = rate
    utterance.onend = resolve
    utterance.onerror = resolve
    speechSynthesis.speak(utterance)
  })
}

export function useFinnishAudio() {
  // Play an explicit audio_url (sentence MP3) with TTS fallback.
  function playSentence(text, audioUrl, rate = 0.85) {
    if (audioUrl) {
      playUrl(audioUrl, rate, text)
    } else {
      stopAll()
      speakTts(text, rate)
    }
  }

  // Like playSentence, but resolves when playback finishes — for playing
  // a whole lesson back to back.
  function playSentenceAsync(text, audioUrl, rate = 0.85) {
    if (audioUrl) return playUrlAsync(audioUrl, rate, text)
    stopAll()
    return speakTtsAsync(text, rate)
  }

  // Play a single word: look it up in the manifest, else TTS.
  async function playWord(word, rate = 0.85) {
    const key = normalizeWord(word)
    const manifest = await loadManifest()
    const url = manifest[key]
    if (url) {
      playUrl(url, rate, word)
    } else {
      stopAll()
      speakTts(word, rate)
    }
  }

  return { playSentence, playSentenceAsync, playWord, stop: stopAll }
}
