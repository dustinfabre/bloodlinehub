<?php

namespace App\Console\Commands;

use App\Models\InviteRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Lists the emails left on the "request an invite" page, newest first. With --since=YYYY-MM-DD only requests
 * made on or after that day are shown.
 */
class ListInviteRequests extends Command
{
    protected $signature = 'invites:list
        {--since= : Only requests made on or after this date (YYYY-MM-DD)}';

    protected $description = 'List the emails that requested an invite, newest first';

    public function handle(): int
    {
        $since = $this->option('since');
        $query = InviteRequest::query()->latest()->latest('id');

        if ($since !== null) {
            if (Validator::make(['since' => $since], ['since' => 'date_format:Y-m-d'])->fails()) {
                $this->error('--since must be a date in the form YYYY-MM-DD.');

                return self::FAILURE;
            }

            $query->where('created_at', '>=', Carbon::parse($since)->startOfDay());
        }

        $requests = $query->get(['email', 'created_at']);

        if ($requests->isNotEmpty()) {
            $this->table(
                ['Email', 'Requested at'],
                $requests->map(fn (InviteRequest $invite) => [
                    $invite->email,
                    $invite->created_at?->format('Y-m-d H:i'),
                ])->all(),
            );
        }

        $this->info($requests->count().' invite '.str('request')->plural($requests->count()).($since !== null ? " since {$since}" : '').'.');

        return self::SUCCESS;
    }
}
