<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mega_menus', function (Blueprint $table) {
            $table->string('display_mode', 16)->default('grouped');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mega_menus', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }
};
