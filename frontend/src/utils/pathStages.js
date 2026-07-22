// CEFR-like codes still organize the curriculum internally, but learners see
// plain-language stages. The codes describe content difficulty; they are not a
// claim that finishing a small app section awards that proficiency level.
export const PATH_STAGES = Object.freeze([
  { id: 'A0', slug: 'first-words', name: 'First words' },
  { id: 'A1', slug: 'everyday-life', name: 'Everyday life' },
  { id: 'A2', slug: 'stories-and-opinions', name: 'Stories & opinions' },
  { id: 'B1', slug: 'real-world-finnish', name: 'Real-world Finnish' },
  { id: 'B2', slug: 'nuance-and-expression', name: 'Nuance & expression' },
  { id: 'C1', slug: 'sounding-local', name: 'Sounding local' }
])

const STAGE_NAMES = new Map(PATH_STAGES.map((stage) => [stage.id, stage.name]))
const STAGE_SLUGS = new Map(PATH_STAGES.map((stage) => [stage.id, stage.slug]))
const SLUG_LEVELS = new Map(PATH_STAGES.map((stage) => [stage.slug, stage.id]))

export function pathStageName(internalLevel) {
  return STAGE_NAMES.get(internalLevel) ?? 'Next stage'
}

export function pathStageSlug(internalLevel) {
  return STAGE_SLUGS.get(internalLevel) ?? 'next-stage'
}

// Friendly slugs are the public route. Old code-based bookmarks still open.
export function pathStageLevel(routeValue) {
  const value = String(routeValue ?? '').toLowerCase()
  return SLUG_LEVELS.get(value) ?? String(routeValue ?? 'A0').toUpperCase()
}
