<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
        });

        $names = DB::table('products')->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category');
        foreach ($names as $name) {
            $baseSlug = Str::slug($name) ?: 'category';
            if ($baseSlug === 'all-products') {
                $baseSlug = 'category-all-products';
            }
            $slug = $baseSlug;
            $suffix = 2;
            while (DB::table('categories')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }
            $categoryId = DB::table('categories')->insertGetId([
                'name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('products')->where('category', $name)->update(['category_id' => $categoryId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
