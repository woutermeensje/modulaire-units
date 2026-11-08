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
        Schema::table('units', function (Blueprint $table) {
            $table->string('price_rent_week')->nullable()->after('price_buy');
            $table->string('price_buy_min')->nullable()->after('price_rent_week');
            $table->string('price_buy_max')->nullable()->after('price_buy_min');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['price_rent_week', 'price_buy_min', 'price_buy_max']);
        });
    }
};
