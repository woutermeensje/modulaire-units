<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The brand of an offered used unit (e.g. Containex or Moravia), filled in freely by the seller and optional.
     */
    public function up(): void
    {
        Schema::table('used_listings', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('used_listings', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
