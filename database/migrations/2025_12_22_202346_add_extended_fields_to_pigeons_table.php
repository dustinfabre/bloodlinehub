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
        Schema::table('pigeons', function (Blueprint $table) {
            $table->string('name')->nullable()->after('color');
            $table->enum('gender', ['male', 'female'])->nullable()->after('name');
            $table->date('hatch_date')->nullable()->after('gender');
            $table->enum('status', ['alive', 'deceased', 'missing'])->default('alive')->after('hatch_date');
            $table->enum('pigeon_status', ['racing', 'breeding', 'stock'])->default('stock')->after('status');
            $table->enum('race_type', ['south', 'north', 'summer', 'olr', 'none'])->default('none')->after('pigeon_status');
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
