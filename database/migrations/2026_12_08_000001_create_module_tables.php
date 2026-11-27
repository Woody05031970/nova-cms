<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Version 0.6: Social Media, Redaktionsplan und Besucherstatistik */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('network', 20); // facebook, instagram
            $table->text('text');
            $table->string('link', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->string('external_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('editorial_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->string('channel', 20)->default('website'); // website, newsletter, facebook, instagram, linkedin, aktion
            $table->text('notes')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->index('date');
        });

        Schema::create('stat_views', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('path', 300);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('mobile')->default(0);
            $table->unique(['day', 'path']);
        });

        Schema::create('stat_sources', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('kind', 10); // referrer, search
            $table->string('value', 200);
            $table->unsignedInteger('hits')->default(0);
            $table->unique(['day', 'kind', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_sources');
        Schema::dropIfExists('stat_views');
        Schema::dropIfExists('editorial_items');
        Schema::dropIfExists('social_posts');
    }
};
