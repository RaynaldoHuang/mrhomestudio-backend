<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\MegaMenu;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MenuDisplayModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function authenticate(): void
    {
        User::factory()->create(['role' => 'super_admin', 'api_token' => hash('sha256', 'display-mode-token'), 'api_token_expires_at' => now()->addHour()]);
        $this->withToken('display-mode-token');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['label' => 'Amplifiers', 'href' => '/collection/amplifiers', 'display_mode' => 'direct', 'sort_order' => 0, 'is_active' => true, 'groups' => [[
            'links' => [
                ['label' => 'Pre-amplifiers', 'href' => '/collection/pre-amplifiers'],
                ['label' => 'Integrated amplifiers', 'href' => '/collection/integrated-amplifiers'],
            ],
        ]]];
    }

    public function test_direct_links_create_categories_without_requiring_group_titles(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/admin/mega-menus', $this->payload())->assertCreated()
            ->assertJsonPath('data.display_mode', 'direct')->assertJsonPath('data.groups.0.title', '');

        $pre = Category::query()->where('name', 'Pre-amplifiers')->firstOrFail();
        $response->assertJsonPath('data.groups.0.links.0.category_id', $pre->id);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseHas('mega_menus', ['id' => $response->json('data.id'), 'display_mode' => 'direct']);
        $this->getJson('/api/admin/categories')->assertOk()->assertJsonPath('data.0.menu_groups', ['Amplifiers']);
    }

    public function test_direct_category_links_and_view_all_filter_products_by_identity(): void
    {
        $this->authenticate();
        $this->postJson('/api/admin/mega-menus', $this->payload())->assertCreated();
        $pre = Category::query()->where('name', 'Pre-amplifiers')->firstOrFail();
        $integrated = Category::query()->where('name', 'Integrated amplifiers')->firstOrFail();
        $first = Product::factory()->create(['category_id' => $pre->id]);
        $second = Product::factory()->create(['category_id' => $integrated->id]);
        Product::factory()->create();

        $this->getJson('/api/products?collection=amplifiers')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $first->id])->assertJsonFragment(['id' => $second->id]);
        $this->getJson('/api/products?collection=pre-amplifiers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
    }

    public function test_grouped_default_requires_group_titles(): void
    {
        $this->authenticate();
        $payload = $this->payload();
        unset($payload['display_mode']);

        $this->postJson('/api/admin/mega-menus', $payload)->assertUnprocessable()->assertJsonValidationErrors('groups.0.title');
        $this->assertDatabaseCount('mega_menus', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_unknown_display_mode_is_rejected(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/mega-menus', [...$this->payload(), 'display_mode' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors('display_mode');
        $this->assertDatabaseCount('mega_menus', 0);
    }

    public function test_switching_to_direct_preserves_groups_categories_and_products(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['name' => 'Pre-amplifiers', 'slug' => 'pre-amplifiers']);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $payload = $this->payload();
        $payload['groups'][0]['title'] = 'By type';
        $payload['groups'][0]['links'] = [$payload['groups'][0]['links'][0] + ['category_id' => $category->id]];
        $menu = MegaMenu::factory()->create(['groups' => $payload['groups']]);

        $this->putJson("/api/admin/mega-menus/{$menu->id}", $payload)->assertOk()
            ->assertJsonPath('data.display_mode', 'direct')->assertJsonPath('data.groups.0.title', 'By type')
            ->assertJsonPath('data.groups.0.links.0.category_id', $category->id);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_omitted_mode_on_update_uses_saved_direct_mode(): void
    {
        $this->authenticate();
        $menu = MegaMenu::factory()->create(['display_mode' => 'direct']);
        $payload = $this->payload();
        unset($payload['display_mode']);

        $this->putJson("/api/admin/mega-menus/{$menu->id}", $payload)->assertOk()
            ->assertJsonPath('data.display_mode', 'direct')->assertJsonPath('data.groups.0.title', '');
    }

    public function test_switching_back_to_grouped_requires_titles_without_changing_saved_mode(): void
    {
        $this->authenticate();
        $menu = MegaMenu::factory()->create(['display_mode' => 'direct']);

        $this->putJson("/api/admin/mega-menus/{$menu->id}", [...$this->payload(), 'display_mode' => 'grouped'])
            ->assertUnprocessable()->assertJsonValidationErrors('groups.0.title');
        $this->assertDatabaseHas('mega_menus', ['id' => $menu->id, 'display_mode' => 'direct']);
    }

    public function test_public_api_returns_display_mode_and_only_actual_promotion_images(): void
    {
        MegaMenu::factory()->create(['display_mode' => 'direct', 'featured_image_path' => null, 'sort_order' => 1]);
        MegaMenu::factory()->create(['featured_image_path' => 'mega-menus/banner.webp', 'sort_order' => 2]);

        $this->getJson('/api/mega-menus')->assertOk()
            ->assertJsonPath('data.0.display_mode', 'direct')->assertJsonPath('data.0.featured_image_url', null)
            ->assertJsonPath('data.1.display_mode', 'grouped')
            ->assertJsonPath('data.1.featured_image_url', 'http://localhost:8000/storage/mega-menus/banner.webp');
    }
}
