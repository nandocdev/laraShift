<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\SendBroadcastAction;
use App\Modules\Central\Support\DTOs\BroadcastData;
use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class BroadcastCenter extends Component
{
    use WithPagination;

    // Form state
    public string $title = '';

    public string $body = '';

    public string $filterType = 'all';

    public string $filterValue = '';

    public string $tenantSlugs = '';

    public string $scheduledAt = '';

    public array $channels = ['email'];

    public function send(SendBroadcastAction $action): void
    {
        $this->dispatchBroadcast($action, false, null);
    }

    public function schedule(SendBroadcastAction $action): void
    {
        $this->validate(['scheduledAt' => 'required|date|after:now']);

        $this->dispatchBroadcast($action, false, $this->scheduledAt);
    }

    public function saveDraft(SendBroadcastAction $action): void
    {
        $this->dispatchBroadcast($action, true, null);
    }

    public function publishDraft(string $id, SendBroadcastAction $action): void
    {
        $broadcast = Broadcast::find($id);

        if (! $broadcast || ! $broadcast->is_draft) {
            return;
        }

        $broadcast->update(['is_draft' => false]);
        $action->sendNow($broadcast->fresh());

        session()->flash('status', __('Broadcast published.'));
    }

    public function unschedule(string $id): void
    {
        $broadcast = Broadcast::find($id);

        if (! $broadcast || $broadcast->sent_at) {
            return;
        }

        $broadcast->update(['scheduled_at' => null, 'is_draft' => true]);

        activity('support')
            ->performedOn($broadcast)
            ->log('broadcast_unscheduled');

        session()->flash('status', __('Scheduled broadcast moved back to draft.'));
    }

    public function deleteDraft(string $id): void
    {
        $broadcast = Broadcast::find($id);

        if (! $broadcast || ! $broadcast->is_draft) {
            return;
        }

        $broadcast->delete();

        session()->flash('status', __('Draft deleted.'));
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    #[Computed]
    public function plans(): array
    {
        try {
            return Plan::where('is_active', true)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn ($plan) => ['slug' => $plan->slug, 'name' => $plan->name])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    #[Computed]
    public function recipientEstimate(): int
    {
        try {
            return $this->audienceQuery()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function render(): View
    {
        return view('support::pages.broadcast-center', [
            'broadcasts' => Broadcast::with('creator')->latest()->paginate(10),
        ]);
    }

    private function dispatchBroadcast(SendBroadcastAction $action, bool $draft, ?string $scheduledAt): void
    {
        $this->validate($this->rules($draft));

        try {
            $action->execute(new BroadcastData(
                title: $this->title,
                body: $this->body,
                filterType: $this->filterType,
                filterValue: $this->filterValue ?: null,
                channels: $this->channels,
                tenantIds: $this->resolveTenantIds(),
                scheduledAt: $scheduledAt,
                draft: $draft,
            ));

            $this->reset(['title', 'body', 'filterType', 'filterValue', 'tenantSlugs', 'scheduledAt', 'channels']);
            session()->flash('status', __($draft ? 'Draft saved.' : ($scheduledAt ? 'Broadcast scheduled.' : 'Broadcast sent successfully.')));
        } catch (\Exception $e) {
            $this->addError('title', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $draft): array
    {
        $filterValueRule = match ($this->filterType) {
            'status' => 'required|in:provisioning,active,pending_payment,past_due,suspended,quarantine,archived,failed,expired',
            'plan' => 'required|string|exists:plans,slug',
            'selected' => 'nullable',
            default => 'nullable',
        };

        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'filterType' => 'required|in:all,status,plan,selected',
            'filterValue' => $filterValueRule,
            'tenantSlugs' => $this->filterType === 'selected' ? 'required|string|max:2000' : 'nullable',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,banner',
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        if ($this->filterType !== 'selected') {
            return [];
        }

        $slugs = collect(explode(',', $this->tenantSlugs))
            ->map(fn ($slug) => trim($slug))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->validate(['tenantSlugs' => 'required']);

        $ids = Tenant::whereIn('slug', $slugs)->pluck('id')->all();

        if (count($ids) !== count($slugs)) {
            $missing = implode(', ', array_diff($slugs, Tenant::whereIn('slug', $slugs)->pluck('slug')->all()));
            throw new \InvalidArgumentException(__('Unknown tenant slugs: :slugs', ['slugs' => $missing]));
        }

        return $ids;
    }

    private function audienceQuery(): Builder
    {
        $query = Tenant::query();

        if ($this->filterType === 'status' && $this->filterValue !== '') {
            $query->where('status', $this->filterValue);
        }

        if ($this->filterType === 'plan' && $this->filterValue !== '') {
            $query->where('plan_id', $this->filterValue);
        }

        if ($this->filterType === 'selected') {
            $slugs = collect(explode(',', $this->tenantSlugs))
                ->map(fn ($slug) => trim($slug))
                ->filter()
                ->all();
            $query->whereIn('slug', $slugs);
        }

        return $query;
    }
}
