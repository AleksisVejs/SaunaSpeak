<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Pattern;
use Illuminate\Database\Seeder;

/**
 * Lessons 9–16: completes A1 (shopping, health, phone, plans) and opens A2
 * (opinions, past tense, work, storytelling). Same puhekieli-first format as
 * LessonSeeder; each lesson carries its own linked grammar pattern.
 *
 * Safe to run on a live database (appends; skips if already seeded).
 */
class ExpansionLessonSeeder extends Seeder
{
    public function run(): void
    {
        // Already expanded - never duplicate on re-run.
        if (Lesson::where('order_index', 9)->exists()) {
            return;
        }

        foreach ($this->lessons() as $index => $data) {
            $pattern = Pattern::create([
                'title' => $data['pattern']['title'],
                'summary' => $data['pattern']['summary'],
                'examples' => $data['pattern']['examples'],
                'order_index' => 6 + $index, // continues after the original five
            ]);

            $lesson = Lesson::create([
                'title' => $data['title'],
                'level' => $data['level'],
                'order_index' => 9 + $index,
                'pattern_id' => $pattern->id,
            ]);

            foreach ($data['sentences'] as $row) {
                $lesson->sentences()->create([
                    'finnish_text' => $row['fi'],
                    'english_text' => $row['en'],
                    'written_text' => $row['written'] ?? null,
                    'word_glosses' => $row['glosses'] ?? null,
                ]);
            }
        }
    }

