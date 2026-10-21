<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives /transport-plaatsing/ the service page layout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Page::query()->where('path', 'transport-plaatsing')->where('template', 'default')->update(['template' => 'service']);
    }

    public function down(): void
    {
        Page::query()->where('path', 'transport-plaatsing')->where('template', 'service')->update(['template' => 'default']);
    }
};
