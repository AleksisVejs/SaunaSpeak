<?php

namespace Tests\Feature;

use App\Support\Listening;
use App\Support\Scenarios;
use App\Support\Themes;
use App\Support\Transforms;
use ReflectionClass;
use Tests\TestCase;

/**
 * The theme map (Themes::THEME_MAP) is hand-authored content: a lesson title
 * pointing at a listening scene, a transform set and a scenario by id. A typo
 * in any id degrades quietly to a level fallback - so this test fails loudly
 * instead, keeping the map honest as assets are renamed or retired.
 */
class ThemesMapTest extends TestCase
{
    /** @return array<string, array> the private THEME_MAP */
    private function map(): array
    {
        return (new ReflectionClass(Themes::class))->getConstant('THEME_MAP');
    }

    public function test_every_mapped_asset_id_exists(): void
    {
        $listening = array_column(Listening::index(), 'id');
        $transforms = array_column(Transforms::index(), 'id');
        $scenarios = Scenarios::ids();

        foreach ($this->map() as $title => $facets) {
            if (isset($facets['listening'])) {
                $this->assertContains($facets['listening'], $listening, "\"{$title}\" points at an unknown listening scene: {$facets['listening']}");
            }
            if (isset($facets['transform'])) {
                $this->assertContains($facets['transform'], $transforms, "\"{$title}\" points at an unknown transform set: {$facets['transform']}");
            }
            if (isset($facets['scenario'])) {
                $this->assertContains($facets['scenario'], $scenarios, "\"{$title}\" points at an unknown scenario: {$facets['scenario']}");
            }
        }
    }
}