    private function lessons(): array
    {
        return [
            [
                'title' => 'At the Shop',
                'level' => 'A1',
                'pattern' => [
                    'title' => 'Having things: mul on, onks sul',
                    'summary' => 'Finnish has no verb "to have". You say "at me is": mulla on (spoken: mul on). Asking is just onks sul...? - is there at you?',
                    'examples' => [
                        ['fi' => 'Mul on kassi.', 'en' => 'I have a bag.', 'note' => 'kirjakieli: Minulla on kassi.'],
                        ['fi' => 'Onks sul rahaa?', 'en' => 'Do you have money?', 'note' => 'kirjakieli: Onko sinulla rahaa?'],
                        ['fi' => 'Meil on kokous.', 'en' => 'We have a meeting.', 'note' => 'meillä → meil'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mä etsin maitoo.', 'en' => "I'm looking for milk.", 'written' => 'Minä etsin maitoa.',
                        'glosses' => ['etsin' => "I'm looking for - from 'etsiä'", 'maitoo' => "milk - spoken partitive of 'maito' (maitoa)"]],
                    ['fi' => 'Mis teil on leipä?', 'en' => 'Where do you have the bread?', 'written' => 'Missä teillä on leipä?',
                        'glosses' => ['mis' => "where - spoken 'missä'", 'teil' => "at you (plural/staff) - spoken 'teillä'", 'leipä' => 'bread']],
                    ['fi' => 'Onks teil tätä isompana?', 'en' => 'Do you have this in a bigger size?', 'written' => 'Onko teillä tätä isompana?',
                        'glosses' => ['tätä' => "this - partitive of 'tämä'", 'isompana' => "in a bigger (size) - from 'isompi'"]],
                    ['fi' => 'Mä vaan katselen, kiitti.', 'en' => "I'm just looking, thanks.", 'written' => 'Minä vain katselen, kiitos.',
                        'glosses' => ['vaan' => "just - spoken 'vain'", 'katselen' => "I'm browsing / looking around", 'kiitti' => "thanks - casual 'kiitos'"]],
                    ['fi' => 'Otatsä muovipussin?', 'en' => 'Will you take a plastic bag?', 'written' => 'Otatko sinä muovipussin?',
                        'glosses' => ['otatsä' => "will you take - spoken 'otatko sinä'", 'muovipussin' => 'plastic bag']],
                    ['fi' => 'Ei kiitos, mul on oma kassi.', 'en' => 'No thanks, I have my own bag.', 'written' => 'Ei kiitos, minulla on oma kassi.',
                        'glosses' => ['mul' => "I have (lit. 'at me') - spoken 'minulla'", 'oma' => 'own', 'kassi' => 'bag']],
                    ['fi' => 'Missä kassa on?', 'en' => 'Where is the checkout?',
                        'glosses' => ['kassa' => 'checkout / till']],
                    ['fi' => 'Saanks mä kuitin?', 'en' => 'Can I get the receipt?', 'written' => 'Saanko minä kuitin?',
                        'glosses' => ['saanks' => "can I get - spoken 'saanko'", 'kuitin' => "receipt - object form of 'kuitti'"]],
                ],
            ],
            [
                'title' => 'How Are You Feeling?',
                'level' => 'A1',
                'pattern' => [
                    'title' => 'Feelings hit you: mua väsyttää',
                    'summary' => 'Feelings and aches put YOU in the partitive: mua väsyttää (it-tires me), mun päätä särkee (my head aches). The feeling is the actor, you are the target.',
                    'examples' => [
                        ['fi' => 'Mua väsyttää.', 'en' => "I'm tired.", 'note' => 'lit. "it tires me" - minua → mua'],
                        ['fi' => 'Mua janottaa.', 'en' => "I'm thirsty.", 'note' => 'lit. "it thirsts me"'],
                        ['fi' => 'Päätä särkee.', 'en' => '(My) head aches.', 'note' => 'päätä = pää in the partitive'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mul on huono olo.', 'en' => "I feel unwell.", 'written' => 'Minulla on huono olo.',
                        'glosses' => ['huono' => 'bad', 'olo' => 'feeling / state of being']],
                    ['fi' => 'Mun päätä särkee.', 'en' => 'My head aches.', 'written' => 'Minun päätäni särkee.',
                        'glosses' => ['päätä' => "head - partitive of 'pää'", 'särkee' => 'aches / hurts']],
                    ['fi' => 'Mua väsyttää tosi paljon.', 'en' => "I'm really tired.", 'written' => 'Minua väsyttää todella paljon.',
                        'glosses' => ['mua' => "me - spoken partitive of 'minä'", 'väsyttää' => 'makes tired (the tiredness acts on you!)']],
                    ['fi' => 'Onks sul kuumetta?', 'en' => 'Do you have a fever?', 'written' => 'Onko sinulla kuumetta?',
                        'glosses' => ['kuumetta' => "fever - partitive of 'kuume'"]],
                    ['fi' => 'Mä meen apteekkiin.', 'en' => "I'm going to the pharmacy.", 'written' => 'Minä menen apteekkiin.',
                        'glosses' => ['meen' => "I go - spoken 'menen'", 'apteekkiin' => "to the pharmacy - 'apteekki' + -in"]],
                    ['fi' => 'Ota tää lääke ruoan kanssa.', 'en' => 'Take this medicine with food.', 'written' => 'Ota tämä lääke ruoan kanssa.',
                        'glosses' => ['ota' => "take - command form of 'ottaa'", 'lääke' => 'medicine', 'kanssa' => 'with']],
                    ['fi' => 'Parane pian!', 'en' => 'Get well soon!',
                        'glosses' => ['parane' => "get well - command of 'parantua'", 'pian' => 'soon']],
                    ['fi' => 'Mun pitäis levätä.', 'en' => 'I should rest.', 'written' => 'Minun pitäisi levätä.',
                        'glosses' => ['pitäis' => "should - spoken 'pitäisi'", 'levätä' => 'to rest']],
                ],
            ],
            [
                'title' => 'On the Phone',
                'level' => 'A1',
                'pattern' => [
                    'title' => 'To someone: -lle (soittaa mulle)',
                    'summary' => 'Doing something TO or FOR a person takes the -lle ending: soita mulle (call me), laita sulle (send to you), anna Annalle (give to Anna).',
                    'examples' => [
                        ['fi' => 'Soita mulle!', 'en' => 'Call me!', 'note' => 'minulle → mulle'],
                        ['fi' => 'Mä laitan sulle viestin.', 'en' => "I'll send you a message.", 'note' => 'sinulle → sulle'],
                        ['fi' => 'Kerro mulle kaikki.', 'en' => 'Tell me everything.', 'note' => '-lle = to/for'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mä soitan sulle illalla.', 'en' => "I'll call you tonight.", 'written' => 'Minä soitan sinulle illalla.',
                        'glosses' => ['soitan' => 'I call', 'sulle' => "to you - spoken 'sinulle'", 'illalla' => 'in the evening']],
                    ['fi' => 'Laita mulle viesti.', 'en' => 'Send me a message.', 'written' => 'Laita minulle viesti.',
                        'glosses' => ['laita' => "put / send - command of 'laittaa'", 'mulle' => "to me - spoken 'minulle'", 'viesti' => 'message']],
                    ['fi' => 'Anteeks, mä en kuule sua.', 'en' => "Sorry, I can't hear you.", 'written' => 'Anteeksi, minä en kuule sinua.',
                        'glosses' => ['kuule' => "hear - from 'kuulla'", 'sua' => "you - spoken partitive of 'sinä'"]],
                    ['fi' => 'Mun akku on melkein loppu.', 'en' => 'My battery is almost dead.', 'written' => 'Minun akkuni on melkein loppu.',
                        'glosses' => ['akku' => 'battery', 'melkein' => 'almost', 'loppu' => 'finished / run out']],
                    ['fi' => 'Voitsä soittaa mulle takasin?', 'en' => 'Can you call me back?', 'written' => 'Voitko sinä soittaa minulle takaisin?',
                        'glosses' => ['voitsä' => "can you - spoken 'voitko sinä'", 'takasin' => "back - spoken 'takaisin'"]],
                    ['fi' => 'Mikä sun numero on?', 'en' => "What's your number?", 'written' => 'Mikä sinun numerosi on?',
                        'glosses' => ['numero' => 'number']],
                    ['fi' => 'Mä lähetän sen sulle WhatsAppis.', 'en' => "I'll send it to you on WhatsApp.", 'written' => 'Minä lähetän sen sinulle WhatsAppissa.',
                        'glosses' => ['lähetän' => 'I send', 'sen' => "it - object form of 'se'", 'whatsappis' => "on WhatsApp - spoken -ssa → -s"]],
                    ['fi' => 'Puhutaan myöhemmin!', 'en' => "Let's talk later!",
                        'glosses' => ['puhutaan' => "let's talk - the -taan 'let's' form", 'myöhemmin' => 'later']],
                ],
            ],
            [
                'title' => 'Making Plans',
                'level' => 'A1',
                'pattern' => [
                    'title' => "The let's-form: mennään!",
                    'summary' => 'Spoken Finnish makes "let\'s..." with the -taan form: mennään (let\'s go), otetaan (let\'s take), nähään (let\'s meet). Add -ks to ask: mennäänks?',
                    'examples' => [
                        ['fi' => 'Mennään!', 'en' => "Let's go!", 'note' => 'the most Finnish sentence there is'],
                        ['fi' => 'Otetaanks kahvit?', 'en' => 'Shall we grab coffees?', 'note' => '-taan + -ks = shall we?'],
                        ['fi' => 'Nähään huomenna.', 'en' => 'See you tomorrow.', 'note' => 'kirjakieli: nähdään'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mitä sä teet viikonloppuna?', 'en' => 'What are you doing on the weekend?', 'written' => 'Mitä sinä teet viikonloppuna?',
                        'glosses' => ['teet' => 'you do', 'viikonloppuna' => 'on the weekend']],
                    ['fi' => 'Nähäänks huomenna?', 'en' => 'Shall we meet tomorrow?', 'written' => 'Nähdäänkö huomenna?',
                        'glosses' => ['nähäänks' => "shall we meet - spoken 'nähdäänkö'", 'huomenna' => 'tomorrow']],
                    ['fi' => 'Sopiiks kuudelta?', 'en' => 'Does six o\'clock work?', 'written' => 'Sopiiko kuudelta?',
                        'glosses' => ['sopiiks' => "does it suit - spoken 'sopiiko'", 'kuudelta' => "at six - 'kuusi' + -lta"]],
                    ['fi' => 'Mul ei käy maanantaina.', 'en' => "Monday doesn't work for me.", 'written' => 'Minulla ei käy maanantaina.',
                        'glosses' => ['käy' => 'works / suits', 'maanantaina' => 'on Monday']],
                    ['fi' => 'Mennään leffaan!', 'en' => "Let's go to the movies!", 'written' => 'Mennään elokuviin!',
                        'glosses' => ['mennään' => "let's go", 'leffaan' => "to the movies - slang 'leffa' = movie"]],
                    ['fi' => 'Mä oon vähän myöhässä.', 'en' => "I'm a bit late.", 'written' => 'Minä olen vähän myöhässä.',
                        'glosses' => ['vähän' => 'a bit', 'myöhässä' => 'late / running late']],
                    ['fi' => 'Ei haittaa, mä odotan.', 'en' => "No problem, I'll wait.", 'written' => 'Ei haittaa, minä odotan.',
                        'glosses' => ['haittaa' => "matters / harms - 'ei haittaa' = no problem", 'odotan' => 'I wait']],
                    ['fi' => 'Nähään sit siel!', 'en' => 'See you there then!', 'written' => 'Nähdään sitten siellä!',
                        'glosses' => ['sit' => "then - spoken 'sitten'", 'siel' => "there - spoken 'siellä'"]],
                ],
            ],
            [
                'title' => 'What Do You Think?',
                'level' => 'A2',
                'pattern' => [
                    'title' => 'Opinions: mun mielestä, musta',
                    'summary' => 'Say opinions with mun mielestä ("from my mind") - or even shorter, just musta. Agree with samaa mieltä, disagree with eri mieltä.',
                    'examples' => [
                        ['fi' => 'Mun mielestä tää on hyvä.', 'en' => 'I think this is good.', 'note' => 'kirjakieli: minun mielestäni'],
                        ['fi' => 'Musta toi on outoo.', 'en' => 'I find that weird.', 'note' => "musta = 'minusta', not the color black!"],
                        ['fi' => 'Mä oon eri mieltä.', 'en' => 'I disagree.', 'note' => 'lit. "of a different mind"'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mun mielestä tää on hyvä idea.', 'en' => 'I think this is a good idea.', 'written' => 'Minun mielestäni tämä on hyvä idea.',
                        'glosses' => ['mielestä' => "in (my) opinion - lit. 'from the mind'", 'idea' => 'idea']],
                    ['fi' => 'Mä oon samaa mieltä.', 'en' => 'I agree.', 'written' => 'Minä olen samaa mieltä.',
                        'glosses' => ['samaa' => 'same (partitive)', 'mieltä' => "mind / opinion - 'samaa mieltä' = agreed"]],
                    ['fi' => 'Emmä oo ihan varma.', 'en' => "I'm not quite sure.", 'written' => 'En minä ole aivan varma.',
                        'glosses' => ['emmä' => "I don't / I'm not - spoken 'en minä'", 'varma' => 'sure / certain']],
                    ['fi' => 'Toi on musta outoo.', 'en' => 'I find that weird.', 'written' => 'Tuo on minusta outoa.',
                        'glosses' => ['musta' => "in my view - spoken 'minusta' (not the color!)", 'outoo' => "weird - spoken 'outoa'"]],
                    ['fi' => 'Mikä sun lempiruoka on?', 'en' => "What's your favorite food?", 'written' => 'Mikä sinun lempiruokasi on?',
                        'glosses' => ['lempiruoka' => "favorite food - 'lempi-' = favorite"]],
                    ['fi' => 'Mä tykkään tästä tosi paljon.', 'en' => 'I like this a lot.', 'written' => 'Minä pidän tästä todella paljon.',
                        'glosses' => ['tykkään' => "I like - spoken favorite 'tykätä'", 'tästä' => "this - 'liking' takes -sta"]],
                    ['fi' => 'Se riippuu säästä.', 'en' => 'It depends on the weather.',
                        'glosses' => ['riippuu' => 'depends (lit. hangs)', 'säästä' => "on the weather - 'sää' + -stä"]],
                    ['fi' => 'Ihan hullu juttu!', 'en' => 'Totally crazy!', 'written' => 'Aivan hullu juttu!',
                        'glosses' => ['hullu' => 'crazy', 'juttu' => 'thing / story - the most useful vague word in Finnish']],
                ],
            ],
            [
                'title' => 'Last Weekend',
                'level' => 'A2',
                'pattern' => [
                    'title' => 'Past tense: the -i- marker',
                    'summary' => 'The past slips an -i- into the verb: oon → olin, meen → menin, syön → söin. Spoken Finnish also loves the me + -tiin past: me mentiin (we went).',
                    'examples' => [
                        ['fi' => 'Mä olin kotona.', 'en' => 'I was at home.', 'note' => 'oon → olin'],
                        ['fi' => 'Me mentiin saunaan.', 'en' => 'We went to the sauna.', 'note' => 'spoken we-past: -tiin'],
                        ['fi' => 'Mä söin jo.', 'en' => 'I already ate.', 'note' => 'syön → söin'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mä olin eilen saunassa.', 'en' => 'I was in the sauna yesterday.', 'written' => 'Minä olin eilen saunassa.',
                        'glosses' => ['olin' => "I was - past of 'oon'", 'eilen' => 'yesterday']],
                    ['fi' => 'Me mentiin mökille.', 'en' => 'We went to the cottage.', 'written' => 'Me menimme mökille.',
                        'glosses' => ['mentiin' => "we went - spoken we-past", 'mökille' => "to the cottage - 'mökki' is a Finnish institution"]],
                    ['fi' => 'Mä söin liikaa pizzaa.', 'en' => 'I ate too much pizza.', 'written' => 'Minä söin liikaa pizzaa.',
                        'glosses' => ['söin' => "I ate - past of 'syön'", 'liikaa' => 'too much']],
                    ['fi' => 'Nukuitsä hyvin?', 'en' => 'Did you sleep well?', 'written' => 'Nukuitko sinä hyvin?',
                        'glosses' => ['nukuitsä' => "did you sleep - spoken 'nukuitko sinä'", 'hyvin' => 'well']],
                    ['fi' => 'Se oli tosi kivaa.', 'en' => 'It was really fun.', 'written' => 'Se oli todella kivaa.',
                        'glosses' => ['oli' => "was - past of 'on'", 'kivaa' => "fun / nice - from 'kiva'"]],
                    ['fi' => 'Mä näin sut eilen kaupungilla.', 'en' => 'I saw you downtown yesterday.', 'written' => 'Minä näin sinut eilen kaupungilla.',
                        'glosses' => ['näin' => "I saw - past of 'nään'", 'sut' => "you - spoken object form of 'sinä'", 'kaupungilla' => 'in town / downtown']],
                    ['fi' => 'Mitä sä teit viikonloppuna?', 'en' => 'What did you do on the weekend?', 'written' => 'Mitä sinä teit viikonloppuna?',
                        'glosses' => ['teit' => "you did - past of 'teet'"]],
                    ['fi' => 'En tehny yhtään mitään.', 'en' => "I didn't do anything at all.", 'written' => 'En tehnyt yhtään mitään.',
                        'glosses' => ['tehny' => "done - spoken 'tehnyt'", 'yhtään' => 'at all', 'mitään' => 'anything']],
                ],
            ],
            [
                'title' => 'At Work',
                'level' => 'A2',
                'pattern' => [
                    'title' => 'Must: mun täytyy, mun pitää',
                    'summary' => 'To say "I must", the person goes into the mun/sun form and the verb stays basic: mun täytyy mennä (I have to go), sun pitää nähä tää (you have to see this).',
                    'examples' => [
                        ['fi' => 'Mun täytyy mennä.', 'en' => 'I have to go.', 'note' => 'kirjakieli: minun täytyy mennä'],
                        ['fi' => 'Sun pitää maistaa tätä.', 'en' => 'You have to taste this.', 'note' => 'pitää = softer must'],
                        ['fi' => 'Mun pitäis lukee.', 'en' => 'I should read.', 'note' => 'pitäis = should (conditional)'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Mä oon töissä kahteen asti.', 'en' => "I'm at work until two.", 'written' => 'Minä olen töissä kahteen asti.',
                        'glosses' => ['töissä' => "at work - lit. 'in works'", 'kahteen' => "until two - 'kaksi' + -en", 'asti' => 'until']],
                    ['fi' => 'Meil on kokous kymmeneltä.', 'en' => 'We have a meeting at ten.', 'written' => 'Meillä on kokous kymmeneltä.',
                        'glosses' => ['kokous' => 'meeting', 'kymmeneltä' => 'at ten']],
                    ['fi' => 'Mun täytyy tehä tää loppuun.', 'en' => 'I have to finish this.', 'written' => 'Minun täytyy tehdä tämä loppuun.',
                        'glosses' => ['täytyy' => 'must / have to', 'tehä' => "do - spoken 'tehdä'", 'loppuun' => 'to the end / to completion']],
                    ['fi' => 'Voitsä auttaa mua tän kanssa?', 'en' => 'Can you help me with this?', 'written' => 'Voitko sinä auttaa minua tämän kanssa?',
                        'glosses' => ['auttaa' => 'to help', 'tän' => "this - spoken 'tämän'"]],
                    ['fi' => 'Mä pidän tauon.', 'en' => "I'm taking a break.", 'written' => 'Minä pidän tauon.',
                        'glosses' => ['pidän' => "I take/hold - from 'pitää'", 'tauon' => "break - object form of 'tauko'"]],
                    ['fi' => 'Lähetä se mulle sähköpostilla.', 'en' => 'Send it to me by email.', 'written' => 'Lähetä se minulle sähköpostilla.',
                        'glosses' => ['lähetä' => "send - command of 'lähettää'", 'sähköpostilla' => "by email - 'sähköposti' + -lla"]],
                    ['fi' => 'Mun pomo on ihan jees.', 'en' => 'My boss is pretty okay.',
                        'glosses' => ['pomo' => 'boss (casual)', 'jees' => "okay / fine - yes, from English 'yes'"]],
                    ['fi' => 'Mä lopetan tänään aikasin.', 'en' => "I'm finishing early today.", 'written' => 'Minä lopetan tänään aikaisin.',
                        'glosses' => ['lopetan' => 'I finish / stop', 'aikasin' => "early - spoken 'aikaisin'"]],
                ],
            ],
            [
                'title' => 'Telling Stories',
                'level' => 'A2',
                'pattern' => [
                    'title' => 'Story glue: sit, ku, vaikka',
                    'summary' => 'Spoken stories run on three little words: sit (then), ku (when/because), vaikka (even though). Master these and your sentences start chaining like a native\'s.',
                    'examples' => [
                        ['fi' => 'Sit mä lähdin.', 'en' => 'Then I left.', 'note' => 'sitten → sit'],
                        ['fi' => 'Ku mä olin pieni...', 'en' => 'When I was little...', 'note' => 'kun/koska → ku'],
                        ['fi' => 'Vaikka satoi, me mentiin.', 'en' => 'Even though it rained, we went.', 'note' => 'vaikka = even though'],
                    ],
                ],
                'sentences' => [
                    ['fi' => 'Eka mä heräsin, sit mä join kahvii.', 'en' => 'First I woke up, then I drank coffee.', 'written' => 'Ensin minä heräsin, sitten minä join kahvia.',
                        'glosses' => ['eka' => "first - spoken 'ensin'", 'heräsin' => 'I woke up', 'sit' => "then - spoken 'sitten'", 'join' => "I drank - past of 'juon'"]],
                    ['fi' => 'Mä myöhästyin, ku bussi ei tullu.', 'en' => "I was late because the bus didn't come.", 'written' => 'Minä myöhästyin, koska bussi ei tullut.',
                        'glosses' => ['myöhästyin' => 'I was late', 'ku' => "because / when - spoken 'koska'/'kun'", 'tullu' => "come - spoken 'tullut'"]],
                    ['fi' => 'Me lähettiin, vaikka satoi.', 'en' => 'We left even though it was raining.', 'written' => 'Me lähdimme, vaikka satoi.',
                        'glosses' => ['lähettiin' => 'we left - spoken we-past', 'vaikka' => 'even though', 'satoi' => 'it rained']],
                    ['fi' => 'Ja sit kaikki alko nauraa.', 'en' => 'And then everyone started laughing.', 'written' => 'Ja sitten kaikki alkoivat nauraa.',
                        'glosses' => ['kaikki' => 'everyone / all', 'alko' => "started - spoken 'alkoi'", 'nauraa' => 'to laugh']],
                    ['fi' => 'Arvaa mitä tapahtu!', 'en' => 'Guess what happened!', 'written' => 'Arvaa mitä tapahtui!',
                        'glosses' => ['arvaa' => "guess - command of 'arvata'", 'tapahtu' => "happened - spoken 'tapahtui'"]],
                    ['fi' => 'Ei voi olla totta!', 'en' => "It can't be true!",
                        'glosses' => ['voi' => 'can', 'totta' => "true - partitive of 'tosi'"]],
                    ['fi' => 'No siis, se oli aika mahtavaa.', 'en' => 'Well, I mean, it was pretty awesome.',
                        'glosses' => ['siis' => "so / I mean - Finland's favorite filler", 'aika' => 'pretty / quite', 'mahtavaa' => 'awesome']],
                    ['fi' => 'Pitkä juttu, mä kerron saunassa.', 'en' => "Long story - I'll tell you in the sauna.", 'written' => 'Pitkä juttu, minä kerron saunassa.',
                        'glosses' => ['pitkä' => 'long', 'kerron' => 'I tell / will tell']],
                ],
            ],
        ];
    }
}
