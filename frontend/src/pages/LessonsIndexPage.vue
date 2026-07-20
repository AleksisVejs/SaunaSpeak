<script setup>
// Public catalog of every lesson - the front door of the SEO content hub.
// No auth: this is the same curriculum a free account learns, readable by
// anyone (and by crawlers) with audio on every sentence.
import { onMounted, ref, computed } from 'vue'
import api from '../api'

const lessons = ref([])
const loading = ref(true)
const error = ref(false)

const LEVEL_BLURBS = {
  A0: 'Your first spoken words - greetings, ordering coffee, surviving day one.',
  A1: 'Everyday life out loud: shops, weather, plans, small talk on the bench.',
  A2: 'Opinions, stories and the past tense - the way Finns actually tell them.',
  B1: 'Real-world Finnish: renting a place, hedging, "no niin" and when to be formal.',
  B2: 'The native layer: regrets, hearsay particles, idioms and "mähän sanoin".',
  C1: 'Sounding local: slang, sarcasm and the idioms textbooks skip.'
}

const byLevel = computed(() => {
  const groups = []
  for (const lesson of lessons.value) {
    let group = groups.find((g) => g.level === lesson.level)
    if (!group) {
      group = { level: lesson.level, blurb: LEVEL_BLURBS[lesson.level] ?? '', lessons: [] }
      groups.push(group)
    }
    group.lessons.push(lesson)
  }
  return groups
})

onMounted(async () => {
  try {
    const { data } = await api.get('/public/lessons')
    lessons.value = data.lessons
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="lessons-index">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
      <router-link to="/login" class="home-link">Log in</router-link>
    </div>

    <div class="hero">
      <h1>Spoken Finnish lessons, <em>free to read</em></h1>
      <p class="muted lede">
        Every lesson in the SaunaSpeak path - yleispuhekieli, the spoken
        Finnish understood everywhere, with the written form (kirjakieli)
        next to it, word-by-word explanations and audio.
        Browse freely; a free account adds daily sessions and spaced repetition.
      </p>
    </div>

    <div v-if="loading" class="muted state">Loading the lesson path…</div>
    <div v-else-if="error" class="muted state">Couldn't load the lessons right now - try a refresh.</div>

    <template v-else>
      <section v-for="group in byLevel" :key="group.level" class="level-block">
        <h2><span class="level-chip">{{ group.level }}</span>{{ group.blurb }}</h2>
        <div class="grid">
          <router-link
            v-for="lesson in group.lessons"
            :key="lesson.slug"
            :to="`/lessons/${lesson.slug}`"
            class="card lesson-card"
          >
            <p class="lesson-title">{{ lesson.title }}</p>
            <p class="teaser fi">“{{ lesson.teaser }}”</p>
            <p class="muted count">{{ lesson.sentence_count }} sentences · audio included</p>
          </router-link>
        </div>
      </section>

      <section class="cta card">
        <h2>Want these to stick?</h2>
        <p class="muted">
          Reading is a start - remembering takes retrieval. A free account turns
          this exact path into daily five-minute sessions with spaced repetition.
        </p>
        <div class="cta-row">
          <router-link to="/register" class="btn btn-primary">Create a free account</router-link>
          <router-link to="/try" class="btn btn-ghost">Try 6 sentences first</router-link>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.lessons-index { max-width: 860px; margin: 0 auto; padding: 16px 4px 60px; }
.page-top { display: flex; justify-content: space-between; margin-bottom: 20px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }

.hero { text-align: center; margin-bottom: 34px; }
.hero h1 { font-size: clamp(26px, 5vw, 34px); line-height: 1.2; }
.hero h1 em { color: var(--accent); font-style: normal; }
.lede { max-width: 520px; margin: 10px auto 0; font-size: 15.5px; line-height: 1.55; }

.state { text-align: center; padding: 40px 0; }

.level-block { margin-bottom: 34px; }
.level-block h2 {
  font-size: 15px; font-weight: 600; color: var(--text-dim);
  display: flex; align-items: center; gap: 10px; margin-bottom: 14px; line-height: 1.4;
}
.level-chip {
  flex: 0 0 auto; background: var(--accent-soft); color: var(--accent);
  font-weight: 800; font-size: 13px; border-radius: var(--radius-pill, 999px);
  padding: 3px 12px;
}
.grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 560px) { .grid { grid-template-columns: 1fr 1fr; } }

.lesson-card { display: block; color: var(--text); transition: border-color 0.15s; }
.lesson-card:hover { border-color: var(--accent); }
.lesson-title { font-weight: 700; margin-bottom: 6px; }
.teaser { color: var(--accent); font-size: 15px; margin-bottom: 8px; }
.count { font-size: 12.5px; }

.cta { text-align: center; margin-top: 10px; }
.cta h2 { font-size: 20px; margin-bottom: 8px; }
.cta p { max-width: 480px; margin: 0 auto 16px; }
.cta-row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
</style>
