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
     */
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            // Check if we need to rename photos to photo_url
            $hasPhotos = Schema::hasColumn('pigeons', 'photos');
            if ($hasPhotos) {
                $table->renameColumn('photos', 'photo_url');
            }
            
            // Add pedigree_images if it doesn't exist
            if (!Schema::hasColumn('pigeons', 'pedigree_images')) {
                $table->json('pedigree_images')->nullable()->after($hasPhotos ? 'photo_url' : 'notes');
            }
        });
        
        // Update existing data: convert photos array to single photo URL
        // Get all pigeons with photos
        $pigeons = DB::table('pigeons')->whereNotNull('photo_url')->get();
        
        foreach ($pigeons as $pigeon) {
            $photos = json_decode($pigeon->photo_url, true);
            if (is_array($photos) && count($photos) > 0) {
                $firstPhoto = $photos[0];
                DB::table('pigeons')
                    ->where('id', $pigeon->id)
                    ->update(['photo_url' => $firstPhoto]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn('pedigree_images');
            $table->renameColumn('photo_url', 'photos');
        });
    }
};
