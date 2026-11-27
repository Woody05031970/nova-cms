<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Version 0.7: Terminbuchung, Mehrsprachigkeit, Gutscheine, Mitgliederbereich, Warenwirtschaft */
return new class extends Migration
{
    public function up(): void
    {
        // Terminbuchung
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration')->default(30); // Minuten
            $table->unsignedSmallInteger('buffer')->default(0);    // Pause danach, Minuten
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name', 120);
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('name', 160);
            $table->string('email');
            $table->string('phone', 60)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending'); // pending, confirmed, cancelled
            $table->string('token', 40)->unique();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        // Mehrsprachigkeit und Mitgliederbereich
        Schema::table('pages', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->index();          // null = Hauptsprache
            $table->foreignId('translation_of')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('visibility', 10)->default('public');       // public, members
        });

        // Gutscheine und Rabatte
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('kind', 10)->default('percent'); // percent, fixed, shipping
            $table->unsignedInteger('value')->default(0);    // Prozent oder Cent
            $table->unsignedInteger('min_order_cents')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->boolean('is_gift')->default(false);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // Kauf des Geschenkgutscheins
            $table->string('note', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('discount_cents')->default(0);
            $table->string('coupon_code', 40)->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_voucher')->default(false);
        });

        // Mitgliederbereich: Dateien nur für Mitglieder
        Schema::create('member_files', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        // Kundenkonto: Kontaktdaten, Rechnungs- und Lieferadresse; Bestellungen dem Konto zuordnen
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 60)->nullable();
            $table->string('company', 160)->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('billing')->nullable(); // abweichende Rechnungsadresse
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn('billing');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'company', 'billing_address', 'shipping_address']));
        Schema::dropIfExists('member_files');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('is_voucher'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['discount_cents', 'coupon_code']));
        Schema::dropIfExists('coupons');
        Schema::table('pages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('translation_of');
            $t->dropColumn(['locale', 'visibility']);
        });
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('services');
    }
};
