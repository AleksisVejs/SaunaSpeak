<script setup>
// First-run intro. One idea per slide, and every claim carries its
// plain-language "why this works" - the method IS the product.
import { ref } from 'vue'

const emit = defineEmits(['done'])

const slide = ref(0)

const slides = [
  {
    image: '/vaino-wave.png',
    alt: 'Väinö waving hello',
    title: 'Learn the Finnish Finns speak',
    text: 'You\'ll learn "Mä oon" - what people say in shops, buses and saunas - not just textbook "Minä olen". The book form stays visible as a 📖 reference.',
    why: 'Learners who study only written Finnish famously can\'t follow real conversations. Starting with speech fixes that from day one.'
  },
  {
    image: '/vaino-think.png',
    alt: 'Väinö thinking, stroking his beard',
    title: 'Guess before you see',
    text: 'New sentences show the English first. Take a wild guess out loud, then see and hear the Finnish.',
    why: 'Trying to retrieve - even guessing wrong - primes your brain to remember the answer far better than just reading it.'
  },
  {
    image: '/vaino-mic.png',
    alt: 'Väinö singing into a microphone',
    title: 'Speak, record, compare',
    text: 'Say every sentence out loud. Record yourself and play your take next to the native audio.',
    why: 'This is shadowing - proven to improve pronunciation, rhythm and fluency, because you hear exactly where you differ.'
  },
  {
    image: '/vaino-loyly.png',
    alt: 'Väinö throwing water on the sauna stones',
    title: 'A little löyly every day',
    text: 'Short daily sessions bring each sentence back right before you\'d forget it - as a gap-fill, then dictation, then pure recall.',
    why: 'Spaced repetition is the most replicated result in memory research. Minutes a day beat hours of cramming.'
  }
]

function next() {
  if (slide.value < slides.length - 1) slide.value++
  else emit('done')
}
</script>

<template>
  <div class="overlay" role="dialog" aria-modal="true" aria-label="How SaunaSpeak works">
    <div class="card modal">
      <transition name="fade" mode="out-in">
        <div :key="slide" class="slide">
          <img v-if="slides[slide].image" class="slide-vaino" :src="slides[slide].image" :alt="slides[slide].alt" />
          <div v-else class="slide-icon"><span>{{ slides[slide].icon }}</span></div>
          <h2>{{ slides[slide].title }}</h2>
          <p class="slide-text">{{ slides[slide].text }}</p>
          <p class="slide-why">
            <span class="why-tag">Why it works</span>
            {{ slides[slide].why }}
          </p>
        </div>
      </transition>

      <div class="dots" aria-hidden="true">
        <span v-for="(s, i) in slides" :key="i" class="dot" :class="{ active: i === slide }"></span>
      </div>

      <button class="btn btn-primary btn-block" @click="next">
        {{ slide < slides.length - 1 ? 'Next' : 'Start learning 🧖' }}
      </button>
      <button v-if="slide < slides.length - 1" class="skip" @click="emit('done')">Skip intro</button>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 12, 16, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 50;
}
.modal {
  max-width: 400px;
  width: 100%;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 30px 26px 24px;
}
.slide { display: flex; flex-direction: column; gap: 12px; min-height: 250px; }
.slide-icon {
  width: 76px;
  height: 76px;
  margin: 0 auto;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--accent-soft);
  font-size: 40px;
}
.slide-vaino { width: 104px; height: 104px; margin: 0 auto; }
.slide h2 { font-size: 21px; }
.slide-text { color: var(--text); font-size: 15px; line-height: 1.55; }
.slide-why {
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
  text-align: left;
  margin-top: auto;
}
.why-tag {
  display: block;
  font-size: var(--text-xs, 11px);
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 3px;
}
.dots { display: flex; justify-content: center; gap: 8px; }
.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--border);
  transition: background 0.2s ease;
}
.dot.active { background: var(--accent); }
.skip {
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
}
.skip:hover { color: var(--text); }
</style>
