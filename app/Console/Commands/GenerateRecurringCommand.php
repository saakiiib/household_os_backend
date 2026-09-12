<?php

namespace App\Console\Commands;

use App\Services\RecurringGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Auto-create the next occurrence for recurring tasks and renewals.
 *
 * Runs from the scheduler, e.g.:
 *   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
 *
 * See App\Services\RecurringGenerator for the (backlog-skipping) logic.
 */
class GenerateRecurringCommand extends Command
{
    protected $signature = 'recurring:generate';
    protected $description = 'Auto-create next occurrence for recurring tasks and renewals';

    public function handle(): int
    {
        // The server scheduler may occasionally overlap (slow DB, two cron
        // invocations, deploy, etc.). Recurring generation must be single-run
        // because two generators racing can create duplicate child items.
        if (!Cache::add('recurring-generate-running', true, 240)) {
            \Log::info('[GenerateRecurring] Run skipped — already running.');
            $this->info('Recurring generation already running — skipping.');
            return Command::SUCCESS;
        }

        try {
            $result = RecurringGenerator::runAll();
        } finally {
            Cache::forget('recurring-generate-running');
        }

        $message = "Generated {$result['tasks']} task(s) and {$result['renewals']} renewal(s).";
        $this->info($message);
        \Log::info('[GenerateRecurring] ' . $message);

        return Command::SUCCESS;
    }
}
