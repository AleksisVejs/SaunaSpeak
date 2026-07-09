<script setup>
import { ref } from 'vue'

const emit = defineEmits(['done'])

const slide = ref(0)

const slides = [
  {
    icon: '🗣️',
    title: 'Real spoken Finnish',
    text: 'You\'ll learn what Finns actually say — "Mä oon", not "Minä olen". Where the book form differs, it\'s shown as a 📖 reference so you can still read signs and news.'
  },
  {
    icon: '👂',
    title: 'Listen → speak → recall',
    text: 'New sentences: listen and say them out loud. Tap any word to hear it and see what it means. As you progress, sentences come back harder: fill the gap 🧩, write what you hear ✍️, and finally recall the Finnish from memory 🧠 — check yourself with the 🎤 or by typing.'
  },
  {
    icon: '🔥',
    title: 'A little löyly every day',
    text: 'Short daily sessions beat cramming — that\'s how memory works. Keep your streak alive, earn XP and climb the sauna ranks from Kylmä Kiuas to Saunalegenda.'
  }
]

function next() {
  if (slide.value < slides.length - 1) slide.value++
  else emit('done')
}
</script>

<template>
  <div class="overlay">
    <div class="card modal">
      <transition name="fade" mode="out-in">
        <div :key="slide" class="slide">
          <div class="slide-icon">{{ slides[slide].icon }}</div>
          <h2>{{ slides[slide].title }}</h2>
          <p class="slide-text">{{ slides[slide].text }}</p>
        </div>
      </transition>

      <div class="dots">
        <span v-for="(s, i) in slides" :key="i" class="dot" :class="{ active: i === slide }"></span>
      </div>

      <button class="btn btn-primary btn-block" @click="next">
        {{ slide < slides.length - 1 ? 'Next' : 'Let\'s go! 🧖' }}
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
  max-width: 380px;
  width: 100%;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 30px 26px 24px;
}
.slide { display: flex; flex-direction: column; gap: 10px; min-height: 170px; }
.slide-icon { font-size: 48px; }
.slide h2 { font-size: 21px; }
.slide-text { color: var(--text-dim); font-size: 15px; line-height: 1.55; }
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
