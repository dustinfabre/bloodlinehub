<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('color_tag_id')
                ->constrained('locations')
                ->nullOnDelete();
        });

        Schema::table('pairings', function (Blueprint $table) {
            $table->foreignId('breeding_location_id')
                ->nullable()
                ->after('ended_at')
                ->constrained('locations')
                ->nullOnDelete();
        });

        Schema::table('clutches', function (Blueprint $table) {
            $table->foreignId('success_location_id')
                ->nullable()
                ->after('notes')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clutches', function (Blueprint $table) {
            $table->dropForeign(['success_location_id']);
            $table->dropColumn('success_location_id');
        });

        Schema::table('pairings', function (Blueprint $table) {
            $table->dropForeign(['breeding_location_id']);
            $table->dropColumn('breeding_location_id');
        });

        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
