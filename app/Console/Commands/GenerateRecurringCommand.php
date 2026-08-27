<?php

namespace App\Console\Commands;

use App\Services\RecurringGenerator;
use Illuminate\Console\Command;

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
        $result = RecurringGenerator::runAll();

        $message = "Generated {$result['tasks']} task(s) and {$result['renewals']} renewal(s).";
        $this->info($message);
        \Log::info('[GenerateRecurring] ' . $message);

        return Command::SUCCESS;
    }
}
