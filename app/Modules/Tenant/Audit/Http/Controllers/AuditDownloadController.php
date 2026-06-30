<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Http\Controllers;

use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditDownloadController extends Controller
{
    /**
     * Downloads an audit log file or tenant data file securely.
     * Enforces path prefix validation to prevent traversal and cross-tenant access.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $path = $request->query('path');
        $tenantId = tenant('id');

        // Security Strategy: Explicitly allow only specific prefixes per tenant.
        $allowedPrefixes = [
            "exports/audit/audit_log_{$tenantId}",
            "exports/tenant_data_{$tenantId}",
        ];

        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with((string) $path, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            Log::warning('Unauthorized file access attempt detected', [
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'requested_path' => $path,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Unauthorized path.');
        }

        if (! Storage::disk('private')->exists((string) $path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('private')->download((string) $path);
    }
}
