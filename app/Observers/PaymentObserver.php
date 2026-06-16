<?php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        activity()
            ->performedOn($payment)
            ->event('payment_created')
            ->log("Payment of IDR " . number_format($payment->amount, 2) . " via {$payment->payment_method} was successfully processed for Order {$payment->order->order_number}.");
    }
}
