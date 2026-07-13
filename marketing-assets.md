# Marketing Assets — Reddit launch post + video scripts + tools

Companion to [marketing-plan.md](marketing-plan.md).

---

## 1. The r/LearnFinnish launch post

> **Check the sub rules / pinned posts before posting, and only post after ~a week
> of genuinely answering questions there.** Best timing: Tue–Thu, ~15:00–17:00
> Finnish time (catches EU evening + US morning).

**Title:**

> I got tired of apps teaching "minä olen" when every Finn says "mä oon", so I built a puhekieli-first app — honest feedback wanted

**Body:**

> Hei everyone! Full disclosure up front: I built this, so grain of salt and all.
>
> Like a lot of people here, I hit the classic wall: months of studying, and then a Finn opens their mouth and says "emmä tiiä, onks sul nälkä?" and none of it matches anything in the textbook. Every app I tried teaches kirjakieli first and treats spoken Finnish as an advanced topic — which always felt backwards to me, since spoken Finnish is what you actually *hear* from day one.
>
> So I built **SaunaSpeak** (saunaspeak.com): every sentence is puhekieli first ("mä oon", "onks", "-ks questions"), with the kirjakieli form one tap away as a reference. Short daily sessions, audio on everything, spaced repetition doing the scheduling. The whole learning path is free — no paywall on lessons, reviews, or audio. (There's an optional sub for the AI conversation stuff, but you can ignore it entirely.)
>
> A Finnish friend has been testing it and keeping my puhekieli honest, but he's one person from one region — which is exactly why I'm posting here.
>
> Two things I'd genuinely love opinions on:
>
> 1. For the roleplay practice I picked 8 everyday situations (grocery store, café, bus, pharmacy, restaurant, meeting a neighbor, market square, sauna evening). **What situation do you wish you'd been able to practice before it happened to you for real?**
> 2. If you try a lesson or two: does the spoken Finnish sound right to you, or did I get something wrong? I'd rather hear it bluntly here than have learners absorb a mistake.
>
> Kiitos! I'll be in the comments.

**Comment-thread prep (have answers ready for):**
- "Which dialect is this?" → capital-region spoken standard; understood everywhere; regional dialect tracks deliberately out of scope (TTS can't do them honestly)
- "How is this different from Duolingo?" → puhekieli-first + speaking out loud + AI roleplay; Finnish is a side dish for the big apps
- "Is the AI accurate?" → what the model is, native-feedback loop, corrections only flag real errors, colloquial forms are never "corrected" into kirjakieli
- "Why an account?" → progress + spaced repetition need one; /try works without

**Follow-up post (~4 weeks later):** "You asked, it's shipped" — changelog of community requests + one new question.

---

## 2. Ten 15-second video scripts (TikTok / Reels / Shorts)

Format for every clip: **HOOK (text on screen, 0–2s) → CONTRAST (2–10s) → PUNCH + CTA (10–15s)**.
Vertical 9:16. Big captions (most watch muted). Väinö/character art as the visual anchor.
Audio: use the app's own TTS clips (you have MP3s for course sentences) or record your own voice.

1. **The classic**
   - Hook: "Your textbook lied to you"
   - Show: "Minä olen" ❌ (red strike) → "Mä oon" ✅, audio of both
   - Punch: "Finns shorten EVERYTHING. Learn the short version first."

2. **The bus stop test**
   - Hook: "2 years of Finnish. Understood nothing."
   - Show: textbook sentence slowly → same sentence at street speed: "Emmä tiiä"
   - Punch: "It's not you. You were taught the wrong register."

3. **The question trick**
   - Hook: "Finns don't say 'onko'"
   - Show: "Onko sinulla nälkä?" → "Onks sul nälkä?" with word-by-word melt animation
   - Punch: "-ko becomes -ks. One rule, instantly more Finnish."

4. **Ordering coffee (Situations demo)**
   - Hook: "POV: ordering coffee in Helsinki"
   - Show: screen-record the kahvila Situation — Joonas asks, you answer, mission accomplished ✅
   - Punch: "Practice it before it happens. Link in bio."

5. **The pronoun diet**
   - Hook: "6 Finnish pronouns you'll never hear"
   - Show: minä→mä, sinä→sä, hän→se, me→me, te→te, he→ne (rapid fire with audio)
   - Punch: "Finns even call people 'it'. Nobody tells you this."

6. **Väinö reacts**
   - Hook: "I asked an AI Finn if my Finnish sounds native"
   - Show: chat with Väinö, typing something slightly textbook, his gentle correction appears
   - Punch: "He never 'fixes' slang into textbook. That's the point."

7. **The kiitos economy**
   - Hook: "How Finns actually say thank you"
   - Show: kiitos → kiitti → kiitoksia, when each fits (captions: formal/friends/extra warm)
   - Punch: "Three words, three vibes."

8. **Sauna small talk**
   - Hook: "The only small talk Finns actually do"
   - Show: saunailta Situation — "Heitänks lisää löylyä?" with translation reveal
   - Punch: "Learn sauna Finnish first. Priorities."

9. **Number shock**
   - Hook: "How Finns say 55"
   - Show: "viisikymmentäviisi" (textbook, endless) → "viiskytviis" (reality), audio both
   - Punch: "Half the letters. All the meaning."

10. **The strawberry test (summer timing!)**
    - Hook: "Buying strawberries at a Finnish tori"
    - Show: tori Situation with Eino — "Maista vaan!" — buy, pay, mission ✅
    - Punch: "6€ a liter and worth every cent. Practice the convo free — link in bio."

Rotate hooks that perform; the format is the asset. Post 3×/week, same clip to all three platforms.

---

## 3. Free AI / creation tools shortlist (July 2026)

The realistic $0 pipeline for the scripts above — mostly **screen recording + editing**,
not fancy AI generation:

| Tool | Use for | Free tier notes |
|---|---|---|
| **CapCut** (desktop) | THE editor: captions, transitions, templates sorted by platform | No watermark on desktop exports, up to 4K; auto-captions built in |
| **Google Veo 3** (AI Studio) | Occasional cinematic b-roll (sauna steam, Helsinki street) | Watermark-free via AI Studio free quota |
| **Seedance 2.0** (ByteDance) | Fast image-to-video clips (animate your Väinö/Marja art!) | Generous free daily generations, no watermark |
| **edge-tts** (already installed) | The Finnish voice for every contrast clip | Free, and it's literally the app's voice — brand-consistent |
| **Canva free** | Thumbnails, text-on-screen frames, carousel posts | Free tier fine for statics |
| **OBS Studio** | Screen-record the Situations demos | Free, open source |

Suggested workflow per clip (~20 min once practiced):
1. Pick script → generate the two audio takes with edge-tts (you have the command)
2. Screen-record the app bit with OBS (if the script needs it)
3. Assemble in CapCut template: hook text → contrast → punch, auto-captions on
4. Export once, post to TikTok + Reels + Shorts

Skip for now: HeyGen (watermark on free), InVideo free (watermark), anything
avatar-based — Väinö IS the avatar and the art is already made.
