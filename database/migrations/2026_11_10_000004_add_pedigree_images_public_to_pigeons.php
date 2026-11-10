<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which certificates and relatives' photos the owner shows on the pedigree (a list of their URLs)
        Schema::table('pigeons', function (Blueprint $table) {
            $table->json('pedigree_images_public')->nullable()->after('pedigree_image_relations');
        });

        // Until now every photo marked as a relative was shown and every certificate was not: keep it that way
        DB::table('pigeons')
            ->whereNotNull('pedigree_image_relations')
            ->select(['id', 'pedigree_images', 'pedigree_image_relations'])
            ->chunkById(200, function ($pigeons) {
                foreach ($pigeons as $pigeon) {
                    $images = json_decode($pigeon->pedigree_images ?? '[]', true) ?: [];
                    $relations = json_decode($pigeon->pedigree_image_relations, true) ?: [];
                    $public = array_values(array_intersect($images, array_keys($relations)));

                    DB::table('pigeons')
                        ->where('id', $pigeon->id)
                        ->update(['pedigree_images_public' => $public ? json_encode($public) : null]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn('pedigree_images_public');
        });
    }
};
