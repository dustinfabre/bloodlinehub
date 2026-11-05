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
        // Merits from a fixed vocabulary (App\Enums\MeritType), unlike user-defined color tags.
        // A bird can hold the same merit more than once (e.g. Race Winner in 2023 and 2024).
        Schema::create('pigeon_merits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('level', 32)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('detail')->nullable();
            // Where a merit suggested from race results came from, e.g. "olr_season:12" or "club_race:40"; null if entered by hand
            $table->string('source', 64)->nullable();
            $table->timestamps();

            $table->index(['pigeon_id', 'type']);
            $table->index('type');
            $table->index(['pigeon_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pigeon_merits');
    }
};
