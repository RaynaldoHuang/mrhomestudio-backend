<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Brand;
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

        $brand = Brand::factory()->create(['name' => 'Marantz']);
        $response = $this->post('/api/admin/products', [
            ...$this->validPayload(),
            'show_on_home' => '1',
            'brand_id' => $brand->id,
            'images' => [
                UploadedFile::fake()->image('thumbnail.jpg', 2000, 1000),
                UploadedFile::fake()->image('detail.jpg'),
            ],
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Reference Speaker')
            ->assertJsonPath('data.show_on_home', true)
            ->assertJsonPath('data.brand', 'Marantz')
            ->assertJsonPath('data.variants.0.name', 'Color')
            ->assertJsonPath('data.images.0.sort_order', 0)
            ->assertJsonCount(2, 'data.images');

        $product = Product::query()->firstOrFail();
        $this->assertSame('reference-speaker', $product->slug);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'show_on_home' => true, 'brand_id' => $brand->id]);
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

    public function test_clearing_copied_variants_and_specifications_persists_without_changing_original(): void
    {
        Storage::fake('public');
        $source = Product::factory()->create([
            'variants' => [['name' => 'Color', 'values' => ['Black', 'White']]],
            'specifications' => [['name' => 'Weight', 'value' => '12 kg']],
        ]);
        Storage::disk('public')->put('products/original.webp', 'image');
        $source->images()->create(['path' => 'products/original.webp', 'sort_order' => 0]);
        $copyId = $this->postJson("/api/admin/products/{$source->id}/duplicate")
            ->assertCreated()->json('data.id');

        $this->post("/api/admin/products/{$copyId}", [
            ...$this->validPayload(),
            '_method' => 'PUT',
            'variants' => '',
            'specifications' => '',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.variants', [])
            ->assertJsonPath('data.specifications', []);

        $this->getJson("/api/admin/products/{$copyId}")->assertOk()
            ->assertJsonPath('data.variants', [])
            ->assertJsonPath('data.specifications', []);
        $this->assertDatabaseHas('products', ['id' => $copyId, 'variants' => null, 'specifications' => null]);
        $this->assertSame([['name' => 'Color', 'values' => ['Black', 'White']]], $source->fresh()->variants);
        $this->assertSame([['name' => 'Weight', 'value' => '12 kg']], $source->fresh()->specifications);
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

    public function test_update_does_not_require_stock_or_change_legacy_inventory(): void
    {
        $product = Product::factory()->create(['stock' => 7]);
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);

        $this->putJson("/api/admin/products/{$product->id}", $this->validPayload())->assertOk()
            ->assertJsonMissingPath('data.stock');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7, 'name' => 'Reference Speaker']);
    }

    #[TestWith(['Accuphase'])]
    #[TestWith(['Marantz'])]
    #[TestWith([null])]
    public function test_update_saves_or_clears_brand(?string $brandName): void
    {
        $original = Brand::factory()->create(['name' => 'Previous Brand']);
        $brand = $brandName === null ? null : Brand::factory()->create(['name' => $brandName]);
        $product = Product::factory()->create(['brand_id' => $original->id]);
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);

        $this->putJson("/api/admin/products/{$product->id}", [
            ...$this->validPayload(),
            'brand_id' => $brand?->id,
        ])->assertOk()->assertJsonPath('data.brand', $brandName)->assertJsonPath('data.brand_id', $brand?->id);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'brand_id' => $brand?->id]);
    }

    #[TestWith(['brand_id', 'invalid', 'The brand id field must be an integer.'])]
    #[TestWith(['brand_id', 999999, 'The selected brand id is invalid.'])]
    public function test_store_and_update_reject_invalid_catalog_fields_with_422(string $field, mixed $value, string $message): void
    {
        Storage::fake('public');
        $brand = Brand::factory()->create(['name' => 'Original']);
        $product = Product::factory()->create(['brand_id' => $brand->id]);
        $product->images()->create(['path' => 'products/existing.jpg', 'sort_order' => 0]);
        $payload = [...$this->validPayload(), $field => $value];

        $this->post('/api/admin/products', [...$payload, 'images' => [UploadedFile::fake()->image('new.jpg')]], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->putJson("/api/admin/products/{$product->id}", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'brand_id' => $brand->id]);
    }

    public function test_duplicate_creates_inactive_copy_with_independent_ordered_images(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $source = Product::factory()->create([
            'name' => 'Reference Speaker', 'slug' => 'reference-speaker',
            'category_id' => $category->id, 'brand_id' => $brand->id,
            'is_active' => true, 'show_on_home' => true, 'stock' => 7,
        ]);
        Storage::disk('public')->put('products/first.webp', 'first image');
        Storage::disk('public')->put('products/second.webp', 'second image');
        $source->images()->create(['path' => 'products/second.webp', 'sort_order' => 8]);
        $source->images()->create(['path' => 'products/first.webp', 'sort_order' => 2]);

        $response = $this->postJson("/api/admin/products/{$source->id}/duplicate");
        $response->assertCreated()->assertJsonPath('data.name', 'Reference Speaker (Copy)')
            ->assertJsonPath('data.slug', 'reference-speaker-copy')
            ->assertJsonPath('data.category_id', $category->id)->assertJsonPath('data.brand_id', $brand->id)
            ->assertJsonPath('data.is_active', false)->assertJsonPath('data.show_on_home', false)
            ->assertJsonPath('data.price', $source->price)->assertJsonPath('data.description', $source->description)
            ->assertJsonPath('data.specifications', $source->specifications)->assertJsonPath('data.variants', $source->variants)
            ->assertJsonCount(2, 'data.images')->assertJsonPath('data.images.0.sort_order', 0)->assertJsonPath('data.images.1.sort_order', 1);
        $copy = Product::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame($source->id, $copy->id);
        $this->assertDatabaseHas('products', ['id' => $copy->id, 'stock' => 0, 'is_active' => false]);
        $this->assertDatabaseHas('products', ['id' => $source->id, 'is_active' => true, 'show_on_home' => true, 'name' => 'Reference Speaker']);
        $this->assertSame('first image', Storage::disk('public')->get($copy->images[0]->path));
        $this->assertSame('second image', Storage::disk('public')->get($copy->images[1]->path));
        $this->assertNotSame('products/first.webp', $copy->images[0]->path);
        $this->getJson("/api/products/{$copy->id}")->assertNotFound();

        $this->deleteJson("/api/admin/products/{$source->id}")->assertNoContent();
        Storage::disk('public')->assertExists($copy->images[0]->path);
        Storage::disk('public')->assertExists($copy->images[1]->path);
    }

    public function test_deleting_a_copy_preserves_original_images(): void
    {
        Storage::fake('public');
        $source = Product::factory()->create();
        Storage::disk('public')->put('products/original.webp', 'original');
        $source->images()->create(['path' => 'products/original.webp', 'sort_order' => 0]);
        $response = $this->postJson("/api/admin/products/{$source->id}/duplicate")->assertCreated();
        $copy = Product::query()->findOrFail($response->json('data.id'));
        $copyPath = $copy->images->first()->path;
        $this->deleteJson("/api/admin/products/{$copy->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($copyPath);
        Storage::disk('public')->assertExists('products/original.webp');
        $this->assertModelExists($source);
    }

    public function test_repeated_duplicates_use_unique_slugs_for_maximum_length_names(): void
    {
        Storage::fake('public');
        $source = Product::factory()->inactive()->create(['name' => str_repeat('a', 255)]);
        Storage::disk('public')->put('products/original.webp', 'original');
        $source->images()->create(['path' => 'products/original.webp', 'sort_order' => 0]);
        $first = $this->postJson("/api/admin/products/{$source->id}/duplicate")->assertCreated()->json('data');
        $second = $this->postJson("/api/admin/products/{$source->id}/duplicate")->assertCreated()->json('data');
        $this->assertNotSame($first['slug'], $second['slug']);
        $this->assertSame(255, strlen($first['name']));
        $this->assertSame(255, strlen($second['slug']));
        $this->assertDatabaseCount('products', 3);
    }

    public function test_duplicate_rejects_missing_image_and_cleans_up_copied_files_with_422(): void
    {
        Storage::fake('public');
        $source = Product::factory()->create();
        Storage::disk('public')->put('products/existing.webp', 'existing');
        $source->images()->create(['path' => 'products/existing.webp', 'sort_order' => 0]);
        $source->images()->create(['path' => 'products/missing.webp', 'sort_order' => 1]);
        $this->postJson("/api/admin/products/{$source->id}/duplicate")->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertSame(['products/existing.webp'], Storage::disk('public')->allFiles('products'));
    }

    #[TestWith([0])]
    #[TestWith([6])]
    public function test_duplicate_with_invalid_image_count_returns_422(int $imageCount): void
    {
        $source = Product::factory()->create();
        for ($index = 0; $index < $imageCount; $index++) {
            $source->images()->create(['path' => "products/image-{$index}.webp", 'sort_order' => $index]);
        }
        $this->postJson("/api/admin/products/{$source->id}/duplicate")->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_duplicate_unknown_product_returns_404(): void
    {
        $this->postJson('/api/admin/products/999999/duplicate')->assertNotFound();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_without_authentication_returns_401(): void
    {
        $source = Product::factory()->create();
        $this->withHeaders(['Authorization' => ''])->postJson("/api/admin/products/{$source->id}/duplicate")->assertUnauthorized();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_duplicate_by_non_admin_returns_401(): void
    {
        $source = Product::factory()->create();
        $token = 'customer-product-token';
        User::factory()->create(['role' => 'customer', 'api_token' => hash('sha256', $token), 'api_token_expires_at' => now()->addHour()]);
        $this->withToken($token)->postJson("/api/admin/products/{$source->id}/duplicate")->assertUnauthorized();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_store_saves_numeric_combination_prices_and_exposes_the_lowest_price_publicly(): void
    {
        Storage::fake('public');
        $this->post('/api/admin/products', [
            ...$this->validPayload(),
            'price' => 100,
            'variants' => [
                ['name' => 'Color', 'values' => ['Black', 'White']],
                ['name' => 'Size', 'values' => ['Small', 'Large']],
            ],
            'variant_prices' => [
                ['options' => ['Black', 'Small'], 'price' => '80'],
                ['options' => ['White', 'Large'], 'price' => '150'],
            ],
            'images' => [UploadedFile::fake()->image('speaker.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.price', 100)
            ->assertJsonPath('data.min_price', 80)
            ->assertJsonPath('data.max_price', 150)
            ->assertJsonPath('data.variant_prices.0.price', 80);

        $product = Product::query()->firstOrFail();
        $this->assertSame([
            ['options' => ['Black', 'Small'], 'price' => 80],
            ['options' => ['White', 'Large'], 'price' => 150],
        ], $product->variant_prices);
        Storage::disk('public')->assertExists($product->images->first()->path);
        $this->getJson("/api/products/{$product->id}")
            ->assertOk()->assertJsonPath('data.min_price', 80)->assertJsonPath('data.max_price', 150);
    }

    public function test_update_ignores_unused_base_price_when_every_variant_has_the_same_custom_price(): void
    {
        $product = Product::factory()->hasImages(1)->create();
        $this->putJson("/api/admin/products/{$product->id}", [
            ...$this->validPayload(),
            'price' => 1,
            'variant_prices' => [
                ['options' => ['Black'], 'price' => 250],
                ['options' => ['White'], 'price' => 250],
            ],
        ])->assertOk()->assertJsonPath('data.min_price', 250)->assertJsonPath('data.max_price', 250);
        $this->assertSame(250, $product->refresh()->variant_prices[0]['price']);
    }

    public function test_update_can_clear_combination_prices_and_return_to_base_price(): void
    {
        $product = Product::factory()->hasImages(1)->create([
            'variant_prices' => [['options' => ['Black'], 'price' => 80]],
        ]);
        $this->putJson("/api/admin/products/{$product->id}", [
            ...$this->validPayload(), 'variant_prices' => '',
        ])->assertOk()->assertJsonPath('data.variant_prices', [])->assertJsonPath('data.min_price', 15_000_000);
        $this->assertSame([], $product->refresh()->variant_prices);
    }

    #[TestWith([[['options' => ['Missing'], 'price' => 100]], 'variant_prices.0.options'])]
    #[TestWith([[['options' => ['Black', 'Extra'], 'price' => 100]], 'variant_prices.0.options'])]
    #[TestWith([[['options' => ['Black'], 'price' => -1]], 'variant_prices.0.price'])]
    #[TestWith([[['options' => ['Black'], 'price' => 1.5]], 'variant_prices.0.price'])]
    #[TestWith([[['options' => ['Black'], 'price' => 9007199254740992]], 'variant_prices.0.price'])]
    #[TestWith([[['options' => ['Black'], 'price' => 100], ['options' => ['Black'], 'price' => 200]], 'variant_prices.1.options'])]
    public function test_update_rejects_invalid_prices_without_changing_the_product(array $prices, string $error): void
    {
        $product = Product::factory()->hasImages(1)->create(['price' => 500]);
        $this->putJson("/api/admin/products/{$product->id}", [
            ...$this->validPayload(), 'variant_prices' => $prices,
        ])->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertSame(500, $product->refresh()->price);
        $this->assertNull($product->variant_prices);
    }

    public function test_duplicate_preserves_variant_prices(): void
    {
        Storage::fake('public');
        $prices = [['options' => ['Black'], 'price' => 800]];
        $product = Product::factory()->create(['variant_prices' => $prices]);
        Storage::disk('public')->put('products/original.jpg', 'image');
        $product->images()->create(['path' => 'products/original.jpg', 'sort_order' => 0]);
        $response = $this->postJson("/api/admin/products/{$product->id}/duplicate")
            ->assertCreated()->assertJsonPath('data.variant_prices', $prices);
        $duplicate = Product::query()->findOrFail($response->json('data.id'));
        $this->assertSame($prices, $duplicate->variant_prices);
        Storage::disk('public')->assertExists($duplicate->images->first()->path);
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
            'description' => 'Detailed product description.',
            'specifications' => [['name' => 'Weight', 'value' => '12 kg']],
            'variants' => [['name' => 'Color', 'values' => ['Black', 'White']]],
            'is_active' => '1',
        ];
    }
}
