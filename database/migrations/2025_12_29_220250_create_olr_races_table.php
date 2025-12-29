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
        Schema::create('olr_races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('organizer')->nullable();
            $table->string('location')->nullable();
            $table->string('country')->nullable();
            $table->integer('year')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->timestamps();
        });

        // Pivot table for pigeons participating in OLR races
        Schema::create('olr_race_pigeon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olr_race_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->string('entry_number')->nullable(); // Entry/team number in the race
            $table->string('result')->nullable(); // Final position or result
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['olr_race_id', 'pigeon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olr_race_pigeon');
        Schema::dropIfExists('olr_races');
    }
};
