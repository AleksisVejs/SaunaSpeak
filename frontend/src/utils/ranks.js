// Sauna ranks - löyly levels earned with XP. Shared by the dashboard rank
// card and the session-end celebration.
// The curve targets ~3-4 months of consistent play to the top (reviews pay
// 10 XP forever, ~130-170 XP on a normal day) - a ladder finished in two
// weeks stops motivating exactly when retention gets hard.
export const RANKS = [
  { xp: 0, title: 'Kylmä Kiuas', icon: '🪨' },
  { xp: 500, title: 'Ensilöyly', icon: '💧' },
  { xp: 1500, title: 'Löylynheittäjä', icon: '♨️' },
  { xp: 4000, title: 'Lauteiden Vakio', icon: '🧖' },
  { xp: 8000, title: 'Löylymestari', icon: '🔥' },
  { xp: 15000, title: 'Saunalegenda', icon: '👑' }
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
