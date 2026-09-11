<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Infrastructure\Console;

use App\Modules\Central\Support\Actions\SendBroadcastAction;
use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Console\Command;

class DispatchDueBroadcastsCommand extends Command
{
    protected $signature = 'broadcasts:dispatch-due';

    protected $description = 'Dispatch scheduled broadcasts whose time has come';

    public function handle(SendBroadcastAction $action): int
    {
        $due = Broadcast::where('is_draft', false)
            ->whereNull('sent_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $broadcast) {
            $action->sendNow($broadcast);
        }

        $this->info("Dispatched {$due->count()} broadcast(s).");

        return self::SUCCESS;
    }
}
