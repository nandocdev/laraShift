<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Illuminate\Support\Str;

class GlobalAnnouncements extends Component
{
    /**
     * Dismisses a specific broadcast for the current user.
     */
    public function dismiss(string $broadcastId): void
    {
        if (! auth()->check()) return;

        DB::table('broadcast_dismissals')->updateOrInsert(
            [
                'broadcast_id' => $broadcastId,
                'user_id' => auth()->id(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => tenant('id'),
                'dismissed_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function render(): View
    {
        $tenant = tenant();
        
        if (! $tenant) {
            return view('support::livewire.global-announcements', ['activeBroadcasts' => collect()]);
        }

        // Fetch broadcasts that:
        // 1. Have 'banner' in channels
        // 2. Are sent (sent_at is not null)
        // 3. Match tenant filters (all, same plan, or same status)
        // 4. Have NOT been dismissed by this user
        
        $dismissedIds = DB::table('broadcast_dismissals')
            ->where('user_id', auth()->id())
            ->pluck('broadcast_id');

        $activeBroadcasts = Broadcast::whereNotNull('sent_at')
            ->whereJsonContains('channels', 'banner')
            ->where(function ($query) use ($tenant) {
                $query->where('filter_type', 'all')
                    ->orWhere(function ($q) use ($tenant) {
                        $q->where('filter_type', 'plan')->where('filter_value', $tenant->plan_id);
                    })
                    ->orWhere(function ($q) use ($tenant) {
                        $q->where('filter_type', 'status')->where('filter_value', $tenant->status);
                    });
            })
            ->whereNotIn('id', $dismissedIds)
            ->latest()
            ->get();

        return view('support::livewire.global-announcements', [
            'activeBroadcasts' => $activeBroadcasts,
        ]);
    }
}
