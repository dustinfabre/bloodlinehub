<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_season_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_season_id')->constrained('club_seasons')->cascadeOnDelete();
            $table->string('name');
            $table->date('event_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('club_season_event_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_season_event_id')->constrained('club_season_events')->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained('pigeons')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['club_season_event_id', 'pigeon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_season_event_entries');
        Schema::dropIfExists('club_season_events');
    }
};
