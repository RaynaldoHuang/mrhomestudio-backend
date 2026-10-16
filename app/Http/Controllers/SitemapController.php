<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MegaMenu;
use App\Models\Product;
use Illuminate\Http\Response;
use XMLWriter;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $urls = array_fill_keys(['/', '/about', '/collection/all-products'], null);
        $inactiveCollections = [];

        foreach (Category::query()->select(['id', 'slug', 'is_active'])->orderBy('id')->cursor() as $category) {
            $path = '/collection/'.rawurlencode($category->slug);
            if ($category->is_active) {
                $urls[$path] = null;
            } else {
                $inactiveCollections[$path] = true;
            }
        }

        foreach (Product::query()->select(['id', 'updated_at'])->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereHas('productCategory', fn ($category) => $category->where('is_active', true)))
            ->orderBy('id')->cursor() as $product) {
            $urls['/product/'.$product->id] = $product->updated_at?->toAtomString();
        }

        foreach (MegaMenu::query()->select(['id', 'href', 'groups', 'featured_url'])->where('is_active', true)->orderBy('id')->cursor() as $menu) {
            $links = [$menu->href, $menu->featured_url];
            foreach ($menu->groups ?? [] as $group) {
                foreach ($group['links'] ?? [] as $link) {
                    $links[] = $link['href'] ?? null;
                }
            }

            foreach ($links as $href) {
                $path = $this->menuPath($href, $baseUrl);
                if ($path === null || isset($inactiveCollections[$path])) {
                    continue;
                }

                if (array_key_exists($path, $urls) || preg_match('~^/collection/[a-z0-9]+(?:-[a-z0-9]+)*$~D', $path)) {
                    $urls[$path] ??= null;
                }
            }
        }

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $path => $lastModified) {
            $xml->startElement('url');
            $xml->writeElement('loc', $baseUrl.$path);
            if ($lastModified !== null) {
                $xml->writeElement('lastmod', $lastModified);
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function menuPath(?string $href, string $baseUrl): ?string
    {
        if ($href === null || trim($href) === '' || str_starts_with($href, '//')) {
            return null;
        }

        $parts = parse_url($href);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['host'])) {
            if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || strtolower($parts['host']) !== strtolower(parse_url($baseUrl, PHP_URL_HOST))
                || ($parts['port'] ?? null) !== parse_url($baseUrl, PHP_URL_PORT)) {
                return null;
            }
        } elseif (isset($parts['scheme']) || ! str_starts_with($href, '/')) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
