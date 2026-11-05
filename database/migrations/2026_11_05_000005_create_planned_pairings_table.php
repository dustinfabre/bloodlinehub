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

    public function up(): void
    {
        // Test pairs saved for the next breeding season (the breeding plan)
        Schema::create('planned_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sire_id')->constrained('pigeons')->cascadeOnDelete();
            $table->foreignId('dam_id')->constrained('pigeons')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sire_id', 'dam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_pairings');
    }
};
