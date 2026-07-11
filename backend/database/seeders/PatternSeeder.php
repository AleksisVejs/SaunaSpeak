<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Pattern;
use Illuminate\Database\Seeder;

/**
 * Short, teachable rules behind the parts of Finnish learners struggle with most.
 * Each is attached to the lesson (by order_index) where its examples first appear,
 * so a "Why this works" note shows up exactly in context.
 */
class PatternSeeder extends Seeder
{
    public function run(): void
    {
        $patterns = [
            // Lesson 1 - First Words in the Sauna
            'lesson_1' => [
                'title' => 'Spoken pronouns: minä → mä',
                'summary' => 'In everyday speech the pronouns shrink. You almost never hear the "book" forms in conversation - learn the short ones first.',
                'examples' => [
                    ['fi' => 'mä', 'en' => 'I', 'note' => 'kirjakieli: minä'],
                    ['fi' => 'sä', 'en' => 'you', 'note' => 'kirjakieli: sinä'],
                    ['fi' => 'sun', 'en' => 'your', 'note' => 'kirjakieli: sinun'],
                    ['fi' => 'mä oon', 'en' => "I am", 'note' => 'kirjakieli: minä olen'],
                ],
            ],
            // Lesson 2 - Spoken Finnish Survival Kit
            'lesson_2' => [
                'title' => 'Asking casually: -ko/-kö → -ks',
                'summary' => 'To turn a statement into a yes/no question, standard Finnish adds -ko/-kö. In speech this becomes a quick -ks on the verb.',
                'examples' => [
                    ['fi' => 'Onks sul nälkä?', 'en' => 'Are you hungry?', 'note' => 'kirjakieli: Onko sinulla nälkä?'],
                    ['fi' => 'Otetaanks kahvit?', 'en' => 'Shall we get coffee?', 'note' => 'kirjakieli: Otetaanko kahvit?'],
                    ['fi' => 'Tiiäks sä?', 'en' => 'Do you know?', 'note' => 'kirjakieli: Tiedätkö sinä?'],
                ],
            ],
            // Lesson 3 - Coffee and Pulla
            'lesson_3' => [
                'title' => 'The partitive: a bit of something',
                'summary' => 'Finnish marks "some / an amount of" with the partitive ending (usually -a/-ä or -ta/-tä). Use it for drinks, food and uncountable things.',
                'examples' => [
                    ['fi' => 'kahvia', 'en' => '(some) coffee', 'note' => 'from kahvi'],
                    ['fi' => 'vettä', 'en' => '(some) water', 'note' => 'from vesi'],
                    ['fi' => 'Saanks mä pullaa?', 'en' => 'Can I have a bun?', 'note' => 'pulla → pullaa'],
                ],
            ],
            // Lesson 4 - Finding Your Way
            'lesson_4' => [
                'title' => 'Place endings: in / from / to',
                'summary' => 'Finnish has no words for "in", "from" or "to" - the ending on the place word carries it. -ssa = in, -sta = from, -lle/-Vn = to.',
                'examples' => [
                    ['fi' => 'kaupassa', 'en' => 'in the shop', 'note' => 'kauppa + -ssa'],
                    ['fi' => 'kaupasta', 'en' => 'from the shop', 'note' => 'kauppa + -sta'],
                    ['fi' => 'Mis rautatieasema on?', 'en' => 'Where is the station?', 'note' => 'missä → mis in speech'],
                ],
            ],
            // Lesson 5 - At Home
            'lesson_5' => [
                'title' => 'Consonant gradation: kk → k',
                'summary' => 'When you add an ending that closes the syllable, a strong double consonant often weakens: kk→k, pp→p, tt→t. It is regular, not random.',
                'examples' => [
                    ['fi' => 'kauppa → kaupassa', 'en' => 'shop → in the shop', 'note' => 'kk → p... note the k weakens'],
                    ['fi' => 'kämppä → kämpässä', 'en' => 'flat → in the flat', 'note' => 'pp → p'],
                    ['fi' => 'matto → maton', 'en' => 'rug → the rug\'s', 'note' => 'tt → t'],
                ],
            ],
        ];

        $lessonByOrder = Lesson::orderBy('order_index')->get()->keyBy('order_index');

        $order = 1;
        foreach ($patterns as $key => $data) {
            $lessonOrder = (int) str_replace('lesson_', '', $key);
            $pattern = Pattern::create([
                'title' => $data['title'],
                'summary' => $data['summary'],
                'examples' => $data['examples'],
                'order_index' => $order++,
            ]);

            $lesson = $lessonByOrder->get($lessonOrder);
            if ($lesson) {
                $lesson->update(['pattern_id' => $pattern->id]);
            }
        }
    }
}
