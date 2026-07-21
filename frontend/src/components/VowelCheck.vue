<script setup>
// Say-it-yourself check for one front/back contrast.
//
// Deliberately placed AFTER the identification drill and behind a score gate.
// Kuulo's whole design rests on discrimination coming before production
// (Logan, Lively & Pisoni 1991) - asking a learner to produce a contrast they
// cannot yet hear fossilises the wrong sound, which is worse than not asking.
// So this appears only once the ear has demonstrably arrived.
//
// What it measures is narrow and stated plainly to the learner: whether their
// two vowels came out as two different vowels. It does not score their accent,
// their consonants, or their intonation, and none of the copy implies it does.
import { computed, ref } from 'vue'
import { Check, Mic, Play, RotateCcw, Square, TriangleAlert, Volume2 } from 'lucide-vue-next'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { useVoiceRecorder } from '../composables/useVoiceRecorder'
import { useVowelCheck } from '../composables/useVowelCheck'

const props = defineProps({
  set: { type: Object, required: true },
})

const { playClip } = useFinnishAudio()
const { supported, recording, start, stopAndGetBlob } = useVoiceRecorder()
const check = useVowelCheck(props.set)

// One pair carries the whole check - the first with audio on both sides, so
// the learner can always hear the model before attempting it.
const pair = computed(
  () => props.set.pairs.find((p) => p.a_audio && p.b_audio) ?? props.set.pairs[0] ?? null
)

// Sets list their contrast front-vowel-first, but the pair's a/b sides follow
// the file's word order, so map explicitly rather than assuming they line up.
const sides = computed(() => {
  if (!pair.value || !check.ordered.value) return null
  const frontIsA = pair.value.a.includes(check.ordered.value.front)
  return frontIsA
    ? { front: { word: pair.value.a, audio: pair.value.a_audio }, back: { word: pair.value.b, audio: pair.value.b_audio } }
    : { front: { word: pair.value.b, audio: pair.value.b_audio }, back: { word: pair.value.a, audio: pair.value.a_audio } }
})

const activeSide = ref(null) // which word we're currently recording

async function toggleRecord(side) {
  if (recording.value) {
    const blob = await stopAndGetBlob()
    activeSide.value = null
    if (blob) await check.addTake(side, blob)
    return
  }
  activeSide.value = side
  start()
}

function runJudge() {
  if (!sides.value) return
  check.judge(sides.value.front.word, sides.value.back.word)
}
</script>

<template>
  <section v-if="check.applicable.value && sides && supported" class="vcheck card">
    <h3><Mic class="vc-ico" aria-hidden="true" /> Now say them</h3>
    <p class="muted intro">
      Your ear has the contrast - so it's worth checking your mouth does too.
      Record both words. We measure one thing: whether they come out as two
      different vowels, or the same one twice.
    </p>

    <div class="rows">
      <div v-for="side in ['front', 'back']" :key="side" class="row">
        <div class="word-col">
          <span class="word">{{ sides[side].word }}</span>
          <button v-if="sides[side].audio" class="listen" @click="playClip(sides[side].audio)">
            <Volume2 class="sm-ico" aria-hidden="true" /> Native
          </button>
        </div>

        <div class="act-col">
          <button
            class="rec"
            :class="{ on: recording && activeSide === side }"
            :disabled="check.analysing.value || (recording && activeSide !== side)"
            @click="toggleRecord(side)"
          >
            <template v-if="recording && activeSide === side">
              <Square class="sm-ico" aria-hidden="true" /> Stop
            </template>
            <template v-else>
              <Mic class="sm-ico" aria-hidden="true" /> Record
            </template>
          </button>

          <template v-if="check.takes.value[side]">
            <button class="listen" @click="playClip(check.takes.value[side].url)">
              <Play class="sm-ico" aria-hidden="true" /> You
            </button>
            <Check class="done-mark" aria-hidden="true" />
          </template>
        </div>
      </div>
    </div>

    <p v-if="check.error.value" class="err">
      <TriangleAlert class="sm-ico" aria-hidden="true" /> {{ check.error.value }}
    </p>

    <button
      v-if="check.ready.value && !check.result.value"
      class="btn btn-primary"
      :disabled="check.analysing.value"
      @click="runJudge"
    >
      Compare them
    </button>

    <div v-if="check.result.value" class="result" :class="check.result.value.tone">
      <p class="headline">{{ check.result.value.headline }}</p>
      <p class="detail">{{ check.result.value.detail }}</p>
      <button class="btn" @click="check.reset()"><RotateCcw class="sm-ico" aria-hidden="true" /> Try again</button>
    </div>

    <p class="muted foot">
      This checks tongue position on one vowel - not your accent overall.
    </p>
  </section>
</template>

<style scoped>
.vcheck { padding: 20px; text-align: left; display: flex; flex-direction: column; gap: 14px; }
.vcheck h3 { display: flex; align-items: center; gap: 8px; font-size: 16px; }
.vc-ico { width: 17px; height: 17px; color: var(--accent); }
.intro { font-size: 13.5px; line-height: 1.6; }

.rows { display: flex; flex-direction: column; gap: 10px; }
.row {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--card);
}
.word-col { display: flex; align-items: center; gap: 10px; }
.word { font-size: 19px; font-weight: 800; }
.act-col { display: flex; align-items: center; gap: 8px; }

.rec, .listen {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 13px; border-radius: var(--radius-pill);
  border: 1px solid var(--border); background: var(--bg);
  font-size: 13px; font-weight: 700; color: var(--text); cursor: pointer;
}
.rec.on { border-color: var(--danger, #d2544b); color: var(--danger, #d2544b); }
.rec:disabled { opacity: 0.45; cursor: default; }
.sm-ico { width: 14px; height: 14px; }
.done-mark { width: 16px; height: 16px; color: var(--accent); }

.err { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--danger, #d2544b); }

.result { padding: 14px; border-radius: 12px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.result.good { border-color: var(--accent); background: var(--accent-soft); }
.result.warn { border-color: var(--warn, #d9a441); }
.result.bad { border-color: var(--danger, #d2544b); }
.headline { font-size: 14.5px; font-weight: 700; }
.detail { font-size: 13.5px; line-height: 1.6; color: var(--text-dim); }

.foot { font-size: 12px; }
</style>
