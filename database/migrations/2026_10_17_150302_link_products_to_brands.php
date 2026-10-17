<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
        });

        DB::table('products')->whereNotNull('brand')->orderBy('id')->chunkById(200, function ($products): void {
            foreach ($products as $product) {
                $name = preg_replace('/\s+/u', ' ', trim($product->brand));
                if ($name === '') {
                    continue;
                }
                $normalizedName = mb_strtolower($name);
                $brandId = DB::table('brands')->where('normalized_name', $normalizedName)->value('id');
                if ($brandId === null) {
                    $brandId = DB::table('brands')->insertGetId([
                        'name' => $name, 'normalized_name' => $normalizedName, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('products')->where('id', $product->id)->update(['brand_id' => $brandId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
