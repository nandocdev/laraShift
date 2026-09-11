<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Enums;

enum BillingEventType: string
{
    case CheckoutCompleted = 'checkout.completed';
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionCanceled = 'subscription.canceled';
    case InvoicePaid = 'invoice.paid';
    case InvoiceFailed = 'invoice.failed';
    case RefundCreated = 'refund.created';
}
