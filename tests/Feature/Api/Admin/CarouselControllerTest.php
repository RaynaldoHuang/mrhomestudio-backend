<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Carousel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CarouselControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $token = 'carousel-admin-test-token';
        User::factory()->create([
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addHour(),
        ]);
        $this->withToken($token);
    }

    public function test_index_returns_active_and_inactive_carousels(): void
    {
        Carousel::factory()->create(['title' => 'Active slide']);
        Carousel::factory()->inactive()->create(['title' => 'Inactive slide']);

        $this->getJson('/api/admin/carousels')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Active slide'])
            ->assertJsonFragment(['title' => 'Inactive slide']);
    }

    public function test_valid_payload_creates_carousel_and_returns_201(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/admin/carousels', [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->image('cinema.jpg', 3000, 1500),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cinema at home');
        $this->assertDatabaseHas('carousels', [
            'title' => 'Cinema at home',
            'is_active' => true,
        ]);

        $carousel = Carousel::query()->where('title', 'Cinema at home')->firstOrFail();
        Storage::disk('public')->assertExists($carousel->image_path);
        $this->assertStringEndsWith('.webp', $carousel->image_path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($carousel->image_path))['mime']);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($carousel->image_path));
        $this->assertSame([2400, 1200], [$dimensions[0], $dimensions[1]]);
    }

    public function test_missing_required_fields_returns_422_without_creating_carousel(): void
    {
        $this->postJson('/api/admin/carousels', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'cta_label',
                'cta_url',
                'image_alt',
                'image_position',
                'sort_order',
                'is_active',
            ]);

        $this->assertDatabaseCount('carousels', 0);
    }

    public function test_update_replaces_the_previous_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('carousels/old.jpg', 'old image');
        $carousel = Carousel::factory()->create(['image_path' => 'carousels/old.jpg']);

        $response = $this->post("/api/admin/carousels/{$carousel->id}", [
            ...$this->validPayload(),
            '_method' => 'PUT',
            'image' => UploadedFile::fake()->image('new.jpg', 1600, 900),
        ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Cinema at home');
        Storage::disk('public')->assertMissing('carousels/old.jpg');
        Storage::disk('public')->assertExists($carousel->refresh()->image_path);
        $this->assertStringEndsWith('.webp', $carousel->image_path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($carousel->image_path))['mime']);
    }

    public function test_destroy_removes_carousel_and_its_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('carousels/slide.jpg', 'image');
        $carousel = Carousel::factory()->create(['image_path' => 'carousels/slide.jpg']);

        $this->deleteJson("/api/admin/carousels/{$carousel->id}")
            ->assertNoContent();

        $this->assertModelMissing($carousel);
        Storage::disk('public')->assertMissing('carousels/slide.jpg');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'eyebrow' => '#homecinema',
            'title' => 'Cinema at home',
            'cta_label' => 'Explore products',
            'cta_url' => '/services',
            'image_alt' => 'A luxury home cinema',
            'image_position' => 'object-center',
            'sort_order' => 1,
            'is_active' => '1',
        ];
    }
}
