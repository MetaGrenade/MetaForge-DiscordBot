<?php

namespace MetaForge\Discord\Console\Commands;

use Illuminate\Console\Command;
use MetaForge\Discord\Services\DiscordSyncFailureService;

class RetryDiscordFailuresCommand extends Command
{
    protected $signature = 'discord:retry-failures
                            {--limit=50 : Maximum unresolved failures to retry}
                            {--dry-run : List retries without dispatching jobs}';

    protected $description = 'Re-dispatch jobs for unresolved Discord sync failures (dead-letter retry).';

    public function handle(DiscordSyncFailureService $service): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $counts = $service->retryUnresolved($limit, $dryRun);

        $verb = $dryRun ? 'Would retry' : 'Retried';
        $this->info(sprintf(
            '%s %d failure(s); skipped %d.',
            $verb,
            $counts['retried'],
            $counts['skipped'],
        ));

        return self::SUCCESS;
    }
}
