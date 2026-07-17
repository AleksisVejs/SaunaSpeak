// Sauna ranks - löyly levels earned with XP. Shared by the dashboard rank
// card and the session-end celebration.
// The curve targets ~3-4 months of consistent play to the top (reviews pay
// 10 XP forever, ~130-170 XP on a normal day) - a ladder finished in two
// weeks stops motivating exactly when retention gets hard.
// Icons are components (Lucide + the LoylyIcon brand glyph) - render with
// <component :is="rank.icon" />.
import { Armchair, Crown, Droplet, Flame, Snowflake } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'

export const RANKS = [
  { xp: 0, title: 'Kylmä Kiuas', icon: Snowflake },
  { xp: 500, title: 'Ensilöyly', icon: Droplet },
  { xp: 1500, title: 'Löylynheittäjä', icon: LoylyIcon },
  { xp: 4000, title: 'Lauteiden Vakio', icon: Armchair },
  { xp: 8000, title: 'Löylymestari', icon: Flame },
  { xp: 15000, title: 'Saunalegenda', icon: Crown }
]

export function rankFor(xp) {
  let idx = 0
  RANKS.forEach((r, i) => {
    if (xp >= r.xp) idx = i
  })
  const current = RANKS[idx]
  const next = RANKS[idx + 1] ?? null
  const pct = next ? Math.round(((xp - current.xp) / (next.xp - current.xp)) * 100) : 100
  return { ...current, index: idx, next, pct }
}
