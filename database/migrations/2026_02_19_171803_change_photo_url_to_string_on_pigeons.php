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
        // First, extract string values from any JSON-encoded URLs
        $pigeons = DB::table('pigeons')->whereNotNull('photo_url')->get();
        foreach ($pigeons as $pigeon) {
            $url = $pigeon->photo_url;
            // If it's valid JSON, decode it
            $decoded = json_decode($url, true);
            if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
                DB::table('pigeons')
                    ->where('id', $pigeon->id)
                    ->update(['photo_url' => DB::raw("'" . addslashes($decoded) . "'::text")]);
            }
        }

        // Change column type from json to text/string
        DB::statement('ALTER TABLE pigeons ALTER COLUMN photo_url TYPE TEXT USING photo_url::text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE pigeons ALTER COLUMN photo_url TYPE JSON USING photo_url::json');
    }
};
