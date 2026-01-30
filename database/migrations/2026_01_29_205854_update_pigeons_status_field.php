<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Disable transaction for Neon.tech pooling compatibility.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     * 
     * Merges 'status' and 'pigeon_status' into a single 'status' field with new values:
     * - stock (was pigeon_status=stock + status=alive)
     * - racing (was pigeon_status=racing + status=alive)
     * - breeding (was pigeon_status=breeding + status=alive)
     * - injured (new)
     * - deceased (was status=deceased)
     * - flyaway (was status=flyaway)
     * - missing (was status=missing)
     */
    public function up(): void
    {
        // Step 1: Migrate data to new status values
        // Map old pigeon_status + status combinations to new status values
        
        // Alive pigeons: use pigeon_status value
        DB::table('pigeons')
            ->where('status', 'alive')
            ->where('pigeon_status', 'stock')
            ->update(['status' => 'stock']);
            
        DB::table('pigeons')
            ->where('status', 'alive')
            ->where('pigeon_status', 'racing')
            ->update(['status' => 'racing']);
            
        DB::table('pigeons')
            ->where('status', 'alive')
            ->where('pigeon_status', 'breeding')
            ->update(['status' => 'breeding']);
        
        // Deceased, missing, flyaway: keep as-is (already correct values)
        
        // Step 2: Drop the pigeon_status and race_type columns
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn(['pigeon_status', 'race_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Re-add the columns
        Schema::table('pigeons', function (Blueprint $table) {
            $table->string('pigeon_status')->default('stock');
            $table->string('race_type')->default('none');
        });
        
        // Step 2: Migrate data back
        // Active statuses go to pigeon_status with status=alive
        DB::table('pigeons')
            ->where('status', 'stock')
            ->update(['pigeon_status' => 'stock', 'status' => 'alive']);
            
        DB::table('pigeons')
            ->where('status', 'racing')
            ->update(['pigeon_status' => 'racing', 'status' => 'alive']);
            
        DB::table('pigeons')
            ->where('status', 'breeding')
            ->update(['pigeon_status' => 'breeding', 'status' => 'alive']);
            
        DB::table('pigeons')
            ->where('status', 'injured')
            ->update(['pigeon_status' => 'stock', 'status' => 'alive']);
        
        // Inactive statuses: set pigeon_status to stock
        DB::table('pigeons')
            ->whereIn('status', ['deceased', 'missing', 'flyaway'])
            ->update(['pigeon_status' => 'stock']);
    }
};
