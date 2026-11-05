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
        // Merit suggestions the breeder chose not to add, so they stop being suggested.
        // suggestion is "{type}:{source}", e.g. "finisher:olr_season:12".
        Schema::create('merit_suggestion_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->string('suggestion', 96);
            $table->timestamps();

            $table->unique(['pigeon_id', 'suggestion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_suggestion_dismissals');
    }
};
