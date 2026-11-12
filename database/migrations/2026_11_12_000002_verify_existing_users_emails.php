<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email verification is now required (User implements MustVerifyEmail). Accounts made before it never got a link, so
 * they are counted as verified rather than locked out. New accounts and email changes must verify.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Which accounts were marked here isn't recorded, so there is nothing to undo
    }
};
