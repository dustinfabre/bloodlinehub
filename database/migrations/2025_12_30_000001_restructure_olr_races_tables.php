<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Drop old pivot table
        Schema::dropIfExists('olr_race_pigeon');

        // Drop old olr_races table
        Schema::dropIfExists('olr_races');

        // Create new olr_races (top-level OLR race/organization)
        Schema::create('olr_races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('organizer')->nullable();
            $table->string('location')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });

        // Create olr_seasons table
        Schema::create('olr_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olr_race_id')->constrained('olr_races')->cascadeOnDelete();
            $table->string('name');
            $table->integer('year');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->timestamps();
        });

        // Create olr_season_entries pivot table (pigeons entered in a season)
        Schema::create('olr_season_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olr_season_id')->constrained('olr_seasons')->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained('pigeons')->cascadeOnDelete();
            $table->string('entry_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['olr_season_id', 'pigeon_id']);
        });

        // Create olr_season_races table (individual races within a season)
        Schema::create('olr_season_races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olr_season_id')->constrained('olr_seasons')->cascadeOnDelete();
            $table->string('name');
            $table->string('release_point')->nullable();
            $table->decimal('distance', 10, 2)->nullable();
            $table->string('distance_unit')->default('km');
            $table->date('race_date')->nullable();
            $table->time('release_time')->nullable();
            $table->string('weather_conditions')->nullable();
            $table->string('wind_direction')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Create olr_race_results table (results per pigeon per race)
        Schema::create('olr_race_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olr_season_race_id')->constrained('olr_season_races')->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained('pigeons')->cascadeOnDelete();
            $table->integer('position')->nullable();
            $table->time('arrival_time')->nullable();
            $table->decimal('speed', 12, 4)->nullable(); // m/min with precision
            $table->text('notes')->nullable();
            $table->boolean('did_not_arrive')->default(false);
            $table->timestamps();

            $table->unique(['olr_season_race_id', 'pigeon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olr_race_results');
        Schema::dropIfExists('olr_season_races');
        Schema::dropIfExists('olr_season_entries');
        Schema::dropIfExists('olr_seasons');
        Schema::dropIfExists('olr_races');
    }
};
