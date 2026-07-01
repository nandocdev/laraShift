<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Jobs;

use App\Modules\Tenant\DataManagement\Models\DataImport;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $importId,
        public array $records,
        public string $type,
        public bool $overwrite = false,
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $import = DataImport::find($this->importId);

            if (! $import) {
                return;
            }

            $import->update(['status' => 'processing']);

            $result = match ($this->type) {
                'users' => $this->importUsers(),
                'settings' => $this->importSettings(),
                default => throw new \InvalidArgumentException(__("Unknown import type: {$this->type}")),
            };

            $import->update([
                'status' => $result['success'] ? 'completed' : 'completed_with_errors',
                'summary' => [
                    'total' => $result['total'],
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'errors' => $result['errors_count'],
                ],
                'errors' => $result['errors'],
            ]);

            Log::info('Data import completed', [
                'tenant_id' => $this->tenantId,
                'type' => $this->type,
                'imported' => $result['imported'],
                'errors' => $result['errors_count'],
            ]);
        } catch (\Throwable $e) {
            DataImport::where('id', $this->importId)->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage()]],
            ]);

            Log::error('Data import failed', [
                'tenant_id' => $this->tenantId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);
        } finally {
            tenancy()->end();
        }
    }

    private function importUsers(): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($this->records as $index => $record) {
            try {
                if (empty($record['email'])) {
                    $skipped++;

                    continue;
                }

                $existing = User::where('email', $record['email'])->first();

                if ($existing && ! $this->overwrite) {
                    $skipped++;

                    continue;
                }

                $data = [
                    'tenant_id' => $this->tenantId,
                    'name' => $record['name'] ?? $record['email'],
                    'email' => $record['email'],
                    'password' => isset($record['password'])
                        ? (Hash::needsRehash($record['password']) ? Hash::make($record['password']) : $record['password'])
                        : Hash::make(Str::random(32)),
                ];

                $data['id'] = Str::uuid()->toString();

                if ($existing && $this->overwrite) {
                    $existing->update($data);
                } else {
                    User::create($data);
                }

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $index,
                    'email' => $record['email'] ?? 'unknown',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => empty($errors),
            'total' => count($this->records),
            'imported' => $imported,
            'skipped' => $skipped,
            'errors_count' => count($errors),
            'errors' => $errors,
        ];
    }

    private function importSettings(): array
    {
        $imported = 0;
        $errors = [];

        foreach ($this->records as $index => $record) {
            try {
                $validKeys = ['timezone', 'locale', 'currency', 'mfa_required'];

                $data = array_intersect_key($record, array_flip($validKeys));

                if (empty($data)) {
                    $errors[] = ['row' => $index, 'message' => 'No valid setting keys provided.'];

                    continue;
                }

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $this->tenantId],
                    $data,
                );

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index, 'message' => $e->getMessage()];
            }
        }

        return [
            'success' => empty($errors),
            'total' => count($this->records),
            'imported' => $imported,
            'skipped' => 0,
            'errors_count' => count($errors),
            'errors' => $errors,
        ];
    }
}
