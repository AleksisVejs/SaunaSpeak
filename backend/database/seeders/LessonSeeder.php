<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

/**
 * All content is everyday SPOKEN Finnish (puhekieli) - the language people
 * actually use in shops, buses and saunas. Each sentence carries:
 *
 *   fi       spoken form (what the learner says)
 *   en       English translation
 *   written  kirjakieli reference, omitted when identical to the spoken form
 *   glosses  per-word dictionary keyed by lowercase word (tap-a-word feature)
 */
class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = [
            [
                'title' => 'First Words in the Sauna',
                'level' => 'A0',
                'sentences' => [
                    [
                        'fi' => 'Moi! Mä oon Anna.',
                        'en' => "Hi! I'm Anna.",
                        'written' => 'Hei! Minä olen Anna.',
                        'glosses' => [
                            'moi' => 'hi / hey - casual greeting',
                            'mä' => "I - spoken form of 'minä'",
                            'oon' => "am - spoken form of 'olen'",
                            'anna' => 'Anna (a name)',
                        ],
                    ],
                    [
                        'fi' => 'Mikä sun nimi on?',
                        'en' => "What's your name?",
                        'written' => 'Mikä sinun nimesi on?',
                        'glosses' => [
                            'mikä' => 'what / which',
                            'sun' => "your - spoken form of 'sinun'",
                            'nimi' => 'name',
                            'on' => "is - 3rd person of 'olla' (to be)",
                        ],
                    ],
                    [
                        'fi' => 'Hauska tavata!',
                        'en' => 'Nice to meet you!',
                        'glosses' => [
                            'hauska' => 'nice, fun',
                            'tavata' => 'to meet',
                        ],
                    ],
                    [
                        'fi' => 'Mitä kuuluu?',
                        'en' => 'How are you?',
                        'glosses' => [
                            'mitä' => "what - partitive of 'mikä'",
                            'kuuluu' => "literally 'is heard' - Mitä kuuluu? = How are you?",
                        ],
                    ],
                    [
                        'fi' => 'Ihan hyvää, kiitos.',
                        'en' => 'Pretty good, thanks.',
                        'glosses' => [
                            'ihan' => 'quite / pretty - very common softener',
                            'hyvää' => "good - partitive of 'hyvä'",
                            'kiitos' => 'thanks',
                        ],
                    ],
                    [
                        'fi' => 'Anteeks, mä en ymmärrä.',
                        'en' => "Sorry, I don't understand.",
                        'written' => 'Anteeksi, minä en ymmärrä.',
                        'glosses' => [
                            'anteeks' => "sorry - spoken form of 'anteeksi'",
                            'mä' => "I - spoken form of 'minä'",
                            'en' => "don't - the negative verb, 1st person",
                            'ymmärrä' => "understand - negative form of 'ymmärtää'",
                        ],
                    ],
                    [
                        'fi' => 'Puhuksä englantia?',
                        'en' => 'Do you speak English?',
                        'written' => 'Puhutko sinä englantia?',
                        'glosses' => [
                            'puhuksä' => "do you speak? - spoken 'puhutko sinä' squeezed into one word",
                            'englantia' => "English - partitive of 'englanti'",
                        ],
                    ],
                    [
                        'fi' => 'Moikka, nähään!',
                        'en' => 'Bye, see you!',
                        'written' => 'Näkemiin, nähdään!',
                        'glosses' => [
                            'moikka' => 'bye - casual',
                            'nähään' => "see you - spoken form of 'nähdään' (we'll see each other)",
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Spoken Finnish Survival Kit',
                'level' => 'A0',
                'sentences' => [
                    [
                        'fi' => 'No niin, mennään!',
                        'en' => "Alright, let's go!",
                        'glosses' => [
                            'no' => 'well / alright - filler word Finns use constantly',
                            'niin' => "so / like that - 'no niin' = alright, here we go",
                            'mennään' => "let's go - the passive used as 'we' in spoken Finnish",
                        ],
                    ],
                    [
                        'fi' => 'Emmä tiiä.',
                        'en' => "I don't know.",
                        'written' => 'En minä tiedä.',
                        'glosses' => [
                            'emmä' => "I don't - 'en' + 'mä' fused into one word",
                            'tiiä' => "know - spoken form of 'tiedä'",
                        ],
                    ],
                    [
                        'fi' => 'Mitä sä teet?',
                        'en' => 'What are you doing?',
                        'written' => 'Mitä sinä teet?',
                        'glosses' => [
                            'mitä' => "what - partitive of 'mikä'",
                            'sä' => "you - spoken form of 'sinä'",
                            'teet' => "do / are doing - 2nd person of 'tehdä'",
                        ],
                    ],
                    [
                        'fi' => 'Ei se mitään.',
                        'en' => "No worries, it's nothing.",
                        'glosses' => [
                            'ei' => 'no / not - 3rd person negative',
                            'se' => "it - also used for 'he/she' in spoken Finnish",
                            'mitään' => "anything - 'ei se mitään' = it's nothing, no worries",
                        ],
                    ],
                    [
                        'fi' => 'Ihan sama.',
                        'en' => "Whatever, I don't mind.",
                        'glosses' => [
                            'ihan' => 'quite / totally',
                            'sama' => "same - 'ihan sama' = whatever, doesn't matter",
                        ],
                    ],
                    [
                        'fi' => 'Onks sul nälkä?',
                        'en' => 'Are you hungry?',
                        'written' => 'Onko sinulla nälkä?',
                        'glosses' => [
                            'onks' => "is (there)? - spoken form of 'onko'",
                            'sul' => "you ('at you') - spoken form of 'sinulla'",
                            'nälkä' => "hunger - Finnish literally asks 'is there hunger at you?'",
                        ],
                    ],
                    [
                        'fi' => 'Joo, sopii mulle.',
                        'en' => 'Yeah, works for me.',
                        'written' => 'Kyllä, se sopii minulle.',
                        'glosses' => [
                            'joo' => "yeah - the everyday spoken 'yes' ('kyllä' is formal)",
                            'sopii' => "suits / works - 3rd person of 'sopia'",
                            'mulle' => "for me - spoken form of 'minulle'",
                        ],
                    ],
                    [
                        'fi' => 'Kato, toi on mun kaveri.',
                        'en' => "Look, that's my friend.",
                        'written' => 'Katso, tuo on minun kaverini.',
                        'glosses' => [
                            'kato' => "look! - spoken form of 'katso'",
                            'toi' => "that - spoken form of 'tuo'",
                            'on' => "is - 3rd person of 'olla' (to be)",
                            'mun' => "my - spoken form of 'minun'",
                            'kaveri' => 'friend, buddy',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Coffee and Pulla',
                'level' => 'A0',
                'sentences' => [
                    [
                        'fi' => 'Mä otan kahvin, kiitos.',
                        'en' => "I'll have a coffee, please.",
                        'written' => 'Minä otan kahvin, kiitos.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'otan' => "take / will have - 1st person of 'ottaa'",
                            'kahvin' => "a coffee - object form of 'kahvi'",
                            'kiitos' => 'thanks / please',
                        ],
                    ],
                    [
                        'fi' => 'Saisinks mä yhen pullan?',
                        'en' => 'Could I get one pulla?',
                        'written' => 'Saisinko minä yhden pullan?',
                        'glosses' => [
                            'saisinks' => "could I get? - spoken form of 'saisinko'",
                            'mä' => "I - spoken form of 'minä'",
                            'yhen' => "one - spoken form of 'yhden'",
                            'pullan' => "a pulla (cardamom bun) - object form of 'pulla'",
                        ],
                    ],
                    [
                        'fi' => 'Paljonks toi maksaa?',
                        'en' => 'How much does that cost?',
                        'written' => 'Paljonko tuo maksaa?',
                        'glosses' => [
                            'paljonks' => "how much? - spoken form of 'paljonko'",
                            'toi' => "that - spoken form of 'tuo'",
                            'maksaa' => 'costs',
                        ],
                    ],
                    [
                        'fi' => 'Se maksaa kolme euroo.',
                        'en' => 'It costs three euros.',
                        'written' => 'Se maksaa kolme euroa.',
                        'glosses' => [
                            'se' => 'it',
                            'maksaa' => 'costs',
                            'kolme' => 'three',
                            'euroo' => "euros - spoken form of 'euroa'",
                        ],
                    ],
                    [
                        'fi' => 'Varo, kahvi on tosi kuumaa.',
                        'en' => 'Careful, the coffee is really hot.',
                        'written' => 'Varo, kahvi on todella kuumaa.',
                        'glosses' => [
                            'varo' => "watch out - imperative of 'varoa'",
                            'kahvi' => 'coffee',
                            'on' => 'is',
                            'tosi' => "really - spoken intensifier (standard 'todella')",
                            'kuumaa' => "hot - partitive of 'kuuma'",
                        ],
                    ],
                    [
                        'fi' => 'Tää on tosi hyvää!',
                        'en' => 'This is really good!',
                        'written' => 'Tämä on todella hyvää!',
                        'glosses' => [
                            'tää' => "this - spoken form of 'tämä'",
                            'on' => 'is',
                            'tosi' => "really - spoken intensifier (standard 'todella')",
                            'hyvää' => "good - partitive of 'hyvä'",
                        ],
                    ],
                    [
                        'fi' => 'Voinks mä maksaa kortilla?',
                        'en' => 'Can I pay by card?',
                        'written' => 'Voinko minä maksaa kortilla?',
                        'glosses' => [
                            'voinks' => "can I? - spoken form of 'voinko'",
                            'mä' => "I - spoken form of 'minä'",
                            'maksaa' => 'to pay',
                            'kortilla' => "by card - kortti + -lla ('with')",
                        ],
                    ],
                    [
                        'fi' => 'Ole hyvä.',
                        'en' => "Here you go. / You're welcome.",
                        'glosses' => [
                            'ole' => "be - imperative of 'olla'",
                            'hyvä' => "good - 'ole hyvä' = here you go / you're welcome",
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Finding Your Way',
                'level' => 'A0',
                'sentences' => [
                    [
                        'fi' => 'Mis rautatieasema on?',
                        'en' => 'Where is the railway station?',
                        'written' => 'Missä rautatieasema on?',
                        'glosses' => [
                            'mis' => "where - spoken form of 'missä'",
                            'rautatieasema' => "railway station - rautatie 'railway' + asema 'station'",
                            'on' => 'is',
                        ],
                    ],
                    [
                        'fi' => 'Se on tuol.',
                        'en' => "It's over there.",
                        'written' => 'Se on tuolla.',
                        'glosses' => [
                            'se' => 'it',
                            'on' => 'is',
                            'tuol' => "over there - spoken form of 'tuolla'",
                        ],
                    ],
                    [
                        'fi' => 'Käänny vasemmalle.',
                        'en' => 'Turn left.',
                        'glosses' => [
                            'käänny' => "turn - imperative of 'kääntyä'",
                            'vasemmalle' => 'to the left',
                        ],
                    ],
                    [
                        'fi' => 'Käänny oikeelle.',
                        'en' => 'Turn right.',
                        'written' => 'Käänny oikealle.',
                        'glosses' => [
                            'käänny' => "turn - imperative of 'kääntyä'",
                            'oikeelle' => "to the right - spoken form of 'oikealle'",
                        ],
                    ],
                    [
                        'fi' => 'Mee suoraan eteenpäin.',
                        'en' => 'Go straight ahead.',
                        'written' => 'Mene suoraan eteenpäin.',
                        'glosses' => [
                            'mee' => "go - spoken form of 'mene'",
                            'suoraan' => 'straight',
                            'eteenpäin' => 'forward / ahead',
                        ],
                    ],
                    [
                        'fi' => 'Onks se kaukana?',
                        'en' => 'Is it far?',
                        'written' => 'Onko se kaukana?',
                        'glosses' => [
                            'onks' => "is? - spoken form of 'onko'",
                            'se' => 'it',
                            'kaukana' => 'far away',
                        ],
                    ],
                    [
                        'fi' => 'Ei oo, se on ihan lähellä.',
                        'en' => "No, it's really close.",
                        'written' => 'Ei ole, se on aivan lähellä.',
                        'glosses' => [
                            'ei' => 'no / not',
                            'oo' => "be - spoken form of 'ole'; 'ei oo' = isn't",
                            'se' => 'it',
                            'on' => 'is',
                            'ihan' => 'quite / really',
                            'lähellä' => 'near, close by',
                        ],
                    ],
                    [
                        'fi' => 'Kiitti avusta!',
                        'en' => 'Thanks for the help!',
                        'written' => 'Kiitos avusta!',
                        'glosses' => [
                            'kiitti' => "thanks - casual spoken 'kiitos'",
                            'avusta' => "for the help - apu 'help' + -sta",
                        ],
                    ],
                ],
            ],
            [
                'title' => 'At Home',
                'level' => 'A1',
                'sentences' => [
                    [
                        'fi' => 'Mä asun Helsingissä.',
                        'en' => 'I live in Helsinki.',
                        'written' => 'Minä asun Helsingissä.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'asun' => "live - 1st person of 'asua'",
                            'helsingissä' => "in Helsinki - Helsinki + -ssä ('in')",
                        ],
                    ],
                    [
                        'fi' => 'Meil on pieni kämppä.',
                        'en' => 'We have a small apartment.',
                        'written' => 'Meillä on pieni asunto.',
                        'glosses' => [
                            'meil' => "we ('at us') - spoken form of 'meillä'",
                            'on' => "is - 'meil on' = we have",
                            'pieni' => 'small',
                            'kämppä' => "apartment, pad - colloquial (standard 'asunto')",
                        ],
                    ],
                    [
                        'fi' => 'Keittiössä on jääkaappi.',
                        'en' => 'There is a fridge in the kitchen.',
                        'glosses' => [
                            'keittiössä' => "in the kitchen - keittiö + -ssä ('in')",
                            'on' => 'is / there is',
                            'jääkaappi' => "fridge - jää 'ice' + kaappi 'cupboard'",
                        ],
                    ],
                    [
                        'fi' => 'Mä siivoon lauantaina.',
                        'en' => 'I clean on Saturday.',
                        'written' => 'Minä siivoan lauantaina.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'siivoon' => "clean - spoken form of 'siivoan'",
                            'lauantaina' => "on Saturday - lauantai + -na ('on')",
                        ],
                    ],
                    [
                        'fi' => 'Kissa nukkuu sohvalla.',
                        'en' => 'The cat is sleeping on the sofa.',
                        'glosses' => [
                            'kissa' => 'cat',
                            'nukkuu' => 'sleeps / is sleeping',
                            'sohvalla' => "on the sofa - sohva + -lla ('on')",
                        ],
                    ],
                    [
                        'fi' => 'Voitsä avata ikkunan?',
                        'en' => 'Can you open the window?',
                        'written' => 'Voitko sinä avata ikkunan?',
                        'glosses' => [
                            'voitsä' => "can you? - spoken 'voitko sinä' in one word",
                            'avata' => 'to open',
                            'ikkunan' => "the window - object form of 'ikkuna'",
                        ],
                    ],
                    [
                        'fi' => 'Sauna on valmis!',
                        'en' => 'The sauna is ready!',
                        'glosses' => [
                            'sauna' => "sauna - Finland's gift to the world",
                            'on' => 'is',
                            'valmis' => 'ready',
                        ],
                    ],
                    [
                        'fi' => 'Mennäänks saunaan?',
                        'en' => 'Shall we go to the sauna?',
                        'written' => 'Menemmekö saunaan?',
                        'glosses' => [
                            'mennäänks' => "shall we go? - spoken form of 'mennäänkö'",
                            'saunaan' => "into the sauna - sauna + -an ('into')",
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Weather and Seasons',
                'level' => 'A1',
                'sentences' => [
                    [
                        'fi' => 'Tänään sataa lunta.',
                        'en' => "It's snowing today.",
                        'glosses' => [
                            'tänään' => 'today',
                            'sataa' => 'rains / falls from the sky',
                            'lunta' => "snow - partitive of 'lumi'",
                        ],
                    ],
                    [
                        'fi' => 'Ulkona on tosi kylmä.',
                        'en' => "It's really cold outside.",
                        'written' => 'Ulkona on todella kylmä.',
                        'glosses' => [
                            'ulkona' => 'outside',
                            'on' => 'is',
                            'tosi' => "really - spoken intensifier (standard 'todella')",
                            'kylmä' => 'cold',
                        ],
                    ],
                    [
                        'fi' => 'Kesällä aurinko paistaa yölläkin.',
                        'en' => 'In summer the sun shines even at night.',
                        'glosses' => [
                            'kesällä' => "in summer - kesä + -llä ('in/at')",
                            'aurinko' => 'sun',
                            'paistaa' => 'shines',
                            'yölläkin' => "even at night - yö 'night' + -llä 'at' + -kin 'even'",
                        ],
                    ],
                    [
                        'fi' => 'Talvi on pitkä ja pimee.',
                        'en' => 'Winter is long and dark.',
                        'written' => 'Talvi on pitkä ja pimeä.',
                        'glosses' => [
                            'talvi' => 'winter',
                            'on' => 'is',
                            'pitkä' => 'long',
                            'ja' => 'and',
                            'pimee' => "dark - spoken form of 'pimeä'",
                        ],
                    ],
                    [
                        'fi' => 'Mä tykkään syksystä.',
                        'en' => 'I like autumn.',
                        'written' => 'Minä pidän syksystä.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'tykkään' => "like - 'tykätä' is THE spoken verb for liking (standard 'pitää')",
                            'syksystä' => "autumn - syksy + -stä ('tykätä' takes -sta/-stä)",
                        ],
                    ],
                    [
                        'fi' => 'Huomenna tulee myrsky.',
                        'en' => 'A storm is coming tomorrow.',
                        'glosses' => [
                            'huomenna' => 'tomorrow',
                            'tulee' => 'comes / is coming',
                            'myrsky' => 'storm',
                        ],
                    ],
                    [
                        'fi' => 'Ota sateenvarjo mukaan.',
                        'en' => 'Take an umbrella with you.',
                        'glosses' => [
                            'ota' => "take - imperative of 'ottaa'",
                            'sateenvarjo' => "umbrella - sade 'rain' + varjo 'shade'",
                            'mukaan' => 'along / with you',
                        ],
                    ],
                    [
                        'fi' => 'Järvi on jäässä.',
                        'en' => 'The lake is frozen.',
                        'glosses' => [
                            'järvi' => 'lake',
                            'on' => 'is',
                            'jäässä' => "frozen - literally 'in ice' (jää + -ssä)",
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Everyday Life',
                'level' => 'A1',
                'sentences' => [
                    [
                        'fi' => 'Mä herään seitsemältä.',
                        'en' => 'I wake up at seven.',
                        'written' => 'Minä herään kello seitsemän.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'herään' => "wake up - 1st person of 'herätä'",
                            'seitsemältä' => "at seven - seitsemän + -ltä ('at' for clock times)",
                        ],
                    ],
                    [
                        'fi' => 'Mä meen töihin bussilla.',
                        'en' => 'I go to work by bus.',
                        'written' => 'Minä menen töihin bussilla.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'meen' => "go - spoken form of 'menen'",
                            'töihin' => "to work - plural of 'työ' + -ihin ('to')",
                            'bussilla' => "by bus - bussi + -lla ('by/with')",
                        ],
                    ],
                    [
                        'fi' => 'Mä syön lounasta kaheltatoista.',
                        'en' => 'I eat lunch at twelve.',
                        'written' => 'Syön lounasta kello kaksitoista.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'syön' => "eat - 1st person of 'syödä'",
                            'lounasta' => "lunch - partitive of 'lounas'",
                            'kaheltatoista' => "at twelve - spoken form of 'kahdeltatoista'",
                        ],
                    ],
                    [
                        'fi' => 'Mä opiskelen suomee joka päivä.',
                        'en' => 'I study Finnish every day.',
                        'written' => 'Opiskelen suomea joka päivä.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'opiskelen' => "study - 1st person of 'opiskella'",
                            'suomee' => "Finnish - spoken form of 'suomea'",
                            'joka' => 'every',
                            'päivä' => 'day',
                        ],
                    ],
                    [
                        'fi' => 'Illalla mä katon telkkarii.',
                        'en' => 'In the evening I watch TV.',
                        'written' => 'Illalla katson televisiota.',
                        'glosses' => [
                            'illalla' => "in the evening - ilta + -lla ('in/at')",
                            'mä' => "I - spoken form of 'minä'",
                            'katon' => "watch - spoken form of 'katson'",
                            'telkkarii' => "TV - colloquial 'telkkari' (standard 'televisio')",
                        ],
                    ],
                    [
                        'fi' => 'Mä käyn kaupas töiden jälkeen.',
                        'en' => 'I stop by the store after work.',
                        'written' => 'Käyn kaupassa työn jälkeen.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'käyn' => "visit / stop by - 1st person of 'käydä'",
                            'kaupas' => "at the store - spoken form of 'kaupassa'",
                            'töiden' => "of work - plural genitive of 'työ'",
                            'jälkeen' => 'after',
                        ],
                    ],
                    [
                        'fi' => 'Viikonloppuna mä vaan lepään.',
                        'en' => 'On the weekend I just rest.',
                        'written' => 'Viikonloppuna minä vain lepään.',
                        'glosses' => [
                            'viikonloppuna' => "on the weekend - viikonloppu + -na ('on')",
                            'mä' => "I - spoken form of 'minä'",
                            'vaan' => "just - spoken form of 'vain'",
                            'lepään' => "rest - 1st person of 'levätä'",
                        ],
                    ],
                    [
                        'fi' => 'Mä meen nukkuu kymmeneltä.',
                        'en' => 'I go to sleep at ten.',
                        'written' => 'Minä menen nukkumaan kello kymmenen.',
                        'glosses' => [
                            'mä' => "I - spoken form of 'minä'",
                            'meen' => "go - spoken form of 'menen'",
                            'nukkuu' => "to sleep - spoken shortening of 'nukkumaan'",
                            'kymmeneltä' => "at ten - kymmenen + -ltä ('at' for clock times)",
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Sauna Small Talk - A Dialogue',
                'level' => 'A1',
                'sentences' => [
                    [
                        'fi' => 'Onks sauna jo lämmin?',
                        'en' => 'Is the sauna warm yet?',
                        'written' => 'Onko sauna jo lämmin?',
                        'speaker' => 'A',
                        'glosses' => [
                            'onks' => "is? - spoken form of 'onko'",
                            'sauna' => 'sauna',
                            'jo' => 'already / yet',
                            'lämmin' => 'warm',
                        ],
                    ],
                    [
                        'fi' => 'Joo, tosi kuuma. Mennään!',
                        'en' => "Yeah, really hot. Let's go!",
                        'written' => 'Kyllä, todella kuuma. Menkäämme!',
                        'speaker' => 'B',
                        'context' => 'Onks sauna jo lämmin?',
                        'glosses' => [
                            'joo' => "yeah - the everyday spoken 'yes'",
                            'tosi' => "really - spoken intensifier (standard 'todella')",
                            'kuuma' => 'hot',
                            'mennään' => "let's go - the passive used as 'we' in spoken Finnish",
                        ],
                    ],
                    [
                        'fi' => 'Heitätsä lisää löylyä?',
                        'en' => 'Will you throw more steam?',
                        'written' => 'Heitätkö sinä lisää löylyä?',
                        'speaker' => 'A',
                        'context' => 'Joo, tosi kuuma. Mennään!',
                        'glosses' => [
                            'heitätsä' => "will you throw? - spoken 'heitätkö sinä' in one word",
                            'lisää' => 'more',
                            'löylyä' => "steam - partitive of 'löyly', the sauna steam (untranslatable, sacred)",
                        ],
                    ],
                    [
                        'fi' => 'Joo, mut sano jos on liikaa.',
                        'en' => "Yeah, but say if it's too much.",
                        'written' => 'Kyllä, mutta sano jos on liikaa.',
                        'speaker' => 'B',
                        'context' => 'Heitätsä lisää löylyä?',
                        'glosses' => [
                            'joo' => "yeah - the everyday spoken 'yes'",
                            'mut' => "but - spoken form of 'mutta'",
                            'sano' => "say - imperative of 'sanoa'",
                            'jos' => 'if',
                            'on' => 'is',
                            'liikaa' => 'too much',
                        ],
                    ],
                    [
                        'fi' => 'Onpa hyvät löylyt!',
                        'en' => 'What a good steam!',
                        'speaker' => 'A',
                        'context' => 'Joo, mut sano jos on liikaa.',
                        'glosses' => [
                            'onpa' => "sure is! - on + -pa, the exclamation ending ('what a...')",
                            'hyvät' => "good - plural of 'hyvä'",
                            'löylyt' => "steam - 'löylyt' in the plural is how Finns praise it",
                        ],
                    ],
                    [
                        'fi' => 'Mennäänks kohta uimaan?',
                        'en' => 'Shall we go swimming soon?',
                        'written' => 'Menemmekö kohta uimaan?',
                        'speaker' => 'B',
                        'context' => 'Onpa hyvät löylyt!',
                        'glosses' => [
                            'mennäänks' => "shall we go? - spoken form of 'mennäänkö'",
                            'kohta' => 'soon / in a moment',
                            'uimaan' => "swimming - 'to swim' in the going-to form (uida + -maan)",
                        ],
                    ],
                    [
                        'fi' => 'Joo, järvi on ihan vieressä.',
                        'en' => 'Yeah, the lake is right next to us.',
                        'written' => 'Kyllä, järvi on aivan vieressä.',
                        'speaker' => 'A',
                        'context' => 'Mennäänks kohta uimaan?',
                        'glosses' => [
                            'joo' => "yeah - the everyday spoken 'yes'",
                            'järvi' => 'lake',
                            'on' => 'is',
                            'ihan' => 'right / quite',
                            'vieressä' => "next to (us) - vieri + -ssä ('beside')",
                        ],
                    ],
                    [
                        'fi' => 'Tää on paras päivä ikinä.',
                        'en' => 'This is the best day ever.',
                        'written' => 'Tämä on paras päivä ikinä.',
                        'speaker' => 'B',
                        'context' => 'Joo, järvi on ihan vieressä.',
                        'glosses' => [
                            'tää' => "this - spoken form of 'tämä'",
                            'on' => 'is',
                            'paras' => 'best',
                            'päivä' => 'day',
                            'ikinä' => 'ever',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($lessons as $index => $data) {
            $lesson = Lesson::create([
                'title' => $data['title'],
                'level' => $data['level'],
                'order_index' => $index + 1,
            ]);

            foreach ($data['sentences'] as $row) {
                $lesson->sentences()->create([
                    'finnish_text' => $row['fi'],
                    'english_text' => $row['en'],
                    'written_text' => $row['written'] ?? null,
                    'speaker' => $row['speaker'] ?? null,
                    'context_text' => $row['context'] ?? null,
                    'word_glosses' => $row['glosses'] ?? null,
                ]);
            }
        }
    }
}
