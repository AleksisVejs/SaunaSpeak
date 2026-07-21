// Central Finnish pronunciation helper used everywhere in the app.
//
// Native neural MP3s only (fi-FI-HarriNeural): pre-generated files for
// sentences and words, on-demand server TTS for chat replies. The browser's
// SpeechSynthesis is deliberately NOT used - its voice varies per device
// (often female, clashing with the male lesson audio) and can double-fire
// alongside MP3 playback. No audio file → silence, never a random voice.

import api from '../api'
import { usePrefs } from './usePrefs'

let wordManifest = null // { "löylyä": "/audio/words/loylya-ab12cd.mp3", ... }
let manifestPromise = null

// Load the word → MP3 map once (static file from Laravel's public/, no auth).
function loadManifest() {
  if (wordManifest) return Promise.resolve(wordManifest)
  if (manifestPromise) return manifestPromise

  manifestPromise = fetch('/audio/words.json')
    .then((res) => (res.ok ? res.json() : {}))
    .then((data) => (wordManifest = data || {}))
    .catch(() => (wordManifest = {}))
  return manifestPromise
}

// Kick off the fetch eagerly so the first tap is instant.
loadManifest()

// Matches the keys in words.json, which are the gloss keys verbatim - so ":"
// ("cv:n") and the spaces of phrase glosses ("ei oo") have to survive.
function normalizeWord(word) {
  return word
    .toLowerCase()
    .replace(/[^\p{L}\p{N}'\-:\s]/gu, '')
    .replace(/\s+/g, ' ')
    .trim()
}

let currentAudio = null

function stopAll() {
  if (currentAudio) {
    currentAudio.pause()
    currentAudio = null
  }
}

// Emoji and pictographs must never reach the TTS engine - it reads them aloud.
function stripEmoji(text) {
  return text
    .replace(/[\p{Extended_Pictographic}\u{FE0F}\u{200D}]/gu, '')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

function playUrl(url, rate) {
  stopAll()
  const audio = new Audio(url)
  audio.playbackRate = rate
  currentAudio = audio
  audio.onerror = () => {
    if (currentAudio === audio) currentAudio = null
  }
  audio.play().catch(() => {
    if (currentAudio === audio) currentAudio = null
  })
}

// Promise-based playback for sequential listening (resolves when the clip
// ends, errors, or is stopped externally via stopAll → 'pause').
function playUrlAsync(url, rate) {
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
    audio.onerror = done
    audio.play().catch(done)
  })
}

export function useFinnishAudio() {
  // Playback speed follows the profile setting (0.5x–2x, default 1x)
  // unless a caller passes an explicit rate.
  const { audioRate } = usePrefs()

  // Play a sentence's pre-generated MP3. No file → silence.
  function playSentence(text, audioUrl, rate = null) {
    if (audioUrl) playUrl(audioUrl, rate ?? audioRate())
  }

  // Like playSentence, but resolves when playback finishes - for playing
  // a whole lesson back to back.
  function playSentenceAsync(text, audioUrl, rate = null) {
    if (audioUrl) return playUrlAsync(audioUrl, rate ?? audioRate())
    return Promise.resolve()
  }

  // Dynamic text (chat replies): synthesized server-side with the same male
  // neural voice as lesson audio - or the female voice when a female scenario
  // persona is speaking - cached by content. If the server can't run edge-tts
  // (e.g. shared hosting), the reply simply stays silent.
  const spokenCache = new Map() // "voice:clean text" → url | null
  let serverTtsDown = false

  async function fetchSpokenUrl(clean, voice) {
    const key = `${voice}:${clean}`
    if (!spokenCache.has(key) && !serverTtsDown) {
      try {
        const { data } = await api.post('/tts', { text: clean, voice })
        spokenCache.set(key, data.url ?? null)
      } catch (e) {
        // 503 = no TTS engine on this server; stop asking. Any other failure
        // (timeout, hiccup) stays uncached so the next tap simply retries.
        if (e.response?.status === 503) {
          serverTtsDown = true
          spokenCache.set(key, null)
        }
      }
    }
    return spokenCache.get(key) ?? null
  }

  async function playSpoken(text, rate = null, voice = 'male') {
    const clean = stripEmoji(text)
    if (!clean) return

    // Prime the audio element NOW, inside the tap's user activation:
    // synthesizing an uncached sentence outlives the activation window on
    // mobile browsers, and an Audio created after the wait gets its play()
    // rejected - which felt like the button needing a second tap. An element
    // load()ed during the gesture keeps its right to play later.
    stopAll()
    const audio = new Audio()
    currentAudio = audio
    audio.load()

    const url = await fetchSpokenUrl(clean, voice)
    if (currentAudio !== audio) return // a newer tap took over meanwhile
    if (!url) {
      currentAudio = null
      return
    }

    audio.src = url
    audio.playbackRate = rate ?? audioRate()
    audio.onerror = () => {
      if (currentAudio === audio) currentAudio = null
    }
    audio.play().catch(() => {
      if (currentAudio === audio) currentAudio = null
    })
  }

  // Play a single word's MP3 from the manifest. Not in it → silence.
  async function playWord(word, rate = null) {
    const key = normalizeWord(word)
    const manifest = await loadManifest()
    const url = manifest[key]
    if (url) playUrl(url, rate ?? audioRate())
  }

  // Play any audio file by URL (recording-studio takes, admin review) -
  // routed through the same player so clips never overlap. Raw takes play
  // at 1x: the reviewer needs to hear exactly what was recorded.
  function playClip(url) {
    if (url) playUrl(url, 1)
  }

  // Like playClip, but resolves when the clip ends - for auditing a whole
  // tier of recordings back to back without clicking play on every row.
  function playClipAsync(url) {
    if (url) return playUrlAsync(url, 1)
    return Promise.resolve()
  }

  return { playSentence, playSentenceAsync, playSpoken, playWord, playClip, playClipAsync, stop: stopAll }
}
