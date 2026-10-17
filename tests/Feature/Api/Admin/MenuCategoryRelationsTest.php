<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\MegaMenu;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\MegaMenuSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MenuCategoryRelationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_menu_creates_categories_and_normalizes_collection_urls(): void
    {
        $this->authenticate();
        $payload = $this->payload();
        $payload['groups'][0]['links'][] = ['label' => 'About', 'href' => '/about'];
        $payload['groups'][0]['links'][] = ['label' => 'All Products', 'href' => '/collection/all-products'];
        $payload['groups'][0]['links'][] = ['label' => 'External', 'href' => 'https://example.com/collection/external'];

        $response = $this->postJson('/api/admin/mega-menus', $payload)->assertCreated()
            ->assertJsonPath('data.groups.0.links.0.href', '/collection/cd-players')
            ->assertJsonPath('data.groups.0.links.1.href', '/about');

        $category = Category::query()->sole();
        $response->assertJsonPath('data.groups.0.links.0.category_id', $category->id);
        $this->assertDatabaseHas('categories', ['name' => 'CD Players', 'slug' => 'cd-players', 'image_path' => null, 'show_on_home' => false]);
        $this->assertSame($category->id, MegaMenu::query()->sole()->groups[0]['links'][0]['category_id']);
    }

    public function test_existing_category_is_reused_by_name_and_keeps_its_canonical_slug(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['name' => 'CD Players', 'slug' => 'compact-disc', 'is_active' => false]);

        $this->postJson('/api/admin/mega-menus', $this->payload())->assertCreated()
            ->assertJsonPath('data.groups.0.links.0.category_id', $category->id)
            ->assertJsonPath('data.groups.0.links.0.href', '/collection/compact-disc');

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
    }

    public function test_renaming_link_preserves_category_and_products_when_id_is_supplied(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['name' => 'CD Players', 'slug' => 'cd-players']);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $menu = MegaMenu::factory()->create();
        $payload = $this->payload();
        $payload['groups'][0]['links'][0] = ['label' => 'Our CD Players', 'href' => '/collection/renamed-url', 'category_id' => $category->id];

        $this->putJson("/api/admin/mega-menus/{$menu->id}", $payload)->assertOk()
            ->assertJsonPath('data.groups.0.links.0.label', 'Our CD Players')
            ->assertJsonPath('data.groups.0.links.0.href', '/collection/cd-players')
            ->assertJsonPath('data.groups.0.links.0.category_id', $category->id);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Our CD Players']);
    }

    public function test_missing_category_id_returns_422_without_saving_menu_or_category(): void
    {
        $this->authenticate();
        $payload = $this->payload();
        $payload['groups'][0]['links'][0]['category_id'] = 99999;

        $this->postJson('/api/admin/mega-menus', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('groups.0.links.0.category_id');

        $this->assertDatabaseCount('mega_menus', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_duplicate_links_share_one_category_even_across_groups(): void
    {
        $this->authenticate();
        $payload = $this->payload();
        $payload['groups'][] = ['title' => 'Featured', 'links' => [['label' => 'CD players', 'href' => '/collection/cd-players']]];

        $response = $this->postJson('/api/admin/mega-menus', $payload)->assertCreated();

        $this->assertDatabaseCount('categories', 1);
        $id = Category::query()->sole()->id;
        $response->assertJsonPath('data.groups.0.links.0.category_id', $id)->assertJsonPath('data.groups.1.links.0.category_id', $id);
    }

    public function test_admin_categories_show_menu_and_group_associations(): void
    {
        $this->authenticate();
        $category = Category::factory()->create();
        $this->menuFor($category);

        $this->getJson('/api/admin/categories')->assertOk()
            ->assertJsonPath('data.0.menu_groups', ['Sources / Digital']);
    }

    public function test_deleting_category_used_by_inactive_menu_returns_422(): void
    {
        $this->authenticate();
        $category = Category::factory()->create();
        $this->menuFor($category)->update(['is_active' => false]);

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertUnprocessable()
            ->assertJsonPath('errors.category.0', 'Remove this category from Mega Menu links before deleting it.');

        $this->assertModelExists($category);
    }

    public function test_deleting_menu_keeps_categories_and_products(): void
    {
        $this->authenticate();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $menu = $this->menuFor($category);

        $this->deleteJson("/api/admin/mega-menus/{$menu->id}")->assertNoContent();

        $this->assertModelMissing($menu);
        $this->assertModelExists($category);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_category_without_home_visibility_can_be_created_without_image(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/categories', ['name' => 'CD Players', 'sort_order' => 0, 'show_on_home' => false, 'is_active' => true])
            ->assertCreated()->assertJsonPath('data.image_url', null);

        $this->assertDatabaseHas('categories', ['name' => 'CD Players', 'image_path' => null, 'show_on_home' => false]);
    }

    public function test_parent_collection_filters_by_linked_ids_before_limit(): void
    {
        $category = Category::factory()->create(['name' => 'CD Players', 'slug' => 'cd-players']);
        $this->menuFor($category);
        $wanted = Product::factory()->create(['category_id' => $category->id, 'category' => 'Outdated name', 'created_at' => now()->subDay()]);
        Product::factory()->create(['category' => 'CD Players', 'created_at' => now()]);
        Product::factory()->create(['category_id' => Category::factory()->create()->id, 'created_at' => now()]);

        $this->getJson('/api/products?collection=sources&limit=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id)->assertJsonPath('data.0.category_slug', 'cd-players');
    }

    #[TestWith(['Source', '/collection/', 'source'])]
    #[TestWith(['Amplifiers', '/collection/amplifiers', 'amplifiers'])]
    #[TestWith(['Speaker', '/collection/speaker', 'speaker'])]
    #[TestWith(['Accessories', '/collection/accessories', 'accessories'])]
    public function test_view_all_includes_all_active_products_from_its_menu_categories(string $label, string $href, string $slug): void
    {
        $firstCategory = Category::factory()->create();
        $secondCategory = Category::factory()->create();
        $hiddenCategory = Category::factory()->create(['is_active' => false]);
        $menu = MegaMenu::factory()->create(['label' => $label, 'href' => $href, 'groups' => [[
            'title' => 'Types', 'links' => [
                ['label' => $firstCategory->name, 'href' => '/collection/'.$firstCategory->slug, 'category_id' => $firstCategory->id],
                ['label' => $secondCategory->name, 'href' => '/collection/'.$secondCategory->slug, 'category_id' => $secondCategory->id],
                ['label' => $hiddenCategory->name, 'href' => '/collection/'.$hiddenCategory->slug, 'category_id' => $hiddenCategory->id],
            ],
        ]]]);
        $first = Product::factory()->create(['category_id' => $firstCategory->id, 'created_at' => now()->subDay()]);
        $second = Product::factory()->create(['category_id' => $secondCategory->id]);
        Product::factory()->inactive()->create(['category_id' => $firstCategory->id]);
        Product::factory()->create(['category_id' => $hiddenCategory->id]);
        Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        Product::factory()->create(['category_id' => null]);
        MegaMenu::factory()->inactive()->create(['href' => '/collection/'.$slug]);

        $this->getJson('/api/mega-menus')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.href', '/collection/'.$slug);
        $this->getJson('/api/products?collection='.$slug)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)->assertJsonPath('data.1.id', $first->id);
        $this->assertDatabaseHas('mega_menus', ['id' => $menu->id, 'href' => $href]);
    }

    public function test_collection_pagination_includes_products_beyond_first_hundred(): void
    {
        $category = Category::factory()->create(['slug' => 'cd-players']);
        $products = Product::factory()->count(101)->create(['category_id' => $category->id, 'created_at' => now()]);

        $this->getJson('/api/products?collection=cd-players&limit=100&page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $products->first()->id);
    }

    #[TestWith(['missing-collection'])]
    #[TestWith(['cd-players'])]
    public function test_unknown_or_inactive_collection_returns_no_products(string $slug): void
    {
        $category = Category::factory()->create(['slug' => 'cd-players', 'is_active' => false]);
        Product::factory()->create(['category_id' => $category->id]);
        Product::factory()->create(['category' => $slug]);

        $this->getJson('/api/products?collection='.$slug)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_public_menu_omits_inactive_category_links(): void
    {
        $category = Category::factory()->create(['is_active' => false]);
        $this->menuFor($category);

        $this->getJson('/api/mega-menus')->assertOk()->assertJsonPath('data.0.groups.0.links', []);
    }

    public function test_inactive_category_products_are_hidden_from_detail_and_sitemap(): void
    {
        $category = Category::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/product/'.$product->id, false);
    }

    public function test_data_migration_imports_existing_menus_without_losing_category_settings(): void
    {
        $category = Category::factory()->create(['name' => 'CD Players', 'slug' => 'compact-disc', 'show_on_home' => true]);
        $menu = MegaMenu::factory()->create(['groups' => $this->payload()['groups']]);
        $legacy = Product::factory()->create(['category' => 'CD Players', 'category_id' => null]);
        $migration = require database_path('migrations/2026_10_16_105600_link_mega_menu_collections_to_categories.php');

        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('categories', 1);
        $this->assertSame('/collection/compact-disc', $menu->refresh()->groups[0]['links'][0]['href']);
        $this->assertSame($category->id, $menu->groups[0]['links'][0]['category_id']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'show_on_home' => true, 'image_path' => $category->image_path]);
        $this->assertDatabaseHas('products', ['id' => $legacy->id, 'category_id' => $category->id]);
    }

    public function test_invalid_collection_rolls_back_categories_menu_and_uploaded_image(): void
    {
        $this->authenticate();
        Storage::fake('public');
        $payload = $this->payload();
        $payload['groups'][0]['links'][] = ['label' => 'Too long', 'href' => '/collection/'.str_repeat('a', 256)];
        $payload['featured_image'] = UploadedFile::fake()->image('banner.jpg');

        $this->post('/api/admin/mega-menus', $payload, ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonPath('errors.groups.0', 'Collection URLs must have a slug of at most 255 characters.');

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('mega_menus', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_parent_collection_includes_direct_category_and_group_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'sources']);
        $child = Category::factory()->create(['slug' => 'cd-players']);
        $this->menuFor($child);
        $direct = Product::factory()->create(['category_id' => $parent->id]);
        $nested = Product::factory()->create(['category_id' => $child->id]);
        Product::factory()->create();

        $this->getJson('/api/products?collection=sources')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $direct->id])->assertJsonFragment(['id' => $nested->id]);
    }

    public function test_data_migration_creates_missing_categories_and_skips_ordinary_links(): void
    {
        $groups = $this->payload()['groups'];
        $groups[0]['links'][] = ['label' => 'About', 'href' => '/about'];
        $groups[0]['links'][] = ['label' => 'All Products', 'href' => '/collection/all-products'];
        $menu = MegaMenu::factory()->create(['groups' => $groups]);
        $migration = require database_path('migrations/2026_10_16_105600_link_mega_menu_collections_to_categories.php');

        $migration->up();

        $this->assertDatabaseCount('categories', 1);
        $category = Category::query()->sole();
        $this->assertSame('cd-players', $category->slug);
        $this->assertSame($category->id, $menu->refresh()->groups[0]['links'][0]['category_id']);
        $this->assertSame('/about', $menu->groups[0]['links'][1]['href']);
    }

    public function test_seeded_menus_link_to_categories_and_can_be_seeded_again(): void
    {
        $this->seed(MegaMenuSeeder::class);
        $count = Category::query()->count();

        $this->seed(MegaMenuSeeder::class);

        $this->assertDatabaseCount('categories', $count);
        $this->assertDatabaseCount('mega_menus', 4);
        $speakers = MegaMenu::query()->where('label', 'Speakers')->firstOrFail();
        $category = Category::query()->findOrFail($speakers->groups[0]['links'][0]['category_id']);
        $this->assertSame('Hi-Fi Speakers', $category->name);
        $this->assertSame('/collection/'.$category->slug, $speakers->groups[0]['links'][0]['href']);
    }

    public function test_unlinked_categories_are_hidden_from_admin_even_when_they_have_products(): void
    {
        $this->authenticate();
        $linked = Category::factory()->create();
        $orphan = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $orphan->id]);
        $this->menuFor($linked);

        $this->getJson('/api/admin/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $linked->id);

        $this->assertModelExists($orphan);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $orphan->id]);
    }

    public function test_removed_link_disappears_from_category_list_after_saving_menu(): void
    {
        $this->authenticate();
        $removed = Category::factory()->create();
        $menu = $this->menuFor($removed);
        $this->putJson("/api/admin/mega-menus/{$menu->id}", $this->payload())->assertOk();

        $this->getJson('/api/admin/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'CD Players');
        $this->assertModelExists($removed);
    }

    public function test_deleted_menu_categories_are_hidden_but_shared_categories_remain_visible(): void
    {
        $this->authenticate();
        $shared = Category::factory()->create();
        $removed = Category::factory()->create();
        $first = $this->menuFor($shared);
        $groups = $first->groups;
        $groups[0]['links'][] = ['label' => $removed->name, 'href' => '/collection/'.$removed->slug, 'category_id' => $removed->id];
        $first->update(['groups' => $groups]);
        $this->menuFor($shared);
        $this->deleteJson("/api/admin/mega-menus/{$first->id}")->assertNoContent();

        $this->getJson('/api/admin/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shared->id);
    }

    public function test_unlinked_categories_are_hidden_from_public_and_home_lists(): void
    {
        Category::factory()->create(['show_on_home' => true]);
        $linked = Category::factory()->create(['show_on_home' => true]);
        $this->menuFor($linked);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $linked->id);
        $this->getJson('/api/categories?show_on_home=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $linked->id);
    }

    public function test_renaming_shared_link_does_not_rename_another_menu_category(): void
    {
        $this->authenticate();
        $original = Category::factory()->create(['name' => 'CD Players', 'slug' => 'cd-players']);
        $menu = $this->menuFor($original);
        $this->menuFor($original);
        $payload = $this->payload();
        $payload['groups'][0]['links'][0] = ['label' => 'DACs', 'href' => '/collection/dacs', 'category_id' => $original->id];

        $response = $this->putJson("/api/admin/mega-menus/{$menu->id}", $payload)->assertOk();

        $new = Category::query()->where('name', 'DACs')->firstOrFail();
        $response->assertJsonPath('data.groups.0.links.0.category_id', $new->id);
        $this->assertDatabaseHas('categories', ['id' => $original->id, 'name' => 'CD Players']);
    }

    public function test_existing_label_takes_precedence_over_a_previously_selected_category(): void
    {
        $this->authenticate();
        $previous = Category::factory()->create(['name' => 'CD Players']);
        $target = Category::factory()->create(['name' => 'DACs', 'slug' => 'dacs']);
        $menu = $this->menuFor($previous);
        $payload = $this->payload();
        $payload['groups'][0]['links'][0] = ['label' => 'DACs', 'href' => '/collection/dacs', 'category_id' => $previous->id];

        $this->putJson("/api/admin/mega-menus/{$menu->id}", $payload)->assertOk()
            ->assertJsonPath('data.groups.0.links.0.category_id', $target->id);

        $this->assertDatabaseHas('categories', ['id' => $previous->id, 'name' => 'CD Players']);
    }

    public function test_different_labels_with_same_url_create_distinct_categories(): void
    {
        $this->authenticate();
        $payload = $this->payload();
        $payload['groups'][0]['links'][] = ['label' => 'DACs', 'href' => '/collection/Cd players'];

        $response = $this->postJson('/api/admin/mega-menus', $payload)->assertCreated();

        $this->assertDatabaseCount('categories', 2);
        $response->assertJsonPath('data.groups.0.links.0.href', '/collection/cd-players')
            ->assertJsonPath('data.groups.0.links.1.href', '/collection/dacs');
        $this->assertDatabaseHas('categories', ['name' => 'CD Players']);
        $this->assertDatabaseHas('categories', ['name' => 'DACs']);
    }

    public function test_category_edit_cannot_override_link_label_name(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['name' => 'CD Players']);
        $this->menuFor($category);

        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Manual name', 'description' => 'Updated cover information', 'sort_order' => 0, 'show_on_home' => false, 'is_active' => true])
            ->assertOk()->assertJsonPath('data.name', 'CD Players');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'CD Players', 'description' => 'Updated cover information']);
    }

    private function authenticate(): void
    {
        User::factory()->create(['role' => 'super_admin', 'api_token' => hash('sha256', 'menu-category-token'), 'api_token_expires_at' => now()->addHour()]);
        $this->withToken('menu-category-token');
    }

    private function menuFor(Category $category): MegaMenu
    {
        return MegaMenu::factory()->create(['label' => 'Sources', 'href' => '/collection/sources', 'groups' => [[
            'title' => 'Digital', 'links' => [['label' => $category->name, 'href' => '/collection/'.$category->slug, 'category_id' => $category->id]],
        ]]]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['label' => 'Sources', 'href' => '/collection/sources', 'sort_order' => 0, 'is_active' => true, 'groups' => [[
            'title' => 'Digital', 'links' => [['label' => 'CD Players', 'href' => '/collection/Cd players']],
        ]]];
    }
}
