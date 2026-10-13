<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_categories_are_selected_active_have_covers_and_follow_display_order(): void
    {
        $later = Category::factory()->create(['show_on_home' => true, 'sort_order' => 2]);
        $first = Category::factory()->create(['show_on_home' => true, 'sort_order' => 1]);
        Category::factory()->create(['show_on_home' => false]);
        Category::factory()->create(['show_on_home' => true, 'is_active' => false]);
        Category::factory()->create(['show_on_home' => true, 'image_path' => null]);

        $this->getJson('/api/categories?show_on_home=1')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $later->id)
            ->assertJsonPath('data.0.image_url', 'http://localhost:8000/storage/categories/cover.jpg');
    }

    public function test_category_list_includes_active_categories_not_selected_for_home(): void
    {
        $category = Category::factory()->create(['show_on_home' => false]);
        Category::factory()->create(['is_active' => false]);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $category->id);
    }

    public function test_product_filter_uses_category_identity_before_limit_and_returns_current_name(): void
    {
        $category = Category::factory()->create(['name' => 'Renamed Speakers']);
        $product = Product::factory()->create(['category_id' => $category->id, 'category' => 'Old name', 'created_at' => now()->subDay()]);
        Product::factory()->create(['category' => 'Renamed Speakers', 'created_at' => now()]);
        Product::factory()->inactive()->create(['category_id' => $category->id]);

        $this->getJson("/api/products?category_id={$category->id}&limit=1")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.category', 'Renamed Speakers')
            ->assertJsonPath('data.0.category_slug', $category->slug);
    }
}
