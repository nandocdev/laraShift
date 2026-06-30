<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class SyncInvoicesAction
{
    public function execute(Tenant $tenant): int
    {
        $gatewayInvoices = app(BillingManager::class)->getInvoices($tenant);
        $syncedCount = 0;

        foreach ($gatewayInvoices as $gatewayInvoice) {
            // Determine source format and map to local model
            if ($this->mapAndStore($tenant, $gatewayInvoice)) {
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    private function mapAndStore(Tenant $tenant, mixed $data): bool
    {
        return DB::transaction(function () use ($tenant, $data) {
            // 1. Stripe (Cashier) format
            if ($data instanceof \Laravel\Cashier\Invoice) {
                $invoice = Invoice::updateOrCreate(
                    ['provider_invoice_id' => $data->id],
                    [
                        'tenant_id' => $tenant->id,
                        'amount' => $data->amount_due,
                        'currency' => $data->currency,
                        'status' => $data->status === 'paid' ? 'paid' : 'pending',
                        'issued_at' => Carbon::createFromTimestamp($data->created),
                    ]
                );

                return $invoice->wasRecentlyCreated || $invoice->wasChanged();
            }

            // 2. PagueloFacil (Clave) format
            // Keys based on Discovery: codOper, totalPay, date, status
            if (isset($data['codOper'])) {
                $invoice = Invoice::updateOrCreate(
                    ['provider_invoice_id' => $data['codOper']],
                    [
                        'tenant_id' => $tenant->id,
                        'amount' => (int) ((float) $data['totalPay'] * 100),
                        'currency' => 'USD',
                        'status' => (isset($data['status']) && $data['status'] == 1) ? 'paid' : 'pending',
                        'issued_at' => Carbon::parse($data['date']),
                    ]
                );

                return $invoice->wasRecentlyCreated || $invoice->wasChanged();
            }

            // 3. dLocal format
            if (isset($data['payment_id'])) {
                $invoice = Invoice::updateOrCreate(
                    ['provider_invoice_id' => (string) $data['payment_id']],
                    [
                        'tenant_id' => $tenant->id,
                        'amount' => (int) ($data['amount'] * 100),
                        'currency' => $data['currency'] ?? 'USD',
                        'status' => ($data['status'] ?? '') === 'PAID' ? 'paid' : 'pending',
                        'issued_at' => Carbon::parse($data['created_date'] ?? now()),
                    ]
                );

                return $invoice->wasRecentlyCreated || $invoice->wasChanged();
            }

            return false;
        });
    }
}
