<?php

namespace Database\Seeders;

use App\MenuCategories;
use App\Models\MegaMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MegaMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(MenuCategories $menuCategories): void
    {
        $menus = [
            [
                'label' => 'Speakers',
                'href' => '/collection/speakers',
                'groups' => [
                    $this->group('By Category', ['Hi-Fi Speakers', 'Wireless Speakers', 'Soundbars', 'Architectural', 'Outdoor Speakers']),
                    $this->group('By Type', ['Floorstanding', 'Bookshelf', 'Centre Channel', 'In-Ceiling', 'In-Wall']),
                    $this->group('By Series', ['Reference Series', 'Signature Series', 'Studio Series', 'Cinema Series', 'Wireless Collection']),
                ],
                'featured_title' => 'Wireless sound, beautifully at home',
                'sort_order' => 1,
            ],
            [
                'label' => 'Subwoofers',
                'href' => '/collection/subwoofers',
                'groups' => [
                    $this->group('By Type', ['Active Subwoofers', 'Home Cinema', 'Compact Subwoofers', 'Architectural', 'Wireless']),
                    $this->group('By Room', ['Living Room', 'Media Room', 'Dedicated Cinema', 'Studio', 'Multi-Room']),
                    $this->group('By Series', ['Reference Series', 'Performance Series', 'Cinema Series', 'Compact Series', 'Installation Series']),
                ],
                'featured_title' => 'Deep impact with absolute control',
                'sort_order' => 2,
            ],
            [
                'label' => 'Headphones',
                'href' => '/collection/headphones',
                'groups' => [
                    $this->group('By Type', ['Over-Ear', 'In-Ear', 'Wireless', 'Noise Cancelling', 'Open-Back']),
                    $this->group('By Use', ['Home Listening', 'Travel', 'Studio', 'Gaming', 'Everyday Audio']),
                    $this->group('Collections', ['Reference', 'Signature', 'Wireless', 'Studio Monitor', 'Limited Edition']),
                ],
                'featured_title' => 'Private listening without compromise',
                'sort_order' => 3,
            ],
            [
                'label' => 'Accessories',
                'href' => '/collection/accessories',
                'groups' => [
                    $this->group('Audio', ['Amplifiers', 'Streamers', 'DACs', 'Turntables', 'Phono Stages']),
                    $this->group('Installation', ['Speaker Stands', 'Wall Mounts', 'Cables', 'Connectors', 'Remote Controls']),
                    $this->group('Shop By', ['New Arrivals', 'Best Sellers', 'Upgrades', 'Replacement Parts', 'Special Offers']),
                ],
                'featured_title' => 'Complete your listening system',
                'sort_order' => 4,
            ],
        ];

        foreach ($menus as $menu) {
            DB::transaction(function () use ($menu, $menuCategories): void {
                $menu['groups'] = $menuCategories->link($menu['groups']);
                MegaMenu::query()->updateOrCreate(
                    ['label' => $menu['label']],
                    [
                        ...$menu,
                        'featured_url' => $menu['href'],
                        'featured_image_alt' => $menu['featured_title'],
                        'is_active' => true,
                    ],
                );
            });
        }
    }

    /**
     * @param  array<int, string>  $labels
     * @return array{title: string, links: array<int, array{label: string, href: string}>}
     */
    private function group(string $title, array $labels): array
    {
        return [
            'title' => $title,
            'links' => array_map(fn (string $label): array => [
                'label' => $label,
                'href' => '/collection/'.str($label)->slug(),
            ], $labels),
        ];
    }
}
