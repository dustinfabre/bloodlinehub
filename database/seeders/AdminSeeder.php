<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The admin account. Change PASSWORD below, then run:
 *
 *     php artisan db:seed --class=AdminSeeder
 *
 * Running it again resets the account's password to PASSWORD and keeps it an admin, so it also recovers a lost
 * admin password. Admins get every Premium feature without paying (User::hasPremium()).
 */
class AdminSeeder extends Seeder
{
    public const EMAIL = 'admin@bloodlinehub.com';

    public const NAME = 'BloodlineHub Admin';

    // Change this before running the seeder. It is refused in production while it is still this placeholder.
    public const PASSWORD = 'change-this-password';

    public function run(): void
    {
        if (self::PASSWORD === 'change-this-password' && app()->isProduction()) {
            throw new RuntimeException('Set AdminSeeder::PASSWORD to a strong password before seeding the admin in production.');
        }

        $user = User::firstOrNew(['email' => self::EMAIL]);

        $user->forceFill([
            'name' => $user->exists ? $user->name : self::NAME,
            'password' => self::PASSWORD,
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->command?->info(self::EMAIL.' is an admin. Log in and open /admin.');
    }
}
