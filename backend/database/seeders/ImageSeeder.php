<?php

namespace Database\Seeders;

use App\Models\Sentence;
use Illuminate\Database\Seeder;

/**
 * Dual coding: pairs every sentence with a simple OpenMoji illustration
 * (CC BY-SA 4.0 - https://openmoji.org). SVGs live in public/images/{hex}.svg;
 * download them with:
 *
 *   php artisan images:fetch
 *
 * Keyed by finnish_text so it can run independently of LessonSeeder ids.
 */
class ImageSeeder extends Seeder
{
    /** finnish_text → OpenMoji hex code. */
    public const MAP = [
        // First Words in the Sauna
        'Moi! Mä oon Anna.' => '1F44B',
        'Mikä sun nimi on?' => '1FAAA',
        'Hauska tavata!' => '1F91D',
        'Mitä kuuluu?' => '1F60A',
        'Ihan hyvää, kiitos.' => '1F44D',
        'Anteeks, mä en ymmärrä.' => '1F615',
        'Puhuksä englantia?' => '1F1EC-1F1E7',
        'Moikka, nähään!' => '1F44B',
        // Spoken Finnish Survival Kit
        'No niin, mennään!' => '1F6B6',
        'Emmä tiiä.' => '1F937',
        'Mitä sä teet?' => '2753',
        'Ei se mitään.' => '1F60C',
        'Ihan sama.' => '1F610',
        'Onks sul nälkä?' => '1F37D',
        'Joo, sopii mulle.' => '1F44C',
        'Kato, toi on mun kaveri.' => '1F465',
        // Coffee and Pulla
        'Mä otan kahvin, kiitos.' => '2615',
        'Saisinks mä yhen pullan?' => '1F9C1',
        'Paljonks toi maksaa?' => '1F4B0',
        'Se maksaa kolme euroo.' => '1F4B6',
        'Varo, kahvi on tosi kuumaa.' => '1F975',
        'Tää on tosi hyvää!' => '1F60B',
        'Voinks mä maksaa kortilla?' => '1F4B3',
        'Ole hyvä.' => '1FAF4',
        // Finding Your Way
        'Mis rautatieasema on?' => '1F689',
        'Se on tuol.' => '1F449',
        'Käänny vasemmalle.' => '2B05',
        'Käänny oikeelle.' => '27A1',
        'Mee suoraan eteenpäin.' => '2B06',
        'Onks se kaukana?' => '1F5FA',
        'Ei oo, se on ihan lähellä.' => '1F4CD',
        'Kiitti avusta!' => '1F64F',
        // At Home
        'Mä asun Helsingissä.' => '1F3D9',
        'Meil on pieni kämppä.' => '1F3E0',
        'Keittiössä on jääkaappi.' => '1F373',
        'Mä siivoon lauantaina.' => '1F9F9',
        'Kissa nukkuu sohvalla.' => '1F431',
        'Voitsä avata ikkunan?' => '1FA9F',
        'Sauna on valmis!' => '1F9D6',
        'Mennäänks saunaan?' => '2668',
        // Weather and Seasons
        'Tänään sataa lunta.' => '1F328',
        'Ulkona on tosi kylmä.' => '1F976',
        'Kesällä aurinko paistaa yölläkin.' => '2600',
        'Talvi on pitkä ja pimee.' => '2744',
        'Mä tykkään syksystä.' => '1F342',
        'Huomenna tulee myrsky.' => '26C8',
        'Ota sateenvarjo mukaan.' => '2614',
        'Järvi on jäässä.' => '1F9CA',
        // Everyday Life
        'Mä herään seitsemältä.' => '23F0',
        'Mä meen töihin bussilla.' => '1F68C',
        'Mä syön lounasta kaheltatoista.' => '1F372',
        'Mä opiskelen suomee joka päivä.' => '1F4DA',
        'Illalla mä katon telkkarii.' => '1F4FA',
        'Mä käyn kaupas töiden jälkeen.' => '1F6D2',
        'Viikonloppuna mä vaan lepään.' => '1F6CC',
        'Mä meen nukkuu kymmeneltä.' => '1F634',
        // Sauna Small Talk - A Dialogue
        'Onks sauna jo lämmin?' => '1F321',
        'Joo, tosi kuuma. Mennään!' => '1F525',
        'Heitätsä lisää löylyä?' => '1F4A6',
        'Joo, mut sano jos on liikaa.' => '270B',
        'Onpa hyvät löylyt!' => '2668',
        'Mennäänks kohta uimaan?' => '1F3CA',
        'Joo, järvi on ihan vieressä.' => '1F30A',
        'Tää on paras päivä ikinä.' => '1F929',
    ];

