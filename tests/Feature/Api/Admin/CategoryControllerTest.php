<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_manage_categories(): void
    {
        $this->postJson('/api/admin/categories', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_non_admin_token_cannot_manage_categories(): void
    {
        $this->authenticate('customer');

        $this->getJson('/api/admin/categories')->assertUnauthorized();
    }

    public function test_admin_index_includes_inactive_categories_and_product_counts(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['is_active' => false]);
        Product::factory()->create(['category_id' => $category->id]);

        $this->getJson('/api/admin/categories')->assertOk()
            ->assertJsonPath('data.0.id', $category->id)
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_admin_can_create_category_with_cover_and_home_settings(): void
    {
        $this->authenticate();
        Storage::fake('public');

        $this->post('/api/admin/categories', [
            ...$this->payload(), 'image' => UploadedFile::fake()->image('cover.jpg', 2000, 1000),
        ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.slug', 'bookshelf-speakers')->assertJsonPath('data.show_on_home', true);

        $category = Category::query()->firstOrFail();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Bookshelf Speakers', 'sort_order' => 1, 'show_on_home' => true]);
        Storage::disk('public')->assertExists($category->image_path);
        $this->assertStringEndsWith('.webp', $category->image_path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($category->image_path))['mime']);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($category->image_path));
        $this->assertSame([1600, 800], [$dimensions[0], $dimensions[1]]);
    }

    public function test_create_requires_cover_image(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/categories', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_invalid_cover_file_and_order_are_rejected(): void
    {
        $this->authenticate();

        $this->post('/api/admin/categories', [
            ...$this->payload(), 'image' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'), 'sort_order' => -1,
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['image', 'sort_order']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_rename_keeps_collection_slug_and_product_association(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['name' => 'Original', 'slug' => 'original']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->putJson("/api/admin/categories/{$category->id}", [...$this->payload(), 'name' => 'New Name', 'show_on_home' => false])
            ->assertOk()->assertJsonPath('data.slug', 'original')->assertJsonPath('data.show_on_home', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name', 'show_on_home' => false]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_replacing_cover_removes_previous_image(): void
    {
        $this->authenticate();
        Storage::fake('public');
        Storage::disk('public')->put('categories/old.jpg', 'old');
        $category = Category::factory()->create(['image_path' => 'categories/old.jpg']);

        $this->post("/api/admin/categories/{$category->id}", [
            ...$this->payload(), '_method' => 'PUT', 'image' => UploadedFile::fake()->image('new.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing('categories/old.jpg');
        Storage::disk('public')->assertExists($category->refresh()->image_path);
        $this->assertStringEndsWith('.webp', $category->image_path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($category->image_path))['mime']);
    }

    public function test_home_selection_requires_cover_for_migrated_category(): void
    {
        $this->authenticate();
        $category = Category::factory()->create(['image_path' => null]);

        $this->putJson("/api/admin/categories/{$category->id}", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'show_on_home' => false]);
    }

    public function test_delete_removes_unused_category_and_cover(): void
    {
        $this->authenticate();
        Storage::fake('public');
        Storage::disk('public')->put('categories/delete.jpg', 'cover');
        $category = Category::factory()->create(['image_path' => 'categories/delete.jpg']);

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertNoContent();

        $this->assertModelMissing($category);
        Storage::disk('public')->assertMissing('categories/delete.jpg');
    }

    public function test_delete_rejects_category_used_by_products(): void
    {
        $this->authenticate();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->assertModelExists($category);
        $this->assertModelExists($product);
    }

    private function authenticate(string $role = 'super_admin'): void
    {
        $token = 'category-admin-token';
        User::factory()->create(['role' => $role, 'api_token' => hash('sha256', $token), 'api_token_expires_at' => now()->addHour()]);
        $this->withToken($token);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['name' => 'Bookshelf Speakers', 'description' => 'Compact speakers for your home.', 'sort_order' => 1, 'show_on_home' => true, 'is_active' => true];
    }
}
