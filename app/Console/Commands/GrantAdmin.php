<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Gives an account access to /admin (or takes it away with --revoke). There is deliberately no form for this.
 */
class GrantAdmin extends Command
{
    protected $signature = 'admin:grant
        {email : Email of the account}
        {--revoke : Remove admin access instead}';

    protected $description = 'Give an account access to the admin dashboard, or remove it with --revoke';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();

        if (! $user) {
            $this->error('No account uses that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info($this->option('revoke')
            ? "{$user->email} is no longer an admin."
            : "{$user->email} is now an admin. They can open /admin.");

        return self::SUCCESS;
    }
}