    /** finnish_text → OpenMoji hex for the expansion lessons (9–16). */
    public const EXPANSION_MAP = [
        // At the Shop
        'Mä etsin maitoo.' => '1F95B',
        'Mis teil on leipä?' => '1F35E',
        'Onks teil tätä isompana?' => '1F4CF',
        'Mä vaan katselen, kiitti.' => '1F440',
        'Otatsä muovipussin?' => '1F6CD',
        'Ei kiitos, mul on oma kassi.' => '1F9FA',
        'Missä kassa on?' => '1F4B5',
        'Saanks mä kuitin?' => '1F9FE',
        // How Are You Feeling?
        'Mul on huono olo.' => '1F912',
        'Mun päätä särkee.' => '1F915',
        'Mua väsyttää tosi paljon.' => '1F62A',
        'Onks sul kuumetta?' => '1F321',
        'Mä meen apteekkiin.' => '1F3E5',
        'Ota tää lääke ruoan kanssa.' => '1F48A',
        'Parane pian!' => '1F33B',
        'Mun pitäis levätä.' => '1F6CC',
        // On the Phone
        'Mä soitan sulle illalla.' => '1F4DE',
        'Laita mulle viesti.' => '1F4AC',
        'Anteeks, mä en kuule sua.' => '1F649',
        'Mun akku on melkein loppu.' => '1FAAB',
        'Voitsä soittaa mulle takasin?' => '1F4F2',
        'Mikä sun numero on?' => '1F522',
        'Mä lähetän sen sulle WhatsAppis.' => '1F4F1',
        'Puhutaan myöhemmin!' => '1F5E3',
        // Making Plans
        'Mitä sä teet viikonloppuna?' => '1F4C5',
        'Nähäänks huomenna?' => '1F91D',
        'Sopiiks kuudelta?' => '1F555',
        'Mul ei käy maanantaina.' => '274C',
        'Mennään leffaan!' => '1F3AC',
        'Mä oon vähän myöhässä.' => '23F3',
        'Ei haittaa, mä odotan.' => '231B',
        'Nähään sit siel!' => '1F4CD',
        // What Do You Think?
        'Mun mielestä tää on hyvä idea.' => '1F4A1',
        'Mä oon samaa mieltä.' => '1F44D',
        'Emmä oo ihan varma.' => '1F937',
        'Toi on musta outoo.' => '1F928',
        'Mikä sun lempiruoka on?' => '1F35C',
        'Mä tykkään tästä tosi paljon.' => '1F60D',
        'Se riippuu säästä.' => '1F326',
        'Ihan hullu juttu!' => '1F92A',
        // Last Weekend
        'Mä olin eilen saunassa.' => '1F9D6',
        'Me mentiin mökille.' => '1F3E1',
        'Mä söin liikaa pizzaa.' => '1F355',
        'Nukuitsä hyvin?' => '1F634',
        'Se oli tosi kivaa.' => '1F389',
        'Mä näin sut eilen kaupungilla.' => '1F3D9',
        'Mitä sä teit viikonloppuna?' => '2753',
        'En tehny yhtään mitään.' => '1F9A5',
        // At Work
        'Mä oon töissä kahteen asti.' => '1F4BC',
        'Meil on kokous kymmeneltä.' => '1F4CB',
        'Mun täytyy tehä tää loppuun.' => '2705',
        'Voitsä auttaa mua tän kanssa?' => '1F198',
        'Mä pidän tauon.' => '2615',
        'Lähetä se mulle sähköpostilla.' => '1F4E7',
        'Mun pomo on ihan jees.' => '1F60E',
        'Mä lopetan tänään aikasin.' => '1F3C3',
        // Telling Stories
        'Eka mä heräsin, sit mä join kahvii.' => '1F305',
        'Mä myöhästyin, ku bussi ei tullu.' => '1F68C',
        'Me lähettiin, vaikka satoi.' => '1F327',
        'Ja sit kaikki alko nauraa.' => '1F602',
        'Arvaa mitä tapahtu!' => '1F632',
        'Ei voi olla totta!' => '1F631',
        'No siis, se oli aika mahtavaa.' => '1F929',
        'Pitkä juttu, mä kerron saunassa.' => '2668',
    ];

