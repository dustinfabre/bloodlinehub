<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Flyaway and missing meant the same thing to fanciers (the bird did not come home), so they are one status now:
     * missing.
     */
    public function up(): void
    {
        DB::table('pigeons')->where('status', 'flyaway')->update(['status' => 'missing']);
    }

    /**
     * The merged birds can't be told apart from the ones that were already missing, so nothing is undone.
     */
    public function down(): void
    {
        //
    }
};
