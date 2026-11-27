<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabellen des Lizenzservers. Sie bleiben leer, solange eine Installation nicht als Lizenzserver arbeitet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('customer_name', 160)->nullable();
            $table->string('customer_email')->nullable()->index();
            $table->boolean('core')->default(true);        // Grundlizenz für Nova CMS
            $table->json('modules')->nullable();             // Slugs gekaufter Module
            $table->unsignedSmallInteger('max_domains')->default(1);
            $table->date('updates_until')->nullable();
            $table->string('status', 20)->default('active'); // active, revoked
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issued_license_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 190);
            $table->string('install_id', 64);
            $table->string('version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['issued_license_id', 'install_id']);
        });

        Schema::create('market_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('category', 40)->nullable();
            $table->string('version', 32);
            $table->string('requires', 32)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('buy_url', 500)->nullable();
            $table->string('file', 255);
            $table->char('sha256', 64);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('license_spec')->nullable(); // {"core": true, "modules": ["..."], "max_domains": 1}
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('license_spec'));
        Schema::dropIfExists('market_modules');
        Schema::dropIfExists('license_activations');
        Schema::dropIfExists('issued_licenses');
    }
};
