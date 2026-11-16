<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The language the quote's PDF and mail are written in: "nl" or "en".
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('locale', 5)->default('nl')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
