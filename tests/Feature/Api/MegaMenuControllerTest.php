<?php

namespace Tests\Feature\Api;

use App\Models\MegaMenu;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MegaMenuControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_active_menus_in_display_order(): void
    {
        MegaMenu::factory()->create(['label' => 'Second', 'sort_order' => 20]);
        MegaMenu::factory()->create(['label' => 'First', 'sort_order' => 10]);
        MegaMenu::factory()->inactive()->create(['label' => 'Hidden', 'sort_order' => 1]);

        $this->getJson('/api/mega-menus')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'First')
            ->assertJsonPath('data.1.label', 'Second')
            ->assertJsonMissing(['label' => 'Hidden']);
    }
}
