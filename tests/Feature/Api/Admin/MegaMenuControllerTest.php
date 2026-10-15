<?php

namespace Tests\Feature\Api\Admin;

use App\Models\MegaMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MegaMenuControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $token = 'mega-menu-admin-token';
        User::factory()->create([
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addHour(),
        ]);
        $this->withToken($token);
    }

    public function test_index_includes_active_and_inactive_menus(): void
    {
        MegaMenu::factory()->create(['label' => 'Active menu']);
        MegaMenu::factory()->inactive()->create(['label' => 'Inactive menu']);

        $this->getJson('/api/admin/mega-menus')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Active menu'])
            ->assertJsonFragment(['label' => 'Inactive menu']);
    }

    public function test_admin_can_create_menu_with_featured_image(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/admin/mega-menus', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('speakers.jpg', 3000, 1500),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.label', 'Speakers');
        $menu = MegaMenu::query()->where('label', 'Speakers')->firstOrFail();
        Storage::disk('public')->assertExists($menu->featured_image_path);
        $this->assertStringEndsWith('.webp', $menu->featured_image_path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($menu->featured_image_path))['mime']);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($menu->featured_image_path));
        $this->assertSame([2400, 1200], [$dimensions[0], $dimensions[1]]);
    }

    public function test_invalid_nested_links_are_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['groups'][0]['links'][0]['href'] = '';

        $this->postJson('/api/admin/mega-menus', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('groups.0.links.0.href');
    }

    public function test_destroy_removes_menu_and_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('mega-menus/menu.jpg', 'image');
        $menu = MegaMenu::factory()->create(['featured_image_path' => 'mega-menus/menu.jpg']);

        $this->deleteJson("/api/admin/mega-menus/{$menu->id}")->assertNoContent();

        $this->assertModelMissing($menu);
        Storage::disk('public')->assertMissing('mega-menus/menu.jpg');
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'label' => 'Speakers',
            'href' => '/collection/speakers',
            'groups' => [[
                'title' => 'By Type',
                'links' => [[
                    'label' => 'Bookshelf',
                    'href' => '/collection/bookshelf',
                ]],
            ]],
            'featured_title' => 'Premium speakers',
            'featured_url' => '/collection/speakers',
            'featured_image_alt' => 'Speakers in a living room',
            'sort_order' => 1,
            'is_active' => '1',
        ];
    }
}
