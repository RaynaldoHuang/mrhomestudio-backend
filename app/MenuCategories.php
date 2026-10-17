<?php

namespace App;

use App\Models\Category;
use App\Models\MegaMenu;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuCategories
{
    public static function collectionSlug(string $href): ?string
    {
        if (! preg_match('~^/collection/([^/?#]+)/?(?:[?#].*)?$~', trim($href), $matches)) {
            return null;
        }

        $slug = Str::slug(rawurldecode($matches[1]));

        return $slug !== '' && $slug !== 'all-products' ? $slug : null;
    }

    /** @param array<int, array<string, mixed>> $groups
     * @return array<int, array<string, mixed>>
     */
    public function link(array $groups, ?MegaMenu $currentMenu = null): array
    {
        $categories = Category::query()->get();
        $labels = [];
        $otherMenus = MegaMenu::query()->when($currentMenu, fn ($query) => $query->whereKeyNot($currentMenu->id))->get(['id', 'groups']);
        $allGroups = $otherMenus->flatMap(fn (MegaMenu $menu): array => $menu->groups ?? [])->merge($groups);
        foreach ($allGroups as $group) {
            foreach ($group['links'] ?? [] as $link) {
                if (isset($link['category_id'])) {
                    $labels[(int) $link['category_id']][] = Str::lower(trim($link['label']));
                }
            }
        }

        foreach ($groups as &$group) {
            $group['title'] = $group['title'] ?? '';
            foreach ($group['links'] as &$link) {
                $slug = self::collectionSlug($link['href']);
                if ($slug === null) {
                    unset($link['category_id']);

                    continue;
                }

                if (Str::length($slug) > 255) {
                    throw ValidationException::withMessages(['groups' => ['Collection URLs must have a slug of at most 255 characters.']]);
                }

                $name = trim($link['label']);
                $normalizedName = Str::lower($name);
                $category = $categories->first(fn (Category $item): bool => Str::lower($item->name) === $normalizedName);
                if ($category === null) {
                    $candidate = isset($link['category_id']) ? $categories->firstWhere('id', (int) $link['category_id']) : null;
                    $candidate ??= $categories->firstWhere('slug', $slug);
                    if ($candidate !== null && count(array_diff($labels[$candidate->id] ?? [], [$normalizedName])) === 0) {
                        $category = $candidate;
                        $category->update(['name' => $name]);
                    }
                }
                if ($category === null) {
                    $baseSlug = $categories->contains('slug', $slug) ? (Str::slug($name) ?: 'category') : $slug;
                    $slug = $baseSlug;
                    $suffix = 2;
                    while ($categories->contains('slug', $slug)) {
                        $slug = $baseSlug.'-'.$suffix++;
                    }
                    $category = Category::query()->create([
                        'name' => $name, 'slug' => $slug,
                        'sort_order' => ((int) $categories->max('sort_order')) + 1,
                        'show_on_home' => false, 'is_active' => true,
                    ]);
                    $categories->push($category);
                }

                $labels[$category->id][] = $normalizedName;
                $link['category_id'] = $category->id;
                $suffix = strpbrk($link['href'], '?#') ?: '';
                $link['href'] = '/collection/'.$category->slug.$suffix;
            }
            unset($link);
        }
        unset($group);

        return $groups;
    }

    /** @return array<int, array<int, string>> */
    public function references(): array
    {
        $references = [];
        foreach (MegaMenu::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'label', 'groups', 'display_mode']) as $menu) {
            foreach ($menu->groups ?? [] as $group) {
                foreach ($group['links'] ?? [] as $link) {
                    if (isset($link['category_id'])) {
                        $references[(int) $link['category_id']][] = ($menu->display_mode === 'direct' || empty($group['title'])) ? $menu->label : $menu->label.' / '.$group['title'];
                    }
                }
            }
        }

        return array_map(fn (array $groups): array => array_values(array_unique($groups)), $references);
    }
}
