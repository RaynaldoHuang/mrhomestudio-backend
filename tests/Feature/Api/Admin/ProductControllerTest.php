<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $token = 'product-admin-test-token';
        User::factory()->create([
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addHour(),
        ]);
        $this->withToken($token);
    }

    public function test_store_creates_product_with_variants_specs_and_ordered_images(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/admin/products', [
            ...$this->validPayload(),
            'show_on_home' => '1',
            'images' => [
                UploadedFile::fake()->image('thumbnail.jpg', 2000, 1000),
                UploadedFile::fake()->image('detail.jpg'),
            ],
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Reference Speaker')
            ->assertJsonPath('data.show_on_home', true)
            ->assertJsonPath('data.variants.0.name', 'Color')
            ->assertJsonPath('data.images.0.sort_order', 0)
            ->assertJsonCount(2, 'data.images');

        $product = Product::query()->firstOrFail();
        $this->assertSame('reference-speaker', $product->slug);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'show_on_home' => true]);
        Storage::disk('public')->assertExists($product->images()->firstOrFail()->path);
        foreach ($product->images as $image) {
            $this->assertStringEndsWith('.webp', $image->path);
            $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($image->path))['mime']);
        }
        $thumbnail = getimagesizefromstring(Storage::disk('public')->get($product->images->first()->path));
        $this->assertSame([1600, 800], [$thumbnail[0], $thumbnail[1]]);
    }

    public function test_store_rejects_more_than_five_images(): void
    {
        Storage::fake('public');

        $this->post('/api/admin/products', [
            ...$this->validPayload(),
            'images' => collect(range(1, 6))->map(fn (int $number) => UploadedFile::fake()->image("{$number}.jpg"))->all(),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');
    }

    public function test_update_can_remove_an_image_and_append_a_new_one(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/old-one.jpg', 'old');
        Storage::disk('public')->put('products/old-two.jpg', 'old');
        $first = $product->images()->create(['path' => 'products/old-one.jpg', 'sort_order' => 0]);
        $product->images()->create(['path' => 'products/old-two.jpg', 'sort_order' => 1]);

        $response = $this->post("/api/admin/products/{$product->id}", [
            ...$this->validPayload(),
            '_method' => 'PUT',
            'remove_image_ids' => [$first->id],
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonCount(2, 'data.images');
        Storage::disk('public')->assertMissing('products/old-one.jpg');
        Storage::disk('public')->assertExists($product->refresh()->images()->latest('id')->firstOrFail()->path);
        $newImage = $product->images()->reorder()->latest('id')->firstOrFail();
        $this->assertStringEndsWith('.webp', $newImage->path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($newImage->path))['mime']);
    }

    public function test_destroy_removes_product_and_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/product.jpg', 'image');
        $product->images()->create(['path' => 'products/product.jpg', 'sort_order' => 0]);

        $this->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();

        $this->assertModelMissing($product);
        Storage::disk('public')->assertMissing('products/product.jpg');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_update_saves_home_selection(bool $showOnHome): void
    {
        $product = Product::factory()->create(['show_on_home' => ! $showOnHome]);
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);

        $this->putJson("/api/admin/products/{$product->id}", [
            ...$this->validPayload(),
            'show_on_home' => $showOnHome ? '1' : '0',
        ])
            ->assertOk()
            ->assertJsonPath('data.show_on_home', $showOnHome);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'show_on_home' => $showOnHome]);
    }

    public function test_update_rejects_invalid_home_selection_with_422(): void
    {
        $product = Product::factory()->create(['show_on_home' => false]);
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);

        $this->putJson("/api/admin/products/{$product->id}", [
            ...$this->validPayload(),
            'show_on_home' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('show_on_home');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'show_on_home' => false]);
    }

    public function test_update_assigns_product_to_selected_category(): void
    {
        $product = Product::factory()->create();
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);
        $category = Category::factory()->create();

        $this->putJson("/api/admin/products/{$product->id}", [...$this->validPayload(), 'category_id' => $category->id])
            ->assertOk()->assertJsonPath('data.category_id', $category->id)->assertJsonPath('data.category', $category->name);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id, 'category' => $category->name]);
    }

    public function test_update_can_clear_product_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'category' => $category->name]);
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);

        $this->putJson("/api/admin/products/{$product->id}", [...$this->validPayload(), 'category_id' => null])
            ->assertOk()->assertJsonPath('data.category_id', null)->assertJsonPath('data.category', null);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null, 'category' => null]);
    }

    public function test_update_rejects_nonexistent_category(): void
    {
        $product = Product::factory()->create();

        $this->putJson("/api/admin/products/{$product->id}", [...$this->validPayload(), 'category_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Reference Speaker',
            'category' => 'Speakers',
            'price' => 15_000_000,
            'stock' => 10,
            'description' => 'Detailed product description.',
            'specifications' => [['name' => 'Weight', 'value' => '12 kg']],
            'variants' => [['name' => 'Color', 'values' => ['Black', 'White']]],
            'is_active' => '1',
        ];
    }
}
