<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Pro-forma Invoice') }} {{ $invoice->number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            line-height: 1.5;
            font-size: 14px;
        }
        .header {
            margin-bottom: 40px;
        }
        .header table {
            width: 100%;
        }
        .logo {
            max-width: 200px;
        }
        .platform-name {
            font-size: 24px;
            font-weight: bold;
            color: {{ $primaryColor }};
        }
        .invoice-title {
            text-align: right;
            font-size: 28px;
            font-weight: bold;
            text-transform: uppercase;
            color: {{ $primaryColor }};
        }
        .info-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .info-table td {
            vertical-align: top;
            width: 50%;
        }
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 2px solid {{ $primaryColor }};
            display: inline-block;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .items-table th {
            background-color: {{ $primaryColor }};
            color: white;
            padding: 10px;
            text-align: left;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .totals {
            width: 300px;
            float: right;
        }
        .totals table {
            width: 100%;
        }
        .totals td {
            padding: 5px 0;
        }
        .total-row {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid {{ $primaryColor }};
        }
        .footer {
            margin-top: 100px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-open { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="logo">
                    @else
                        <span class="platform-name">{{ $platformName }}</span>
                    @endif
                </td>
                <td class="invoice-title">
                    {{ __('PRO-FORMA') }}
                </td>
            </tr>
        </table>
    </div>

    <table class="info-table">
        <tr>
            <td>
                <div class="section-title">{{ __('From') }}</div>
                <div><strong>{{ $platformName }}</strong></div>
                <div>{{ __('SaaS Platform Administration') }}</div>
            </td>
            <td style="text-align: right;">
                <div class="section-title">{{ __('Bill To') }}</div>
                <div><strong>{{ $tenant->name }}</strong></div>
                <div>{{ $tenant->email }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <div><strong>{{ __('Invoice Number') }}:</strong> {{ $invoice->number }}</div>
                <div><strong>{{ __('Date') }}:</strong> {{ $invoice->created_at->format('Y-m-d') }}</div>
            </td>
            <td style="text-align: right;">
                <div><strong>{{ __('Period') }}:</strong> {{ $invoice->period_start->format('Y-m-d') }} - {{ $invoice->period_end->format('Y-m-d') }}</div>
                <div>
                    <strong>{{ __('Status') }}:</strong>
                    <span class="status-badge {{ $invoice->status === 'paid' ? 'status-paid' : 'status-open' }}">
                        {{ strtoupper($invoice->status) }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th style="text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Subscription to') }} {{ strtoupper($tenant->plan_id) }} {{ __('Plan') }}</td>
                <td style="text-align: right;">{{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>{{ __('Subtotal') }}</td>
                <td style="text-align: right;">{{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($invoice->amount) }}</td>
            </tr>
            <tr class="total-row">
                <td>{{ __('Total') }}</td>
                <td style="text-align: right;">{{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($invoice->amount) }}</td>
            </tr>
            @if($invoice->status === 'paid')
                <tr>
                    <td>{{ __('Amount Paid') }}</td>
                    <td style="text-align: right;">{{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($invoice->amount) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        <p>{{ __('Thank you for choosing') }} {{ $platformName }}.</p>
        <p>{{ __('This is a computer-generated pro-forma invoice.') }}</p>
    </div>
</body>
</html>
