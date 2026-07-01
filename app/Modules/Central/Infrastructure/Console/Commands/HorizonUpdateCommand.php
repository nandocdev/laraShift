<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Console\Commands;

use App\Modules\Central\Infrastructure\Services\HorizonQueueResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class HorizonUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:update-queues {--force : Force a restart even if no changes detected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect changes in the queue catalog and perform a soft restart of Horizon.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $currentQueues = HorizonQueueResolver::resolve();
        sort($currentQueues);

        $cacheKey = 'horizon_active_queues_list';
        $lastQueues = Cache::get($cacheKey, []);
        sort($lastQueues);

        if ($this->option('force') || $currentQueues !== $lastQueues) {
            $this->info('Changes detected in queue catalog or force flag used. Restarting Horizon...');

            Cache::forever($cacheKey, $currentQueues);

            Artisan::call('horizon:terminate');

            $this->info('Horizon termination signal sent. It should restart automatically via supervisor.');
        } else {
            $this->info('No changes detected in queue catalog.');
        }
    }
}
