<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BrandControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['GET'])]
    #[TestWith(['POST'])]
    #[TestWith(['PUT'])]
    #[TestWith(['DELETE'])]
    public function test_master_data_requires_authentication_with_401(string $method): void
    {
        $brand = Brand::factory()->create();
        $path = in_array($method, ['PUT', 'DELETE']) ? "/api/admin/brands/{$brand->id}" : '/api/admin/brands';
        $this->json($method, $path, ['name' => 'Changed'])->assertUnauthorized();
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => $brand->name]);
    }

    public function test_non_admin_cannot_access_brand_master_with_401(): void
    {
        $this->authenticate('customer');
        $this->getJson('/api/admin/brands')->assertUnauthorized();
    }

    public function test_index_lists_all_brands_with_product_counts(): void
    {
        $this->authenticate();
        $brand = Brand::factory()->create(['name' => 'Accuphase']);
        Brand::factory()->create(['name' => 'Marantz']);
        Product::factory()->create(['brand_id' => $brand->id]);
        Product::factory()->inactive()->create(['brand_id' => $brand->id]);
        $this->getJson('/api/admin/brands')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Accuphase')->assertJsonPath('data.0.products_count', 2)
            ->assertJsonPath('data.1.products_count', 0);
    }

    public function test_store_normalizes_spacing_and_ignores_client_normalized_name(): void
    {
        $this->authenticate();
        $this->postJson('/api/admin/brands', ['name' => '  Audio   Research  ', 'normalized_name' => 'wrong'])
            ->assertCreated()->assertJsonPath('data.name', 'Audio Research')->assertJsonPath('data.products_count', 0);
        $this->assertDatabaseHas('brands', ['name' => 'Audio Research', 'normalized_name' => 'audio research']);
    }

    #[TestWith([''])]
    #[TestWith([null])]
    #[TestWith([123])]
    #[TestWith([['invalid']])]
    #[TestWith(['xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'])]
    public function test_invalid_name_is_rejected_on_create_and_update_with_422(mixed $name): void
    {
        $this->authenticate();
        $brand = Brand::factory()->create(['name' => 'Original']);
        $this->postJson('/api/admin/brands', ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/admin/brands/{$brand->id}", ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('brands', 1);
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Original']);
    }

    public function test_duplicates_ignore_case_and_whitespace_with_422(): void
    {
        $this->authenticate();
        Brand::factory()->create(['name' => 'Audio Research']);
        $other = Brand::factory()->create(['name' => 'Marantz']);
        $this->postJson('/api/admin/brands', ['name' => ' audio   RESEARCH '])->assertUnprocessable()->assertJsonValidationErrors('normalized_name');
        $this->putJson("/api/admin/brands/{$other->id}", ['name' => 'Audio Research'])->assertUnprocessable()->assertJsonValidationErrors('normalized_name');
        $this->assertDatabaseCount('brands', 2);
        $this->assertDatabaseHas('brands', ['id' => $other->id, 'name' => 'Marantz']);
    }

    public function test_rename_updates_product_api_and_accepts_unchanged_name(): void
    {
        $this->authenticate();
        $brand = Brand::factory()->create(['name' => 'Original']);
        $product = Product::factory()->create(['brand_id' => $brand->id]);
        $this->putJson("/api/admin/brands/{$brand->id}", ['name' => 'Original'])->assertOk();
        $this->putJson("/api/admin/brands/{$brand->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Renamed', 'normalized_name' => 'renamed']);
        $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.brand', 'Renamed')->assertJsonPath('data.brand_id', $brand->id);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.brand', 'Renamed');
    }

    public function test_unused_brand_can_be_deleted(): void
    {
        $this->authenticate();
        $brand = Brand::factory()->create();
        $this->deleteJson("/api/admin/brands/{$brand->id}")->assertNoContent();
        $this->assertModelMissing($brand);
    }

    public function test_brand_used_by_inactive_product_cannot_be_deleted_with_422(): void
    {
        $this->authenticate();
        $brand = Brand::factory()->create();
        $product = Product::factory()->inactive()->create(['brand_id' => $brand->id]);
        $this->deleteJson("/api/admin/brands/{$brand->id}")->assertUnprocessable()->assertJsonValidationErrors('brand');
        $this->assertModelExists($brand);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'brand_id' => $brand->id]);
    }

    public function test_unknown_brand_returns_404(): void
    {
        $this->authenticate();
        $this->putJson('/api/admin/brands/999999', ['name' => 'Unknown'])->assertNotFound();
        $this->deleteJson('/api/admin/brands/999999')->assertNotFound();
    }

    private function authenticate(string $role = 'super_admin'): void
    {
        $token = 'brand-admin-token';
        User::factory()->create(['role' => $role, 'api_token' => hash('sha256', $token), 'api_token_expires_at' => now()->addHour()]);
        $this->withToken($token);
    }
}
