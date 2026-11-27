<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('sku', 60)->nullable();
            $table->string('short_description', 300)->nullable();
            $table->text('description')->nullable();       // HTML, bereinigt
            $table->unsignedInteger('price_cents');          // Bruttopreis
            $table->unsignedInteger('compare_price_cents')->nullable(); // "statt"-Preis
            $table->decimal('tax_rate', 4, 1)->default(19);
            $table->integer('stock')->nullable();            // null = unbegrenzt
            $table->decimal('base_quantity', 10, 3)->nullable(); // Grundpreis nach PAngV, z. B. 0.5
            $table->string('base_unit', 8)->nullable();          // kg, l, m …
            $table->string('delivery_time', 60)->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('token', 40)->unique();
            $table->string('status', 20)->default('new')->index(); // new, processing, shipped, completed, cancelled
            $table->string('payment_method', 30);
            $table->string('payment_status', 20)->default('open'); // open, paid, failed
            $table->string('payment_reference', 120)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('email');
            $table->string('name', 160);
            $table->string('company', 160)->nullable();
            $table->string('street', 160);
            $table->string('zip', 12);
            $table->string('city', 120);
            $table->string('country', 2);
            $table->string('phone', 60)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('shipping_cents');
            $table->unsignedInteger('total_cents');
            $table->json('taxes')->nullable();              // [{"rate":19,"cents":123}]
            $table->boolean('small_business')->default(false);
            $table->string('tracking_number', 80)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('sku', 60)->nullable();
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('quantity');
            $table->decimal('tax_rate', 4, 1);
            $table->unsignedInteger('total_cents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
