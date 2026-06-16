<?php

namespace App\Observers;

use App\Models\Order;
use Spatie\Activitylog\Models\Activity;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        activity()
            ->performedOn($order)
            ->event('created')
            ->log("Order {$order->order_number} was placed by customer.");
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;
            activity()
                ->performedOn($order)
                ->event('status_updated')
                ->log("Order {$order->order_number} status changed from {$oldStatus} to {$newStatus}.");
        }
    }
}
