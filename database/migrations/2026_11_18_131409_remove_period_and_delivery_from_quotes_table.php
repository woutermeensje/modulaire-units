<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rental period, delivery address, requested delivery and validity are not asked in a quote any more.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['rental_weeks', 'delivery_address', 'delivery_date', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedSmallInteger('rental_weeks')->nullable()->after('kind');
            $table->string('delivery_address')->nullable()->after('reference');
            $table->string('delivery_date', 100)->nullable()->after('delivery_address');
            $table->date('valid_until')->nullable()->after('delivery_date');
        });
    }
};
