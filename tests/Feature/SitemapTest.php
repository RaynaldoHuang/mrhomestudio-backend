<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MegaMenu;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use SimpleXMLElement;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_sitemap_contains_static_pages_as_valid_xml(): void
    {
        config(['app.url' => 'https://mrhommeaudio.com']);

        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $xml = simplexml_load_string($response->getContent());
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->getDocNamespaces()['']);
        $this->assertSame([
            'https://mrhommeaudio.com/',
            'https://mrhommeaudio.com/about',
            'https://mrhommeaudio.com/collection/all-products',
        ], $this->locations($xml));
        $this->assertCount(0, $xml->xpath('//*[local-name()="lastmod"]'));
    }

    public function test_sitemap_includes_all_active_products_beyond_catalog_limit_with_real_lastmod(): void
    {
        config(['app.url' => 'https://mrhommeaudio.com']);
        $this->travelTo('2026-10-15 01:00:00');
        $products = Product::factory()->count(101)->create();
        $inactive = Product::factory()->inactive()->create();

        $response = $this->get('/sitemap.xml')->assertOk();

        $xml = simplexml_load_string($response->getContent());
        $locations = $this->locations($xml);
        $this->assertCount(104, $locations);
        $this->assertContains('https://mrhommeaudio.com/product/'.$products->last()->id, $locations);
        $this->assertNotContains('https://mrhommeaudio.com/product/'.$inactive->id, $locations);
        $this->assertSame('2026-10-15T01:00:00+00:00', (string) $xml->url[3]->lastmod);
    }

    public function test_categories_and_menu_links_are_canonical_unique_and_active(): void
    {
        config(['app.url' => 'https://mrhommeaudio.com/']);
        Category::factory()->create(['slug' => 'speakers']);
        Category::factory()->create(['slug' => 'hidden-category', 'is_active' => false]);
        $product = Product::factory()->create();
        MegaMenu::factory()->create([
            'href' => '/collection/speakers/',
            'featured_url' => 'https://mrhommeaudio.com/collection/amplifiers?sort=price#top',
            'groups' => [[
                'title' => 'Products',
                'links' => [
                    ['label' => 'Speakers', 'href' => '/collection/speakers?sort=latest'],
                    ['label' => 'Hidden', 'href' => '/collection/hidden-category'],
                    ['label' => 'Product', 'href' => '/product/'.$product->id],
                    ['label' => 'About', 'href' => '/about#team'],
                    ['label' => 'Headphones', 'href' => '/collection/headphones'],
                ],
            ]],
        ]);
        MegaMenu::factory()->inactive()->create([
            'href' => '/collection/hidden-menu',
            'featured_url' => '/collection/hidden-feature',
            'groups' => [],
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertSame([
            'https://mrhommeaudio.com/',
            'https://mrhommeaudio.com/about',
            'https://mrhommeaudio.com/collection/all-products',
            'https://mrhommeaudio.com/collection/speakers',
            'https://mrhommeaudio.com/product/'.$product->id,
            'https://mrhommeaudio.com/collection/amplifiers',
            'https://mrhommeaudio.com/collection/headphones',
        ], $this->locations(simplexml_load_string($response->getContent())));
    }

    #[TestWith(['/services'])]
    #[TestWith(['/contact'])]
    #[TestWith(['https://mrhommeaudio.com/services?source=menu'])]
    #[TestWith(['/admin/login'])]
    #[TestWith(['/dashboard'])]
    #[TestWith(['/cart'])]
    #[TestWith(['/api/products'])]
    #[TestWith(['/product/99999'])]
    #[TestWith(['/unknown-page'])]
    #[TestWith(['https://example.com/collection/external'])]
    #[TestWith(['https://mrhommeaudio.com.evil.test/collection/external'])]
    #[TestWith(['//mrhommeaudio.com/collection/protocol-relative'])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['mailto:hello@example.com'])]
    #[TestWith(['#'])]
    #[TestWith([''])]
    #[TestWith(['https://mrhommeaudio.com:8443/collection/wrong-port'])]
    #[TestWith(['https://user@mrhommeaudio.com/collection/credentials'])]
    #[TestWith(['/collection/<script>alert(1)</script>'])]
    #[TestWith(['/collection/../admin'])]
    public function test_menu_cannot_add_external_private_missing_or_unsafe_urls(string $href): void
    {
        config(['app.url' => 'https://mrhommeaudio.com']);
        MegaMenu::factory()->create(['href' => $href, 'groups' => [], 'featured_url' => null]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertCount(3, $this->locations(simplexml_load_string($response->getContent())));
    }

    public function test_menu_cannot_reintroduce_an_inactive_product(): void
    {
        config(['app.url' => 'https://mrhommeaudio.com']);
        $product = Product::factory()->inactive()->create();
        MegaMenu::factory()->create(['href' => '/product/'.$product->id, 'groups' => [], 'featured_url' => null]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertNotContains('https://mrhommeaudio.com/product/'.$product->id,
            $this->locations(simplexml_load_string($response->getContent())));
    }

    public function test_sitemap_uses_configured_origin_instead_of_request_host(): void
    {
        config(['app.url' => 'https://audio.example.test']);
        Category::factory()->create(['slug' => 'audio&video']);

        $response = $this->get('http://untrusted.test/sitemap.xml')->assertOk();

        $locations = $this->locations(simplexml_load_string($response->getContent()));
        $this->assertSame('https://audio.example.test/', $locations[0]);
        $this->assertContains('https://audio.example.test/collection/audio%26video', $locations);
    }

    public function test_sitemap_reflects_product_menu_and_category_changes_without_rebuilding(): void
    {
        config(['app.url' => 'https://mrhommeaudio.com']);
        $product = Product::factory()->create();
        $category = Category::factory()->create(['slug' => 'old-category']);
        $menu = MegaMenu::factory()->create(['href' => '/collection/old-menu', 'groups' => [], 'featured_url' => null]);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/collection/old-menu', false);

        $product->update(['is_active' => false]);
        $category->update(['slug' => 'new-category']);
        $menu->update(['href' => '/collection/new-menu']);

        $response = $this->get('/sitemap.xml')->assertOk();

        $locations = $this->locations(simplexml_load_string($response->getContent()));
        $this->assertNotContains('https://mrhommeaudio.com/product/'.$product->id, $locations);
        $this->assertNotContains('https://mrhommeaudio.com/collection/old-category', $locations);
        $this->assertNotContains('https://mrhommeaudio.com/collection/old-menu', $locations);
        $this->assertContains('https://mrhommeaudio.com/collection/new-category', $locations);
        $this->assertContains('https://mrhommeaudio.com/collection/new-menu', $locations);
    }

    /** @return list<string> */
    private function locations(SimpleXMLElement $xml): array
    {
        return array_map(fn (SimpleXMLElement $location): string => (string) $location,
            $xml->xpath('//*[local-name()="loc"]'));
    }
}
