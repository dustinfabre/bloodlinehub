<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Related loft birds (ids) the owner shows as notable relatives below this bird's pedigree
        Schema::table('pigeons', function (Blueprint $table) {
            $table->json('featured_relatives')->nullable()->after('pedigree_images_public');
        });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn('featured_relatives');
        });
    }
};
