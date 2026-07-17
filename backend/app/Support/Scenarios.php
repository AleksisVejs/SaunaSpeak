<?php

namespace App\Support;

/**
 * Tilanteet: guided roleplay scenarios for conversation practice. Each one
 * drops the learner into an everyday situation (buying groceries, ordering
 * coffee) with a concrete mission - the difference from free-form Sauna Chat
 * is that there's a goal to reach, which gives beginners something to aim at
 * instead of a blank "say something" box.
 *
 * The catalog is code, not DB: scenarios change with deploys, not at runtime,
 * and the LLM prompt fragments belong next to the metadata they describe.
 */
class Scenarios
{
    /**
     * Scenario definitions.
     *
     * - persona/role/scene feed the system prompt (the character to play).
     * - voice is the character's TTS voice (male|female), matching the persona.
     * - mission is learner-facing English: what to accomplish.
     * - goal_check tells the model when the mission counts as done.
     * - opener_* is the character's first line, shown client-side.
     * - goals maps to the intake-quiz `goal` preference for ordering.
     */
    private const CATALOG = [
        'kauppa' => [
            'emoji' => '🛒',
            'title' => 'At the grocery store',
            'tagline' => 'Find what you need and get through the till.',
            'persona' => 'Marja',
            'voice' => 'female',
            'role' => 'a friendly middle-aged cashier at a K-Market in a Finnish suburb',
            'scene' => 'The learner is a customer in your grocery store. They may ask where things are (milk, bread, coffee), then come to your till to pay. Prices are in euros; keep them realistic.',
            'mission' => 'Ask where the milk is, buy it, and pay at the till.',
            'goal_check' => 'the learner has asked for or found a product AND completed paying for it',
            'opener_fi' => 'No moi! Tarviitko apua, vai löytyykö kaikki?',
            'opener_en' => 'Well hi! Do you need help, or are you finding everything?',
            'difficulty' => 'easy',
            'goals' => ['move', 'travel'],
        ],
        'kahvila' => [
            'emoji' => '☕',
            'title' => 'Ordering at a café',
            'tagline' => 'Coffee and a korvapuusti, please.',
            'persona' => 'Joonas',
            'voice' => 'male',
            'role' => 'a relaxed young barista at a Helsinki café',
            'scene' => 'The learner is at the counter of your café. On offer: kahvi, tee, kaakao, korvapuusti (cinnamon bun), mustikkapiirakka (blueberry pie). A coffee is around 3€, a bun 4€. Ask if they want it to stay or to go (täällä vai mukaan).',
            'mission' => 'Order a coffee and something to eat, and answer whether it\'s for here or to go.',
            'goal_check' => 'the learner has ordered a drink and answered the here-or-to-go question',
            'opener_fi' => 'Moi! Mitä sulle saisi olla?',
            'opener_en' => 'Hi! What can I get you?',
            'difficulty' => 'easy',
            'goals' => ['travel', 'casual'],
        ],
        'bussi' => [
            'emoji' => '🚌',
            'title' => 'Taking the bus',
            'tagline' => 'Get a ticket and get off at the right stop.',
            'persona' => 'Pekka',
            'voice' => 'male',
            'role' => 'a laconic but helpful bus driver',
            'scene' => 'The learner boards your bus. They may want a ticket to the centre (keskusta) and to know which stop to get off at. A single ticket is about 3€. Keep answers short - a driver has a schedule.',
            'mission' => 'Buy a ticket to the centre and find out where to get off.',
            'goal_check' => 'the learner has bought a ticket AND asked about or been told their stop',
            'opener_fi' => 'Iltaa. Minne matka?',
            'opener_en' => 'Evening. Where are you headed?',
            'difficulty' => 'medium',
            'goals' => ['move', 'travel'],
        ],
        'apteekki' => [
            'emoji' => '💊',
            'title' => 'At the pharmacy',
            'tagline' => 'Explain what hurts and get the right thing.',
            'persona' => 'Liisa',
            'voice' => 'female',
            'role' => 'a caring pharmacist',
            'scene' => 'The learner comes into your pharmacy. They might have a headache (pää kipeä / päänsärky), a cold (flunssa) or a sore throat (kurkku kipeä). Recommend something simple like Burana and mention the dose plainly.',
            'mission' => 'Explain a small ailment and buy something for it.',
            'goal_check' => 'the learner has described a symptom and accepted or bought a remedy',
            'opener_fi' => 'Hei, mitäs sulle? Miten voin auttaa?',
            'opener_en' => 'Hi, what can I do for you? How can I help?',
            'difficulty' => 'medium',
            'goals' => ['move'],
        ],
        'ravintola' => [
            'emoji' => '🍽️',
            'title' => 'At a restaurant',
            'tagline' => 'Table, order, bill - the full round.',
            'persona' => 'Antti',
            'voice' => 'male',
            'role' => 'a professional but warm waiter in a casual Finnish restaurant',
            'scene' => 'The learner arrives at your restaurant. Seat them, offer the menu: lohikeitto (salmon soup), lihapullat (meatballs), paistettu kuha (fried pike-perch). Drinks: vesi, olut, viini. At the end they ask for the bill (lasku).',
            'mission' => 'Get a table, order food and a drink, and ask for the bill.',
            'goal_check' => 'the learner has ordered food AND asked for the bill',
            'opener_fi' => 'Tervetuloa! Onks teillä pöytävaraus?',
            'opener_en' => 'Welcome! Do you have a table reservation?',
            'difficulty' => 'medium',
            'goals' => ['travel', 'casual'],
        ],
        'naapuri' => [
            'emoji' => '🏡',
            'title' => 'Meeting your neighbor',
            'tagline' => 'Introduce yourself over the hedge.',
            'persona' => 'Ritva',
            'voice' => 'female',
            'role' => 'a curious, kind retiree who lives next door',
            'scene' => 'The learner has just moved in next door and you meet by the mailboxes. You want to know who they are, where they moved from and how they like it here. Offer a small piece of local advice (sauna day, recycling, the good grocery store).',
            'mission' => 'Introduce yourself, say where you\'re from, and answer a bit of small talk.',
            'goal_check' => 'the learner has given their name AND shared where they are from or why they moved',
            'opener_fi' => 'No hei! Sä oot varmaan se uus naapuri? Mä oon Ritva.',
            'opener_en' => "Well hello! You must be the new neighbor? I'm Ritva.",
            'difficulty' => 'easy',
            'goals' => ['move', 'family'],
        ],
        'tori' => [
            'emoji' => '🍓',
            'title' => 'At the market square',
            'tagline' => 'Summer strawberries, straight from the stall.',
            'persona' => 'Eino',
            'voice' => 'male',
            'role' => 'a cheerful old berry seller at the kauppatori',
            'scene' => 'The learner stops at your berry stall. You sell mansikka (strawberries, 6€/litra), mustikka (blueberries, 8€/litra) and herne (peas). Offer a taste (maista!), weigh the berries, take payment.',
            'mission' => 'Ask the price of strawberries, buy some, and pay.',
            'goal_check' => 'the learner has asked a price AND completed a purchase',
            'opener_fi' => 'Päivää päivää! Tuoreita mansikoita, maista vaan!',
            'opener_en' => 'Good day, good day! Fresh strawberries - have a taste!',
            'difficulty' => 'easy',
            'goals' => ['travel', 'family', 'casual'],
        ],
        'saunailta' => [
            'emoji' => '🧖',
            'title' => 'Sauna evening with a friend',
            'tagline' => 'Löyly, makkara and easy small talk.',
            'persona' => 'Ossi',
            'voice' => 'male',
            'role' => "the learner's easygoing Finnish friend, hosting a sauna evening at his mökki",
            'scene' => 'The learner is your guest at the summer cottage. Talk about the sauna heat (lisää löylyä?), offer makkara and juoma, ask about their week, maybe suggest a dip in the lake (järvi). Pure relaxed small talk between friends.',
            'mission' => 'Chat about the sauna, accept or decline food and drink, and keep the small talk going.',
            'goal_check' => 'the learner has responded naturally to at least three questions or offers',
            'opener_fi' => 'Moro! Sauna on just sopiva. Heitänks lisää löylyä?',
            'opener_en' => 'Hey! The sauna is just right. Should I throw more steam?',
            'difficulty' => 'easy',
            'goals' => ['family', 'casual'],
        ],
        'puhelin' => [
            'emoji' => '📞',
            'title' => 'Phone call to the health center',
            'tagline' => 'No faces, no pointing - just your Finnish on the line.',
            'persona' => 'Sirpa',
            'voice' => 'female',
            'role' => 'a brisk but professional receptionist at a Finnish health center (terveysasema), answering the phone',
            'scene' => 'The learner is calling your health center to book a doctor\'s appointment. This is a PHONE CALL: they cannot see you or point at anything, so everything must be said in words. Ask their name, what the problem is (flunssa, selkä kipeä, korvakipu...) and how long it has lasted, then offer a concrete time (e.g. torstaina kello 10.40) and confirm the booking. You are friendly but efficient - other calls are waiting - and you use real reception phrases like "hetkinen" and "katsotaanpa".',
            'mission' => 'Book a doctor\'s appointment by phone: give your name, describe the problem, and agree on a time.',
            'goal_check' => 'the learner has described their problem AND accepted a specific appointment time',
            'opener_fi' => 'Terveysasema, Sirpa puhelimessa. Miten voin auttaa?',
            'opener_en' => 'Health center, Sirpa speaking. How can I help?',
            'difficulty' => 'hard',
            'goals' => ['move'],
        ],
    ];

    /** First-completion XP by difficulty - paid once, on the first clear. */
    public const XP = ['easy' => 30, 'medium' => 50, 'hard' => 80];

    public static function xpFor(array $scenario): int
    {
        return self::XP[$scenario['difficulty']] ?? self::XP['easy'];
    }

    /** The whole catalog, ids included. */
    public static function all(): array
    {
        $out = [];
        foreach (self::CATALOG as $id => $scenario) {
            $out[] = ['id' => $id] + $scenario;
        }

        return $out;
    }

    public static function find(?string $id): ?array
    {
        return $id !== null && isset(self::CATALOG[$id])
            ? ['id' => $id] + self::CATALOG[$id]
            : null;
    }

    /** Valid ids, for request validation. */
    public static function ids(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * Catalog ordered for one learner: scenarios matching their intake-quiz
     * goal float to the top (stable within each half), each entry flagged
     * with `recommended` so the UI can label them.
     */
    public static function forGoal(?string $goal): array
    {
        $all = array_map(function (array $s) use ($goal) {
            $s['recommended'] = $goal !== null && in_array($goal, $s['goals'], true);

            return $s;
        }, self::all());

        usort($all, fn ($a, $b) => $b['recommended'] <=> $a['recommended']);

        return $all;
    }
}
