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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('club_races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('club_name')->nullable();
            $table->string('release_point')->nullable();
            $table->decimal('distance', 10, 2)->nullable(); // Distance in km/miles
            $table->string('distance_unit')->default('km'); // km or miles
            $table->date('race_date')->nullable();
            $table->time('release_time')->nullable();
            $table->text('description')->nullable();
            $table->string('weather_conditions')->nullable();
            $table->string('wind_direction')->nullable();
            $table->string('status')->default('upcoming'); // upcoming, completed, cancelled
            $table->timestamps();
        });

        // Pivot table for pigeons participating in club races
        Schema::create('club_race_pigeon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_race_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->time('arrival_time')->nullable();
            $table->decimal('speed', 10, 4)->nullable(); // meters per minute or yards per minute
            $table->integer('position')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['club_race_id', 'pigeon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_race_pigeon');
        Schema::dropIfExists('club_races');
    }
};
