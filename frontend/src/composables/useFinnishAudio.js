// Central Finnish pronunciation helper used everywhere in the app.
//
// Strategy: play a pre-generated native-neural MP3 when one exists, and fall
// back to the browser SpeechSynthesis voice otherwise. This keeps a single
// consistent voice for sentences, tapped words, the recap and the word bank.

import api from '../api'

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

// Emoji and pictographs must never reach a TTS engine — it reads them aloud.
function stripEmoji(text) {
  return text
    .replace(/[\p{Extended_Pictographic}\u{FE0F}\u{200D}]/gu, '')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

// Prefer a male Finnish voice (matches the fi-FI-HarriNeural lesson audio);
// Edge exposes "Microsoft Harri Online (Natural)", other browsers vary.
function pickFinnishVoice() {
  const voices = speechSynthesis.getVoices().filter((v) => v.lang.toLowerCase().startsWith('fi'))
  return voices.find((v) => /harri|onni|\bmale\b/i.test(v.name)) ?? voices[0] ?? null
}

function speakTts(text, rate) {
  if (!('speechSynthesis' in window)) return
  const utterance = new SpeechSynthesisUtterance(stripEmoji(text))
  utterance.lang = 'fi-FI'
  const voice = pickFinnishVoice()
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

  // Dynamic text (chat replies): synthesize server-side with the same male
  // neural voice as lesson audio, cached by content. Falls back to browser
  // speech where the server can't run edge-tts (e.g. shared hosting) — and
  // remembers that for the session so we don't keep asking.
  const spokenCache = new Map() // clean text → url | null
  let serverTtsDown = false
  async function playSpoken(text, rate = 0.95) {
    const clean = stripEmoji(text)
    if (!clean) return

    if (!spokenCache.has(clean) && !serverTtsDown) {
      try {
        const { data } = await api.post('/tts', { text: clean })
        spokenCache.set(clean, data.url ?? null)
      } catch (e) {
        spokenCache.set(clean, null)
        // 503 = edge-tts not available on this server; stop asking.
        if (e.response?.status === 503) serverTtsDown = true
      }
    }

    const url = spokenCache.get(clean)
    if (url) {
      playUrl(url, rate, clean)
    } else {
      stopAll()
      speakTts(clean, 0.85)
    }
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

  return { playSentence, playSentenceAsync, playSpoken, playWord, stop: stopAll }
}
