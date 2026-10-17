<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_product_brands_are_deduplicated_and_linked(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $empty = Product::factory()->create();
        DB::table('products')->where('id', $first->id)->update(['brand' => ' Audio   Research ']);
        DB::table('products')->where('id', $second->id)->update(['brand' => 'audio research']);
        DB::table('products')->where('id', $empty->id)->update(['brand' => '   ']);
        $migration = require database_path('migrations/2026_10_17_150302_link_products_to_brands.php');
        $migration->down();
        $migration->up();

        $brandId = DB::table('brands')->where('normalized_name', 'audio research')->value('id');
        $this->assertDatabaseCount('brands', 1);
        $this->assertDatabaseHas('brands', ['id' => $brandId, 'name' => 'Audio Research']);
        $this->assertDatabaseHas('products', ['id' => $first->id, 'brand_id' => $brandId]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'brand_id' => $brandId]);
        $this->assertDatabaseHas('products', ['id' => $empty->id, 'brand_id' => null]);
    }
}
