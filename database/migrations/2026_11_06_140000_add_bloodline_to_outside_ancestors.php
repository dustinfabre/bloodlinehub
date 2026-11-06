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
        // Bloodline of outside (non-loft) ancestors, shown on the pedigree cards like a loft bird's bloodline:
        // the sire and dam recorded on a pigeon, and the ancestors recorded behind them
        Schema::table('pigeons', function (Blueprint $table) {
            $table->string('sire_bloodline')->nullable()->after('sire_color');
            $table->string('dam_bloodline')->nullable()->after('dam_color');
        });

        Schema::table('pedigree_ancestors', function (Blueprint $table) {
            $table->string('bloodline')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn(['sire_bloodline', 'dam_bloodline']);
        });

        Schema::table('pedigree_ancestors', function (Blueprint $table) {
            $table->dropColumn('bloodline');
        });
    }
};
