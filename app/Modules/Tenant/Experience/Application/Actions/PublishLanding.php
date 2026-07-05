<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Actions;

use App\Modules\Tenant\Experience\Domain\Models\Landing;
use App\Modules\Tenant\Experience\Domain\Models\LandingVersion;
use Illuminate\Support\Facades\DB;

final class PublishLanding
{
    public function __construct(
        private RenderLanding $renderAction
    ) {}

    /**
     * Publishes a landing page by rendering its HTML and saving a version snapshot.
     */
    public function execute(Landing $landing, ?string $publisherId = null): Landing
    {
        return DB::transaction(function () use ($landing, $publisherId) {
            // 1. Render HTML
            $html = $this->renderAction->execute($landing);

            // 2. Create version snapshot
            LandingVersion::create([
                'landing_id' => $landing->id,
                'tenant_id' => $landing->tenant_id,
                'blocks_snapshot' => $landing->blocks,
                'theme_snapshot' => $landing->theme,
                'published_by' => $publisherId,
                'created_at' => now(),
            ]);

            // 3. Update Landing record
            $landing->update([
                'status' => 'published',
                'published_html' => $html,
                'published_at' => now(),
            ]);

            return $landing;
        });
    }
}
