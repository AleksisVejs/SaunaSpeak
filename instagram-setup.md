# Instagram — profile setup pack

Companion to [marketing-plan.md](marketing-plan.md) and [marketing-assets.md](marketing-assets.md).
The video scripts live there; this file covers only what's Instagram-specific: the account
itself, the bio, and how the grid differs from the TikTok mirror.

> **Handle availability is unverified.** Instagram blocks logged-out lookups, so the options
> below are ranked by preference, not by what's actually free. Check them in the app in order
> and take the first available.

---

## 1. Handle

| Rank | Handle | Notes |
|---|---|---|
| 1 | `saunaspeak` | Exact brand match; matches the domain and every other channel |
| 2 | `saunaspeak.app` | Dots read as intentional on IG; still exact-match in search |
| 3 | `saunaspeak.fi` | Only if you'd ever want the .fi domain too — otherwise skip, it implies a domain you don't own |
| 4 | `getsaunaspeak` | Common SaaS fallback, but weakest for search |

Avoid underscores and numbers — they cost you in search and look squatted.

**Claim the same handle on TikTok, YouTube and Threads at the same time**, even if you don't post
there yet. Phase 3 posts the same clip to all three; mismatched handles break the cross-promotion.

## 2. Account type

Set up as a **Business account** (not Creator):
- Business exposes the website link field and contact button (Creator gates some of it)
- Required for Meta Ads later — §6 of the marketing plan budgets €5/day on IG/FB, and that needs
  a Business account linked to a Meta Business Suite asset
- Insights are the same either way

Category: **Education** → *Educational Research* or *Language School*.

## 3. Bio

150 characters, and Instagram does **not** render line breaks typed in the mobile app reliably —
paste the bio with real newlines from a desktop browser.

**Primary:**

```
Learn the Finnish Finns actually speak 🔥
"Mä oon" — not "minä olen"
5 spoken minutes a day · free forever
```

*(103 chars — leaves room for the link line IG appends.)*

**Alternates, if you want to test:**

- Pain-point lead, matching the Reddit post's voice:
  ```
  2 years of Finnish and you still can't follow a bus stop conversation?
  Puhekieli first. 5 min/day. Free.
  ```
- Shortest, punchiest:
  ```
  The Finnish your textbook skipped 🔥
  Puhekieli first · free forever
  ```

**Name field** (the bold line — this one *is* searchable, unlike the bio):
`SaunaSpeak · Spoken Finnish`

That's the single highest-leverage field on the profile. Instagram search matches handle + name
only, so "spoken Finnish" and "Finnish" both need to be in there.

## 4. Link in bio

Start with the plain `saunaspeak.com` — one link, no tool needed. Add a link-in-bio page only
once you're driving traffic to more than one destination.

Two adjustments worth making first:

- **Point at `/try`, not `/`.** The funnel note in memory says the register page is fine and the
  cliff is day 2 — but cold social traffic converts better into a no-signup demo. `/try` is
  already built for exactly this and is the landing page §6 specifies for paid.
- **Tag the link** so Umami can separate IG traffic from the Reddit launch spike:
  `https://saunaspeak.com/try?utm_source=instagram&utm_medium=bio`

Per-clip links in Stories should use `utm_medium=story` so you can tell which format actually
moves people.

## 5. Profile picture

The existing `pwa-512x512.png` works and is already the JSON-LD logo — brand-consistent for free.

One caveat: IG crops to a circle at ~110px display. Check the mark still reads at that size; if
the logo has a square frame or edge text, crop a centered variant rather than shipping a squished
one. Väinö's face is the stronger option if the logo is wordmark-heavy — a face outperforms a
wordmark in a comment feed, and Väinö is already the brand anchor per §2 of the assets doc.

## 6. What's different from TikTok

The plan currently treats IG as a mirror ("same clip to all three"). That's right for Reels and
worth keeping — but two IG-only formats are free upside and don't exist on TikTok:

**Carousels.** IG's highest-reach format for educational content, and it costs you nothing new:
every 15s script in `marketing-assets.md` is already a carousel. The pronoun diet (#5) is
literally six slides. The maybe ladder (#11) is five. Build them in Canva from the same text,
post on the days you don't have a Reel ready. Saves and shares — which carousels earn far more
than video — feed the algorithm harder than views do.

**Stories.** Where the build-in-public thread from §5 belongs, rather than the grid: milestone
posts, "shipped what you asked for", polls ("which situation should I build next?"). The poll
sticker is the same question as the Reddit launch post's Q1 — reuse it.

Repost the removed watermark version only. TikTok exports carry a TikTok watermark and IG
suppresses reach on them — export clean from CapCut once and upload the same file to each
platform separately.

## 7. First two weeks

Bank clips before you open the account — the plan already says 10 before starting, which is the
right call. Post 3×/week to match Phase 3, alternating Reel / carousel / Reel.

| # | Format | Source |
|---|---|---|
| 1 | Reel | Script 1, "The classic" — the flagship contrast, and the proof asset from §1 |
| 2 | Carousel | Script 5, "The pronoun diet" — six slides, highest save-rate candidate |
| 3 | Reel | Script 15, "One-word questions" |
| 4 | Carousel | Script 11, "The maybe ladder" |
| 5 | Reel | Script 16, "Mökki mode" — **time-sensitive, post while July holds** |
| 6 | Reel | Script 10, "The strawberry test" — also summer-timed |

Scripts 16 and 10 both expire with the season; if the account is live in July, front-load them
ahead of this order.

**Hashtags:** 5–8, mixed by size. Big (`#learnfinnish` `#finnishlanguage` `#languagelearning`),
mid (`#puhekieli` `#suomenkieli` `#expatsinfinland`), and one branded (`#saunaspeak`) that you
own from day one. Skip the 30-tag dump — it reads as spam and IG's own guidance now says 3–5.

## 8. Not doing yet

- **Link-in-bio tool** — one destination, no need
- **Paid** — §6 is explicit that this waits until a clip proves a hook organically
- **Cross-posting automation** — manual upload avoids the watermark penalty; automate only if
  volume makes it painful
