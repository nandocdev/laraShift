<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes tenant export files older than 24h from the private disk.
 * Exports are delivered through 24h signed URLs (UC-T-09): anything older
 * is undeliverable and must not accumulate on storage.
 */
class PruneExportsCommand extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete tenant export files older than 24 hours';

    public function handle(): int
    {
        $disk = Storage::disk('private');
        $cutoff = now()->subHours(24);
        $deleted = 0;

        foreach (['exports', 'exports/audit'] as $directory) {
            if (! $disk->exists($directory)) {
                continue;
            }

            foreach ($disk->allFiles($directory) as $file) {
                if ($disk->lastModified($file) < $cutoff->timestamp) {
                    $disk->delete($file);
                    $deleted++;
                }
            }
        }

        $this->info("Pruned {$deleted} expired export file(s).");

        return self::SUCCESS;
    }
}
