<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction for Neon.tech pooling compatibility.
     */
    public $withinTransaction = false;

    /**
     * There is one paid plan, Premium; admins only change its prices and the length of its free trial. The row is
     * marked with key "premium". A plan made earlier becomes Premium (the first active one), and any others are hidden
     * but kept, so past payments still point at them.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            // Days of full Premium a new account gets free; 0 turns the trial off
            $table->unsignedSmallInteger('trial_days')->default(0)->after('duration_months');
        });

        // The free trial: once per account, separate from paid Premium (users.premium_until)
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('premium_until');
        });

        $premium = DB::table('plans')
            ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('duration_months')->orderBy('id')
            ->first();

        if ($premium) {
            DB::table('plans')->where('id', $premium->id)->update(['key' => 'premium', 'name' => 'Premium', 'trial_days' => 14]);
            DB::table('plans')->where('id', '!=', $premium->id)->update(['is_active' => false]);

            return;
        }

        // Not on sale until an admin sets its price in /admin/plans
        DB::table('plans')->insert([
            'key' => 'premium',
            'name' => 'Premium',
            'duration_months' => 12,
            'trial_days' => 14,
            'price_php' => 0,
            'price_usd' => null,
            'is_active' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'trial_days']);
        });
    }
};
