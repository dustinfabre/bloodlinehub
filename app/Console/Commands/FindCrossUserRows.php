<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds rows linking one user's records to another user's, which the unscoped routes and validation fixed on
 * 2026-11-06 allowed. By default it only reports. With --fix it removes the cross-user links (entries, results,
 * bloodline links, sale listings) and clears foreign clutch references. Pairings made from another user's birds
 * are only listed: they belong to the user who made them, so review them before deleting.
 *
 * Not detectable: seasons renamed or races/events created inside another user's season (those rows have no
 * record of who changed them).
 */
class FindCrossUserRows extends Command
{
    protected $signature = 'security:cross-user-rows
        {--fix : Remove the cross-user links and clear foreign clutch references}
        {--details : List the ids of every row found}';

    protected $description = 'Find (and optionally remove) rows that link one user\'s data to another user\'s';

    public function handle(): int
    {
        $checks = $this->checks();
        $rows = [];
        $found = 0;

        foreach ($checks as $name => $check) {
            $ids = $check['query']()->pluck('id')->all();
            $found += count($ids);
            $rows[] = [$name, count($ids), count($ids) ? $check['action'] : '-'];

            if ($ids && $this->option('details')) {
                $this->line("<comment>{$name}</comment>: ".implode(', ', $ids));
            }
        }

        $this->table(['Check', 'Rows', 'With --fix'], $rows);

        if (! $found) {
            $this->info('No cross-user rows found.');

            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->warn("{$found} cross-user rows found. Run again with --fix to clean up (pairings are only listed).");

            return self::FAILURE;
        }

        DB::transaction(function () use ($checks) {
            foreach ($checks as $name => $check) {
                if (! isset($check['fix'])) {
                    continue;
                }

                $ids = $check['query']()->pluck('id')->all();

                if ($ids) {
                    $check['fix']($ids);
                    $this->line("Fixed {$name}: ".count($ids));
                }
            }
        });

        $this->info('Done. Review the pairings listed above by hand.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{query: callable(): Builder, action: string, fix?: callable(array<int, int>): void}>
     */
    private function checks(): array
    {
        $seasonLinks = [
            'OLR season entries' => ['olr_season_entries', null, 'olr_season_id', 'olr_seasons', 'olr_race_id', 'olr_races'],
            'OLR race results' => ['olr_race_results', ['olr_season_race_id', 'olr_season_races', 'olr_season_id'], null, 'olr_seasons', 'olr_race_id', 'olr_races'],
            'Club season entries' => ['club_season_entries', null, 'club_season_id', 'club_seasons', 'club_id', 'clubs'],
            'Club race results' => ['club_race_results', ['club_season_race_id', 'club_season_races', 'club_season_id'], null, 'club_seasons', 'club_id', 'clubs'],
        ];

        $checks = [];

        foreach ($seasonLinks as $name => [$table, $race, $seasonFk, $seasons, $ownerFk, $owners]) {
            $query = function () use ($table, $race, $seasonFk, $seasons, $ownerFk, $owners) {
                $query = DB::table("{$table} as link")->select('link.id');

                if ($race) {
                    [$raceFk, $races, $raceSeasonFk] = $race;
                    $query->join("{$races} as race", 'race.id', '=', "link.{$raceFk}")
                        ->join("{$seasons} as season", 'season.id', '=', "race.{$raceSeasonFk}");
                } else {
                    $query->join("{$seasons} as season", 'season.id', '=', "link.{$seasonFk}");
                }

                return $query
                    ->join("{$owners} as owner", 'owner.id', '=', "season.{$ownerFk}")
                    ->join('pigeons as bird', 'bird.id', '=', 'link.pigeon_id')
                    ->whereColumn('bird.user_id', '!=', 'owner.user_id');
            };

            $checks[$name] = [
                'query' => $query,
                'action' => 'delete',
                'fix' => fn (array $ids) => DB::table($table)->whereIn('id', $ids)->delete(),
            ];
        }

        $checks['Club event entries'] = [
            'query' => fn () => DB::table('club_season_event_entries as link')
                ->select('link.id')
                ->join('club_season_events as event', 'event.id', '=', 'link.club_season_event_id')
                ->join('club_seasons as season', 'season.id', '=', 'event.club_season_id')
                ->join('clubs as owner', 'owner.id', '=', 'season.club_id')
                ->join('pigeons as bird', 'bird.id', '=', 'link.pigeon_id')
                ->whereColumn('bird.user_id', '!=', 'owner.user_id'),
            'action' => 'delete',
            'fix' => fn (array $ids) => DB::table('club_season_event_entries')->whereIn('id', $ids)->delete(),
        ];

        $checks['Bloodline links'] = [
            'query' => fn () => DB::table('pigeon_bloodline as link')
                ->select('link.id')
                ->join('pigeons as bird', 'bird.id', '=', 'link.pigeon_id')
                ->join('bloodlines as line', 'line.id', '=', 'link.bloodline_id')
                ->whereColumn('bird.user_id', '!=', 'line.user_id'),
            'action' => 'delete',
            'fix' => fn (array $ids) => DB::table('pigeon_bloodline')->whereIn('id', $ids)->delete(),
        ];

        $checks['Sale listings for another user\'s bird'] = [
            'query' => fn () => DB::table('sales as sale')
                ->select('sale.id')
                ->join('pigeons as bird', 'bird.id', '=', 'sale.pigeon_id')
                ->whereColumn('bird.user_id', '!=', 'sale.user_id'),
            'action' => 'delete',
            'fix' => fn (array $ids) => DB::table('sales')->whereIn('id', $ids)->delete(),
        ];

        $checks['Foster clutches pointing at another user\'s pairing'] = [
            'query' => fn () => DB::table('clutches as clutch')
                ->select('clutch.id')
                ->join('pairings as pairing', 'pairing.id', '=', 'clutch.pairing_id')
                ->join('pairings as biological', 'biological.id', '=', 'clutch.biological_pairing_id')
                ->whereColumn('pairing.user_id', '!=', 'biological.user_id'),
            'action' => 'clear biological pairing',
            'fix' => fn (array $ids) => DB::table('clutches')->whereIn('id', $ids)->update(['biological_pairing_id' => null]),
        ];

        $checks['Pigeons in another user\'s clutch'] = [
            'query' => fn () => DB::table('pigeons as bird')
                ->select('bird.id')
                ->join('clutches as clutch', 'clutch.id', '=', 'bird.clutch_id')
                ->join('pairings as pairing', 'pairing.id', '=', 'clutch.pairing_id')
                ->whereColumn('bird.user_id', '!=', 'pairing.user_id'),
            'action' => 'clear clutch',
            'fix' => fn (array $ids) => DB::table('pigeons')->whereIn('id', $ids)->update(['clutch_id' => null]),
        ];

        $checks['Pairings made from another user\'s birds'] = [
            'query' => fn () => DB::table('pairings as pairing')
                ->select('pairing.id')
                ->leftJoin('pigeons as sire', 'sire.id', '=', 'pairing.sire_id')
                ->leftJoin('pigeons as dam', 'dam.id', '=', 'pairing.dam_id')
                ->where(fn ($query) => $query
                    ->whereColumn('sire.user_id', '!=', 'pairing.user_id')
                    ->orWhereColumn('dam.user_id', '!=', 'pairing.user_id')),
            'action' => 'list only: review by hand',
        ];

        return $checks;
    }
}
