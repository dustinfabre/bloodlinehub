<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction for Neon.tech pooling compatibility.
     */
    public $withinTransaction = false;

    private const RACE_TABLES = ['olr_season_races', 'club_season_races'];

    private const ENTRY_TABLES = ['olr_season_entries', 'club_season_entries'];

    public function up(): void
    {
        // What merits can be suggested from a race depends on its type (App\Enums\RaceType) and field size
        foreach (self::RACE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('race_type', 16)->default('regular');
                $table->unsignedInteger('birds_released')->nullable();
            });
        }

        // Final overall standing in the season (derby / OLR overall, club ace list)
        foreach (self::ENTRY_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('overall_position')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::RACE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['race_type', 'birds_released']);
            });
        }

        foreach (self::ENTRY_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('overall_position');
            });
        }
    }
};
