<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $t) {
            $t->boolean('show_title')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn('show_title'));
    }
};
