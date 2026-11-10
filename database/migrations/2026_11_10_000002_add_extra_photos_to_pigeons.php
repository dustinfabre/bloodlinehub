<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pigeon can have any number of photos: photo_url stays the main one, the rest are kept here in order
        Schema::table('pigeons', function (Blueprint $table) {
            $table->json('extra_photos')->nullable()->after('photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn('extra_photos');
        });
    }
};
