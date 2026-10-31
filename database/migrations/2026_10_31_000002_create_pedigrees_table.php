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
        Schema::create('pedigrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('serial', 20)->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedigrees');
    }
};
