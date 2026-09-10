<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

enum BillingEventType: string
{
    case CheckoutCompleted = 'checkout.completed';

    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case PaymentPending = 'payment.pending';

    case SubscriptionCreated = 'subscription.created';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionCanceled = 'subscription.canceled';

    case InvoiceCreated = 'invoice.created';
    case InvoicePaid = 'invoice.paid';
    case InvoiceFailed = 'invoice.failed';

    case RefundCreated = 'refund.created';
}