    /** JSON lessons 03-10 (database/lessons/), same finnish_text keying. */
    public const JSON_LESSON_MAP = [
        // Numbers, Prices and Time
        'Paljon tää maksaa?' => '1F3F7',
        'Se on viis euroo.' => '1F4B6',
        'Mä herään seiskalta.' => '23F0',
        'Kello on puol viis.' => '1F55F',
        'Nähään vartin päästä.' => '23F3',
        'Bussi lähtee kahelta.' => '1F68C',
        'Mä tuun kotiin kuudelta.' => '1F3E1',
        'Sata euroo?! Ei oo totta.' => '1F632',
        // Family and Friends
        'Tää on mun äiti.' => '1F469',
        'Mun isä on viel töissä.' => '1F468',
        'Onks sul sisaruksia?' => '1F46A',
        'Mul on kaks veljee.' => '1F46C',
        'Toi on mun paras kaveri.' => '1F917',
        'Mun mummo soittaa mulle joka päivä.' => '1F475',
        'Me ollaan serkkuja.' => '1F46B',
        'Tuutsä meille kylään?' => '1F6AA',
        // At the Pharmacy
        'Mä oon kipee.' => '1F912',
        'Mun kurkku on tosi kipee.' => '1F637',
        'Tarviinks mä reseptin?' => '1F4C4',
        'Onks teil mitään flunssaan?' => '1F48A',
        'Ota näitä kaks kertaa päivässä.' => '1F4C5',
        'Mul on kuumetta.' => '1F321',
        'Mä varaan ajan lääkärille.' => '1FA7A',
        'Mee kotiin lepäämään.' => '1F634',
        // A Night Out
        'Lähetsä illalla ulos?' => '1F303',
        'Otetaanks yhet?' => '1F37B',
        'Mä tuun sinne ysiltä.' => '1F558',
        'Tää biisi on ihan sika hyvä!' => '1F3B6',
        'Haluutsä jotain juotavaa?' => '1F379',
        'Mennään tanssimaan!' => '1F57A',
        'Mun pitää mennä himaan.' => '1F6B6',
        'Eilen meni vähän myöhään.' => '1F971',
        // Weekend at the Mökki
        'Lähetään viikonloppuna mökille.' => '1F697',
        'Mökil ei oo nettii.' => '1F4F5',
        'Lämmitetäänks sauna?' => '1F525',
        'Heitä lisää löylyä!' => '1F4A6',
        'Mä käyn uimassa.' => '1F3CA',
        'Grillataanks makkaraa?' => '1F32D',
        'Järvi on viel tosi kylmä.' => '1F976',
        'Tääl on niin rauhallista.' => '1F332',
        // When Something Breaks
        'Tää puhelin on rikki.' => '1F4F1',
        'Se lakkas toimimasta eilen.' => '26A0',
        'Voinks mä palauttaa tän?' => '1F4E6',
        'Onks sul kuitti mukana?' => '1F9FE',
        'Joo, se on tässä.' => '1F44D',
        'Saanks mä rahat takas?' => '1F4B8',
        'Me vaihetaan se uuteen.' => '1F504',
        'Hyvää päivänjatkoa!' => '1F31E',
        // Renting a Place
        'Mä etin uut kämppää.' => '1F50D',
        'Paljon vuokra on kuussa?' => '1F4B0',
        'Millon sen vois tulla kattomaan?' => '1F440',
        'Vesi kuuluu vuokraan.' => '1F6BF',
        'Onks se kalustettu?' => '1F6CB',
        'Sopiiks, et mul on kissa?' => '1F431',
        'Mä otan sen.' => '1F511',
        'Vuokrasopimus allekirjotetaan huomenna.' => '1F4DD',
        // Maybe, Probably, I Guess
        'Se on varmaan jo kotona.' => '1F914',
        'Emmä kyl usko.' => '1F928',
        'Kai se onnistuu.' => '1F91E',
        'Bussi tais just mennä.' => '1F4A8',
        'Ehkä me tullaan myöhemmin.' => '1F937',
        'Mä luulin, et se on tänään.' => '1F605',
        'Nii kai sit.' => '1F644',
        'Kattotaan nyt eka, miten käy.' => '1F52E',
    ];

    /** Every sentence → hex mapping, original, expansion and JSON lessons combined. */
    public static function all(): array
    {
        return self::MAP + self::EXPANSION_MAP + self::JSON_LESSON_MAP;
    }

    public function run(): void
    {
        foreach (self::all() as $text => $hex) {
            // Only link images whose SVG has actually been downloaded.
            if (! file_exists(public_path("images/{$hex}.svg"))) {
                continue;
            }

            Sentence::where('finnish_text', $text)->update(['image_url' => "/images/{$hex}.svg"]);
        }
    }
}
