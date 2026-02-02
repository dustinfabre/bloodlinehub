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
        Schema::create('pigeon_bloodline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bloodline_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // A pigeon can only have each bloodline once
            $table->unique(['pigeon_id', 'bloodline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pigeon_bloodline');
    }
};
