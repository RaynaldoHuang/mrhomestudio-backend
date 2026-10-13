<?php

namespace Tests\Feature\Api;

use App\Models\Carousel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CarouselControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_active_carousels_in_display_order(): void
    {
        Carousel::factory()->create(['title' => 'Second slide', 'sort_order' => 20]);
        Carousel::factory()->create(['title' => 'First slide', 'sort_order' => 10]);
        Carousel::factory()->inactive()->create(['title' => 'Hidden slide', 'sort_order' => 1]);

        $this->getJson('/api/carousels')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'First slide')
            ->assertJsonPath('data.1.title', 'Second slide')
            ->assertJsonMissing(['title' => 'Hidden slide']);
    }
}
