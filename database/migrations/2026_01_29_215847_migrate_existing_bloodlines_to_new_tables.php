<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Migrate existing bloodline data from pigeons.bloodline column
     * to the new bloodlines and pigeon_bloodline tables.
     */
    public function up(): void
    {
        // Get all unique user/bloodline combinations from pigeons
        $existingBloodlines = DB::table('pigeons')
            ->select('user_id', 'bloodline')
            ->whereNotNull('bloodline')
            ->where('bloodline', '!=', '')
            ->distinct()
            ->get();

        // Create entries in bloodlines table for each unique bloodline per user
        foreach ($existingBloodlines as $item) {
            $existingRecord = DB::table('bloodlines')
                ->where('user_id', $item->user_id)
                ->whereRaw('UPPER(name) = ?', [strtoupper($item->bloodline)])
                ->first();

            if (!$existingRecord) {
                DB::table('bloodlines')->insert([
                    'user_id' => $item->user_id,
                    'name' => strtoupper($item->bloodline),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create pigeon_bloodline relationships for all pigeons with bloodlines
        $pigeonsWithBloodlines = DB::table('pigeons')
            ->select('id', 'user_id', 'bloodline')
            ->whereNotNull('bloodline')
            ->where('bloodline', '!=', '')
            ->get();

        foreach ($pigeonsWithBloodlines as $pigeon) {
            $bloodline = DB::table('bloodlines')
                ->where('user_id', $pigeon->user_id)
                ->whereRaw('UPPER(name) = ?', [strtoupper($pigeon->bloodline)])
                ->first();

            if ($bloodline) {
                // Check if relationship already exists
                $exists = DB::table('pigeon_bloodline')
                    ->where('pigeon_id', $pigeon->id)
                    ->where('bloodline_id', $bloodline->id)
                    ->exists();

                if (!$exists) {
                    DB::table('pigeon_bloodline')->insert([
                        'pigeon_id' => $pigeon->id,
                        'bloodline_id' => $bloodline->id,
                        'is_primary' => true, // Mark as primary since it was the only bloodline
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all pigeon_bloodline entries
        DB::table('pigeon_bloodline')->truncate();
        
        // Remove all bloodlines entries
        DB::table('bloodlines')->truncate();
    }
};
