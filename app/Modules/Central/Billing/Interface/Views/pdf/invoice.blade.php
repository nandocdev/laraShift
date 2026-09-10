<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ strtoupper(substr($invoice->id, 0, 8)) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        .brand { color: #D96A27; }
        .muted { color: #6b7280; }
        .small { font-size: 11px; }
        .right { text-align: right; }
        .logo { font-size: 48px; font-weight: bold; color: #D96A27; line-height: 1; }
        .sender-name { font-size: 16px; color: #D96A27; font-weight: bold; }
        .section-title { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: bold; margin-bottom: 4px; }
        .amount-highlight { font-size: 26px; color: #D96A27; font-weight: bold; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table th { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: bold; text-align: left; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
        .items-table th.num, .items-table td.num { text-align: right; }
        .items-table td { padding: 12px 0; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .item-title { font-weight: bold; font-size: 11px; }
        .item-desc { font-size: 11px; color: #6b7280; }
        .totals-table { width: 250px; border-collapse: collapse; font-size: 11px; }
        .totals-table td { padding: 4px 0; }
        .total-final { font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 36px;">
        <tr>
            <td valign="top">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td valign="top" style="padding-right: 12px;"><span class="logo">{{ strtoupper(substr($platformName, 0, 1)) }}</span></td>
                        <td valign="top" class="small muted">
                            <div class="sender-name">{{ $platformName }}</div>
                            <div>{{ __('Payment receipt') }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td valign="top" class="small muted right">
                <div>{{ __('Issued') }}: {{ $invoice->issued_at?->format('d M, Y') ?? '—' }}</div>
                <div>{{ __('Paid') }}: {{ $invoice->paid_at?->format('d M, Y') ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr>
            <td valign="top" width="40%">
                <div class="section-title">{{ __('Billed to') }}</div>
                <strong>{{ $billedName }}</strong>
                <div class="small muted">{{ $billedEmail }}</div>
            </td>
            <td valign="top" width="30%">
                <div class="section-title">{{ __('Invoice number') }}</div>
                <strong>#{{ strtoupper(substr($invoice->id, 0, 8)) }}</strong>
                <div style="height: 12px;"></div>
                <div class="section-title">{{ __('Reference') }}</div>
                <strong>{{ $invoice->provider_invoice_id ?? substr($invoice->payment_id ?? '', 0, 8) }}</strong>
            </td>
            <td valign="top" width="30%" class="right">
                <div class="section-title">{{ __('Invoice of') }} ({{ $invoice->currency }})</div>
                <div class="amount-highlight">{{ number_format($invoice->amount_cents / 100, 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>{{ __('Item Detail') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Rate') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="item-title">{{ __('Workspace subscription') }}</div>
                    <div class="item-desc">{{ __('Paid via') }} {{ $gateway }}</div>
                </td>
                <td class="num">1</td>
                <td class="num">{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</td>
                <td class="num">{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 48px;">
        <tr>
            <td></td>
            <td width="250">
                <table class="totals-table" width="100%">
                    <tr>
                        <td>{{ __('Subtotal') }}</td>
                        <td class="right">{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</td>
                    </tr>
                    <tr>
                        <td class="total-final">{{ __('Total') }}</td>
                        <td class="right total-final">{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="small">
        <p><strong>{{ __('Thanks for the business.') }}</strong></p>
        <p class="muted">{{ __('This receipt confirms payment received.') }}</p>
    </div>
</body>
</html>
