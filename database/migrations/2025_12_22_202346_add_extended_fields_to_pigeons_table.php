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
            $table->string('name')->nullable();
            $table->string('gender')->nullable(); // male, female
            $table->date('hatch_date')->nullable();
            $table->string('status')->default('alive'); // alive, deceased, missing
            $table->string('pigeon_status')->default('stock'); // racing, breeding, stock
            $table->string('race_type')->default('none'); // south, north, summer, olr, none
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'gender',
                'hatch_date',
                'status',
                'pigeon_status',
                'race_type',
            ]);
        });
    }
};
