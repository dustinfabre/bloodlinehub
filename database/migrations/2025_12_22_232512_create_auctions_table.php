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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->decimal('starting_price', 10, 2);
            $table->decimal('current_price', 10, 2);
            $table->decimal('increment', 10, 2)->default(10.00);
            $table->decimal('reserve_price', 10, 2)->nullable();
            $table->timestamp('deadline');
            $table->text('description')->nullable();
            $table->json('additional_photos')->nullable();
            $table->string('status')->default('active'); // active, ended, cancelled
            $table->foreignId('winning_bidder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
