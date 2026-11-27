<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oeffnungszeiten', function (Blueprint $table) {
            $table->unsignedTinyInteger('wochentag')->primary();
            $table->string('von', 5)->nullable();
            $table->string('bis', 5)->nullable();
            $table->boolean('geschlossen')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oeffnungszeiten');
    }
};
