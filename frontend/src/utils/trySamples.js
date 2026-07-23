// The audio files are the committed course MP3s (sentence ids from the seed
// order), so the demo voice is exactly the voice inside the app. On mount we
// ask the backend for each sentence's CURRENT audio - once a human recording
// is approved, the demo plays the human voice too (see tryAudio upgrade below).
//
// Five cards, not seven: attention decays fast, and the demo's job is momentum,
// not coverage. EVERY card is an ear test - audio first, commit to what you
// heard, then the reveal. It used to be card 3 only, with the other four
// showing the sentence up front and asking for a "Show meaning" tap that
// revealed something never hidden: two taps per card with nothing at stake and
// no way to be right.
//
// The decoy is another SPOKEN sentence, not the written form, everywhere except
// card 1. Pitting puhekieli against kirjakieli makes the right answer reliably
// the shorter string - reduction IS shortening - so "tap the shorter one" scored
// 5/5 with the sound off. Decoys now match the real sentence in word count and
// rhythm and differ in the part that carries the meaning, so the only way
// through is to actually hear it. The written form still appears in the reveal,
// where it teaches instead of giving the answer away.
//
// Card 1 keeps the written contrast on purpose: it states the thesis and hands
// out a win before asking anyone to work. Only the real sentence has audio -
// decoys are text, so no new clips are needed.
//
// `options` order is authored, not shuffled, so the answer isn't always in the
// same slot and a run stays reproducible.
export const TRY_SAMPLES = [
  {
    fi: 'Moi! Mä oon Anna.',
    book: 'Hei! Minä olen Anna.',
    en: "Hi! I'm Anna.",
    note: '“Mä” is spoken Finnish for “minä” (I).',
    audio: '/audio/try-1.mp3',
    options: ['Hei! Minä olen Anna.', 'Moi! Mä oon Anna.']
  },
  {
    fi: 'Onks sul nälkä?',
    book: 'Onko sinulla nälkä?',
    en: 'Are you hungry?',
    note: '“Onks” = “onko” (is), “sul” = “sinulla” (you have).',
    audio: '/audio/try-2.mp3',
    // Same three words, same rhythm - only the last one differs.
    options: ['Onks sul nälkä?', 'Onks sul kylmä?']
  },
  {
    fi: 'Emmä tiiä.',
    book: 'En minä tiedä.',
    en: "I don't know.",
    note: 'Three textbook words melt into two spoken ones - you\'ll hear this daily.',
    audio: '/audio/sentence-emma-tiia-1838ce.mp3',
    // Both start "Emmä"; the verb is the whole test.
    options: ['Emmä tuu.', 'Emmä tiiä.']
  },
  {
    fi: 'Moikka, nähään!',
    book: 'Hei hei, nähdään!',
    en: 'Bye, see you!',
    note: '“Nähään” = “nähdään” - literally “we\'ll be seen”.',
    audio: '/audio/sentence-moikka-nahaan-a128dd.mp3',
    // nähään / mennään - same shape, same ending, different verb.
    options: ['Moikka, nähään!', 'Moikka, mennään!']
  },
  // A taste from further down the path - slang the textbooks never touch.
  {
    fi: 'Nyt meni kyl överiks.',
    book: 'Nyt se meni kyllä liian pitkälle.',
    en: 'Now that went too far.',
    note: 'From further down the same course - “överiks” is Helsinki slang, borrowed from Swedish “över”. The path keeps going into Finnish the textbooks never touch.',
    audio: '/audio/sentence-nyt-meni-kyl-overiks-6a792b.mp3',
    // Opposite meaning, identical scaffolding - the slang word is the test.
    options: ['Nyt meni kyl hyvin.', 'Nyt meni kyl överiks.']
  }
]
