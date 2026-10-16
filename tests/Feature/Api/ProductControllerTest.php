<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_only_active_products_newest_first_and_honours_limit(): void
    {
        Product::factory()->create(['name' => 'Older', 'created_at' => now()->subDay()]);
        Product::factory()->create(['name' => 'Newest', 'created_at' => now()]);
        Product::factory()->inactive()->create(['name' => 'Hidden']);

        $this->getJson('/api/products?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Newest')
            ->assertJsonMissing(['name' => 'Hidden']);
    }

    public function test_show_returns_active_product_with_images(): void
    {
        $product = Product::factory()->create();
        $product->images()->create(['path' => 'products/first.jpg', 'sort_order' => 0]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.thumbnail_url', 'http://localhost:8000/storage/products/first.jpg');
    }

    public function test_home_filter_returns_only_selected_active_products_before_applying_limit(): void
    {
        $selected = Product::factory()->create(['show_on_home' => true, 'created_at' => now()->subDay()]);
        $selected->images()->create(['path' => 'products/home.jpg', 'sort_order' => 0]);
        Product::factory()->create(['show_on_home' => false, 'created_at' => now()]);
        Product::factory()->inactive()->create(['show_on_home' => true]);

        $this->getJson('/api/products?show_on_home=1&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $selected->id)
            ->assertJsonPath('data.0.show_on_home', true)
            ->assertJsonPath('data.0.thumbnail_url', 'http://localhost:8000/storage/products/home.jpg');
    }

    public function test_home_filter_returns_empty_when_no_products_are_selected(): void
    {
        Product::factory()->create();

        $this->getJson('/api/products?show_on_home=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_catalog_returns_products_regardless_of_home_selection(): void
    {
        Product::factory()->create(['show_on_home' => true]);
        Product::factory()->create(['show_on_home' => false]);

        $this->getJson('/api/products?show_on_home=0')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_hides_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_active_product_with_zero_legacy_stock_is_available_without_inventory_data(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $this->getJson("/api/products/{$product->id}")->assertOk()
            ->assertJsonPath('data.id', $product->id)->assertJsonMissingPath('data.stock');
    }
}
