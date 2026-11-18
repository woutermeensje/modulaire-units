<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sections of the technical specification the admin unticked: their text is kept, but not put in the PDF.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->json('hidden_specifications')->nullable()->after('specifications');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('hidden_specifications');
        });
    }
};
