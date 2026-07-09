<?php

namespace Database\Seeders;

use App\Models\Sentence;
use Illuminate\Database\Seeder;

/**
 * Dual coding: pairs every sentence with a simple OpenMoji illustration
 * (CC BY-SA 4.0 — https://openmoji.org). SVGs live in public/images/{hex}.svg;
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
        // Sauna Small Talk — A Dialogue
        'Onks sauna jo lämmin?' => '1F321',
        'Joo, tosi kuuma. Mennään!' => '1F525',
        'Heitätsä lisää löylyä?' => '1F4A6',
        'Joo, mut sano jos on liikaa.' => '270B',
        'Onpa hyvät löylyt!' => '2668',
        'Mennäänks kohta uimaan?' => '1F3CA',
        'Joo, järvi on ihan vieressä.' => '1F30A',
        'Tää on paras päivä ikinä.' => '1F929',
    ];

    public function run(): void
    {
        foreach (self::MAP as $text => $hex) {
            // Only link images whose SVG has actually been downloaded.
            if (! file_exists(public_path("images/{$hex}.svg"))) {
                continue;
            }

            Sentence::where('finnish_text', $text)->update(['image_url' => "/images/{$hex}.svg"]);
        }
    }
}
