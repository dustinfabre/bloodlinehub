<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Breeding locations get their own tab on the breeding page
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('is_breeding')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('is_breeding');
        });
    }
};
