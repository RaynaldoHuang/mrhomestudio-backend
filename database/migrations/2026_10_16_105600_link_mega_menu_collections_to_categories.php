<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $categories = DB::table('categories')->orderBy('id')->get();
            foreach (DB::table('mega_menus')->orderBy('sort_order')->orderBy('id')->get() as $menu) {
                $groups = json_decode($menu->groups ?? '[]', true, flags: JSON_THROW_ON_ERROR);
                foreach ($groups as &$group) {
                    foreach ($group['links'] ?? [] as $index => $link) {
                        if (! preg_match('~^/collection/([^/?#]+)/?(?:[?#].*)?$~', trim($link['href'] ?? ''), $matches)) {
                            continue;
                        }
                        $slug = Str::slug(rawurldecode($matches[1]));
                        if ($slug === '' || $slug === 'all-products') {
                            continue;
                        }
                        $category = isset($link['category_id']) ? $categories->firstWhere('id', (int) $link['category_id']) : null;
                        $category ??= $categories->firstWhere('slug', $slug);
                        $category ??= $categories->first(fn (object $item): bool => Str::lower($item->name) === Str::lower(trim($link['label'])));
                        if ($category === null) {
                            $id = DB::table('categories')->insertGetId([
                                'name' => trim($link['label']), 'slug' => $slug,
                                'sort_order' => ((int) $categories->max('sort_order')) + 1,
                                'show_on_home' => false, 'is_active' => true,
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $category = DB::table('categories')->find($id);
                            $categories->push($category);
                        }
                        $group['links'][$index]['category_id'] = $category->id;
                        $group['links'][$index]['href'] = '/collection/'.$category->slug.(strpbrk($link['href'], '?#') ?: '');
                        DB::table('products')->whereNull('category_id')
                            ->whereIn('category', [$category->name, $link['label']])
                            ->update(['category_id' => $category->id]);
                    }
                }
                unset($group);
                DB::table('mega_menus')->where('id', $menu->id)->update(['groups' => json_encode($groups, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void
    {
        // Keep categories and product assignments: they may have been edited after import.
    }
};
