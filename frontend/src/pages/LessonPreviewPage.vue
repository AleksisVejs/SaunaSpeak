<script setup>
// Public read-only view of one lesson: every sentence with its audio,
// written-Finnish counterpart and word-by-word glosses. Each page targets
// the long-tail searches only this content can answer ("mä oon meaning",
// "onks meaning Finnish") and funnels into /try and /register.
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Mic, Volume2 } from 'lucide-vue-next'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { setPageHead } from '../composables/usePageHead'

const route = useRoute()
const { playSentence } = useFinnishAudio()

const lesson = ref(null)
const previous = ref(null)
const next = ref(null)
const loading = ref(true)
const missing = ref(false)

// Pattern examples come in two seed shapes: plain strings and
// { fi, en, note } objects. Normalize for the template.
function exampleText(ex) {
  if (typeof ex === 'string') return ex
  return [ex.fi, ex.en].filter(Boolean).join(' = ') + (ex.note ? ` (${ex.note})` : '')
}

async function load(slug) {
  loading.value = true
  missing.value = false
  try {
    const { data } = await api.get(`/public/lessons/${slug}`)
    lesson.value = data.lesson
    previous.value = data.previous
    next.value = data.next

    const first = data.lesson.sentences[0]
    setPageHead({
      title: `${data.lesson.title} - ${data.lesson.level} spoken Finnish lesson - SaunaSpeak`,
      description: `Learn "${first?.finnish_text}" and ${data.lesson.sentences.length - 1} more real spoken-Finnish (puhekieli) sentences with audio, written Finnish and word-by-word explanations.`
    })
    setJsonLd(data.lesson, slug)
  } catch {
    missing.value = true
  } finally {
    loading.value = false
  }
}

// Structured data for the content hub: Article + BreadcrumbList per lesson.
// Injected per slug and removed on leave so it never leaks onto app pages.
let ld = null
function setJsonLd(l, slug) {
  ld?.remove()
  ld = document.createElement('script')
  ld.type = 'application/ld+json'
  ld.textContent = JSON.stringify({
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Article',
        headline: `${l.title} - ${l.level} spoken Finnish lesson`,
        inLanguage: 'en',
        about: 'Spoken Finnish (puhekieli)',
        author: { '@type': 'Organization', name: 'SaunaSpeak', url: 'https://saunaspeak.com' },
        publisher: { '@type': 'Organization', name: 'SaunaSpeak', url: 'https://saunaspeak.com' }
      },
      {
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Lessons', item: 'https://saunaspeak.com/lessons' },
          { '@type': 'ListItem', position: 2, name: l.title, item: `https://saunaspeak.com/lessons/${slug}` }
        ]
      }
    ]
  })
  document.head.appendChild(ld)
}
onBeforeUnmount(() => ld?.remove())

onMounted(() => load(route.params.slug))
// Prev/next links land on this same component - refetch on slug change.
watch(() => route.params.slug, (slug) => { if (slug && route.name === 'lesson-preview') load(slug) })
</script>

<template>
  <div class="lesson-preview">
    <div class="page-top">
      <router-link to="/lessons" class="home-link">‹ All lessons</router-link>
      <router-link to="/login" class="home-link">Log in</router-link>
    </div>

    <div v-if="loading" class="muted state">Warming up the sauna…</div>

    <div v-else-if="missing" class="state">
      <p class="muted">That lesson doesn't exist (any more).</p>
      <router-link to="/lessons" class="btn btn-ghost">Browse all lessons</router-link>
    </div>

    <template v-else>
      <header class="head">
        <p class="level-chip">{{ lesson.level }}</p>
        <h1>{{ lesson.title }}</h1>
        <p class="muted lede">
          {{ lesson.sentences.length }} sentences of real spoken Finnish (puhekieli),
          each with the written form and what every word means. Tap the speaker to hear it.
        </p>
      </header>

      <aside v-if="lesson.pattern" class="card pattern">
        <p class="pattern-kicker">Pattern · {{ lesson.pattern.title }}</p>
        <p class="pattern-summary">{{ lesson.pattern.summary }}</p>
        <ul v-if="lesson.pattern.examples?.length">
          <li v-for="(ex, i) in lesson.pattern.examples" :key="i" class="fi-example">{{ exampleText(ex) }}</li>
        </ul>
      </aside>

      <section class="sentences">
        <article v-for="s in lesson.sentences" :key="s.finnish_text" class="card sentence">
          <div class="fi-row">
            <button
              v-if="s.audio_url"
              type="button"
              class="play"
              :aria-label="`Play '${s.finnish_text}'`"
              @click="playSentence(s.finnish_text, s.audio_url)"
            ><Volume2 class="play-ico" aria-hidden="true" /></button>
            <h2 class="fi-text">{{ s.finnish_text }}</h2>
            <span
              v-if="s.audio_url?.startsWith('/audio/human/')"
              class="native-pill"
              title="Recorded by a native Finnish speaker"
            ><Mic class="pill-ico" aria-hidden="true" /> native</span>
          </div>
          <p class="english">{{ s.english_text }}</p>
          <p v-if="s.written_text && s.written_text !== s.finnish_text" class="written muted">
            <span class="lbl">written Finnish</span> {{ s.written_text }}
          </p>
          <dl v-if="s.word_glosses && Object.keys(s.word_glosses).length" class="glosses">
            <div v-for="(gloss, word) in s.word_glosses" :key="word" class="gloss">
              <dt>{{ word }}</dt>
              <dd>{{ gloss }}</dd>
            </div>
          </dl>
        </article>
      </section>

      <nav class="neighbors">
        <router-link v-if="previous" :to="`/lessons/${previous.slug}`" class="card neighbor">
          <span class="muted dir">‹ Previous · {{ previous.level }}</span>
          <span class="n-title">{{ previous.title }}</span>
        </router-link>
        <router-link v-if="next" :to="`/lessons/${next.slug}`" class="card neighbor next">
          <span class="muted dir">Next · {{ next.level }} ›</span>
          <span class="n-title">{{ next.title }}</span>
        </router-link>
      </nav>

      <section class="cta card">
        <h2>Don't just read it - keep it</h2>
        <p class="muted">
          A free SaunaSpeak account teaches this lesson in daily five-minute
          sessions and brings each sentence back right before you'd forget it.
        </p>
        <div class="cta-row">
          <router-link to="/register" class="btn btn-primary">Learn this lesson free</router-link>
          <router-link to="/try" class="btn btn-ghost">Try 6 sentences first</router-link>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.lesson-preview { max-width: 720px; margin: 0 auto; padding: 16px 4px 60px; }
