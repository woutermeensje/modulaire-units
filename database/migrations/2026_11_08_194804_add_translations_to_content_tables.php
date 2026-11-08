<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose content is translated for the English site on /en/.
     *
     * @var list<string>
     */
    private const TABLES = [
        'pages', 'posts', 'units', 'products', 'vacancies', 'categories', 'authors', 'unit_terms', 'menu_items', 'team_members',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('translations')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
    }
};
