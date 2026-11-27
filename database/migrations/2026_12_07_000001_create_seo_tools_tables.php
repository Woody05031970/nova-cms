<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Version 0.5: Mitbewerber-Analyse, Weiterleitungen und Protokoll nicht gefundener Seiten */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500);
            $table->string('name')->nullable();
            $table->json('data')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique();
            $table->string('to_url', 500);
            $table->unsignedSmallInteger('status')->default(301);
            $table->boolean('automatic')->default(false);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500)->unique();
            $table->string('referer', 500)->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('competitors');
    }
};
