<?php

namespace Tests\Feature;

use App\Support\MinimalPairs;
use Tests\TestCase;

class MinimalPairsTest extends TestCase
{
    public function test_every_set_loads_with_a_contrast_and_pairs(): void
    {
        $sets = MinimalPairs::index();

        $this->assertNotEmpty($sets);

        foreach ($sets as $set) {
            $this->assertCount(2, $set['contrast'], "{$set['id']} needs exactly two vowels");
            $this->assertGreaterThan(0, $set['pairs_count']);
        }
    }

    /**
     * The reason wordBase() hashes. Str::slug flattens ä to a, so these two
     * would share a filename and one would overwrite the other - destroying
     * the only distinction the drill exists to teach.
     */
    public function test_words_that_differ_only_by_an_umlaut_get_different_filenames(): void
    {
        foreach ([['sää', 'saa'], ['jää', 'jaa'], ['tähti', 'tahti'], ['söi', 'soi']] as [$front, $back]) {
            $this->assertNotSame(
                MinimalPairs::wordBase($front),
                MinimalPairs::wordBase($back),
                "{$front} and {$back} must not collide",
            );
        }
    }

    public function test_both_halves_of_every_pair_are_collected_for_recording(): void
    {
        $words = MinimalPairs::words();

        foreach (MinimalPairs::all() as $set) {
            foreach ($set['pairs'] as $pair) {
                $this->assertArrayHasKey(mb_strtolower($pair['a']), $words);
                $this->assertArrayHasKey(mb_strtolower($pair['b']), $words);
            }
        }
    }

    public function test_pairs_actually_differ_only_in_the_sets_contrast(): void
    {
        foreach (MinimalPairs::all() as $set) {
            [$front, $back] = $set['contrast'];

            foreach ($set['pairs'] as $pair) {
                $this->assertSame(
                    str_replace($front, $back, mb_strtolower($pair['a'])),
                    mb_strtolower($pair['b']),
                    "{$pair['a']}/{$pair['b']} in {$set['id']} differ by more than {$front}/{$back}",
                );
            }
        }
    }

    public function test_a_word_with_a_generated_clip_resolves_to_a_url(): void
    {
        $sets = MinimalPairs::all();
        $pair = $sets[0]['pairs'][0];

        // Only meaningful once pairs:audio has run; skip rather than fail on
        // a machine without the generated clips.
        if ($pair['a_audio'] === null) {
            $this->markTestSkipped('run `php artisan pairs:audio` first');
        }

        $this->assertStringStartsWith('/audio/', $pair['a_audio']);
        $this->assertStringEndsWith('.mp3', $pair['a_audio']);
        $this->assertNotSame($pair['a_audio'], $pair['b_audio']);
    }

    /**
     * Fails the day someone flips a set to verified, which is the prompt to
     * delete this test and let the drill ship.
     */
    public function test_content_is_still_awaiting_native_review(): void
    {
        $this->assertFalse(
            MinimalPairs::allVerified(),
            'A set is marked verified - has a native speaker actually checked the words and glosses?',
        );
    }
}
