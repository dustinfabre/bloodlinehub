<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('sire_id')->constrained('pigeons')->onDelete('cascade');
            $table->foreignId('dam_id')->constrained('pigeons')->onDelete('cascade');
            $table->string('pair_name')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('current_clutch_number')->default(1);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            // Add indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['sire_id', 'dam_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pairings');
    }
};
