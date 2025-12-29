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
        Schema::table('pigeons', function (Blueprint $table) {
            $table->foreignId('sire_id')
                ->nullable()
                ->constrained('pigeons')
                ->nullOnDelete();
            $table->foreignId('dam_id')
                ->nullable()
                ->constrained('pigeons')
                ->nullOnDelete();

            $table->string('sire_name')->nullable();
            $table->string('sire_ring_number')->nullable();
            $table->string('sire_color')->nullable();
            $table->text('sire_notes')->nullable();

            $table->string('dam_name')->nullable();
            $table->string('dam_ring_number')->nullable();
            $table->string('dam_color')->nullable();
            $table->text('dam_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropForeign(['sire_id']);
            $table->dropForeign(['dam_id']);

            $table->dropColumn([
                'sire_id',
                'dam_id',
                'sire_name',
                'sire_ring_number',
                'sire_color',
                'sire_notes',
                'dam_name',
                'dam_ring_number',
                'dam_color',
                'dam_notes',
            ]);
        });
    }
};