.page-top { display: flex; justify-content: space-between; margin-bottom: 20px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.state { text-align: center; padding: 40px 0; display: flex; flex-direction: column; align-items: center; gap: 14px; }

.head { text-align: center; margin-bottom: 24px; }
.level-chip {
  display: inline-block; background: var(--accent-soft); color: var(--accent);
  font-weight: 800; font-size: 13px; border-radius: var(--radius-pill, 999px);
  padding: 3px 12px; margin-bottom: 10px;
}
.head h1 { font-size: clamp(24px, 5vw, 32px); line-height: 1.2; }
.lede { max-width: 460px; margin: 10px auto 0; font-size: 15px; line-height: 1.55; }

.pattern { margin-bottom: 22px; border-left: 3px solid var(--accent); }
.pattern-kicker { font-size: 12.5px; font-weight: 800; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
.pattern-summary { font-size: 14.5px; line-height: 1.55; }
.pattern ul { list-style: none; padding: 0; margin: 10px 0 0; display: flex; flex-direction: column; gap: 5px; }
.fi-example { font-size: 14px; color: var(--text-dim); }

.sentences { display: flex; flex-direction: column; gap: 12px; }
.fi-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.native-pill {
  font-size: 10.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 3px 9px;
  border-radius: 99px;
  background: var(--green-soft);
  color: var(--green);
  white-space: nowrap;
}
.play {
  flex: 0 0 auto; background: var(--accent-soft); border: none; cursor: pointer;
  color: var(--accent); border-radius: 50%; width: 38px; height: 38px;
  display: grid; place-items: center;
}
.play-ico { width: 16px; height: 16px; }
.pill-ico { width: 10px; height: 10px; vertical-align: -1px; }
.play:hover { outline: 2px solid var(--accent); }
.fi-text { font-size: 19px; line-height: 1.35; }
.english { margin-top: 6px; font-size: 14.5px; }
.written { margin-top: 4px; font-size: 13.5px; }
.written .lbl {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
  background: var(--accent-soft); color: var(--accent); border-radius: 4px; padding: 1px 6px; margin-right: 6px;
}
.glosses { margin-top: 12px; border-top: 1px solid var(--border); padding-top: 10px; display: flex; flex-direction: column; gap: 5px; }
.gloss { display: flex; gap: 8px; font-size: 13.5px; line-height: 1.5; }
.gloss dt { flex: 0 0 auto; font-weight: 700; color: var(--accent); }
.gloss dd { color: var(--text-dim); }

.neighbors { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 22px 0; }
.neighbor { display: flex; flex-direction: column; gap: 4px; color: var(--text); }
.neighbor:hover { border-color: var(--accent); }
.neighbor.next { text-align: right; }
.dir { font-size: 12.5px; }
.n-title { font-weight: 700; font-size: 14.5px; }

.cta { text-align: center; }
.cta h2 { font-size: 20px; margin-bottom: 8px; }
.cta p { max-width: 460px; margin: 0 auto 16px; }
.cta-row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

@media (max-width: 520px) { .neighbors { grid-template-columns: 1fr; } .neighbor.next { text-align: left; } }
</style>
