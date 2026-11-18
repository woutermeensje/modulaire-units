<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything a quote needs besides the composed unit: sale or rent, project details, the technical specification per
     * section, price lines with their status, terms, open points and internal notes. See units.md.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('kind', 10)->default('sale')->after('locale');
            $table->unsignedSmallInteger('rental_weeks')->nullable()->after('kind');
            $table->string('reference')->nullable()->after('rental_weeks');
            $table->string('delivery_address')->nullable()->after('reference');
            $table->string('delivery_date', 100)->nullable()->after('delivery_address');
            $table->date('valid_until')->nullable()->after('delivery_date');
            $table->json('specifications')->nullable()->after('configuration');
            $table->json('lines')->nullable()->after('specifications');
            $table->text('terms')->nullable()->after('lines');
            $table->text('open_points')->nullable()->after('terms');
            $table->text('internal_notes')->nullable()->after('open_points');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['kind', 'rental_weeks', 'reference', 'delivery_address', 'delivery_date', 'valid_until', 'specifications', 'lines', 'terms', 'open_points', 'internal_notes']);
        });
    }
};
