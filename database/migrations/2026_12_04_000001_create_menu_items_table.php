<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 16)->default('page'); // page, link, shop
            $table->foreignId('page_id')->nullable()->constrained('pages')->cascadeOnDelete();
            $table->string('label', 80)->nullable();     // leer = Seitentitel
            $table->string('url', 500)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'position']);
        });

        // Bisheriges Menü übernehmen: alle Seiten mit "Im Menü anzeigen" in der alten Reihenfolge
        $pages = DB::table('pages')->whereNull('deleted_at')->where('show_in_menu', true)
            ->orderByDesc('is_home')->orderBy('menu_order')->orderBy('title')->pluck('id');
        $now = now();
        foreach ($pages->values() as $i => $id) {
            DB::table('menu_items')->insert(['type' => 'page', 'page_id' => $id, 'position' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
