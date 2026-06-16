<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Generate sequential order number based on outlet and date.
     */
    public function generateOrderNumber(int $outletId): string
    {
        $outlet = Outlet::findOrFail($outletId);

        $prefix = 'OUT';
        $slug = Str::slug($outlet->name);
        if (str_contains($slug, 'galaxy')) {
            $prefix = 'GLX';
        } elseif (str_contains($slug, 'bekasi')) {
            $prefix = 'BKS';
        }

        $dateStr = now()->format('Ymd');

        // Count orders created today for this outlet
        $todayCount = Order::where('outlet_id', $outletId)
            ->whereDate('created_at', today())
            ->count();

        $sequence = sprintf('%03d', $todayCount + 1);

        return "{$prefix}-{$dateStr}-{$sequence}";
    }

    /**
     * Create order from the current cart session.
     */
    public function checkout(?string $customerName = null): Order
    {
        $tableSession = $this->cartService->getActiveTableSession();
        if (! $tableSession) {
            throw new \Exception('No active QR table session found.');
        }

        $items = $this->cartService->get();
        if (empty($items)) {
            throw new \Exception('Cart is empty.');
        }

        $outletId = $tableSession->table->outlet_id;
        $tableId = $tableSession->table->id;
        $subtotal = $this->cartService->total();

        // Calculate tax (10%) and service charge (5%)
        $taxAmount = round($subtotal * 0.10, 2);
        $serviceCharge = round($subtotal * 0.05, 2);
        $totalAmount = $subtotal + $taxAmount + $serviceCharge;

        return DB::transaction(function () use ($outletId, $tableId, $tableSession, $customerName, $taxAmount, $serviceCharge, $totalAmount, $items) {
            $orderNumber = $this->generateOrderNumber($outletId);

            // Create Order header
            $order = Order::create([
                'outlet_id' => $outletId,
                'table_id' => $tableId,
                'order_number' => $orderNumber,
                'customer_name' => $customerName ?: 'Customer Table '.$tableSession->table->number,
                'status' => 'pending',
                'tax_amount' => $taxAmount,
                'service_charge' => $serviceCharge,
                'total_amount' => $totalAmount,
                'accurate_sync_status' => 'unsynced',
            ]);

            // Create Order items
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'options' => $item['options'],
                    'notes' => $item['notes'],
                ]);
            }

            // Clear cart session
            $this->cartService->clear();

            return $order;
        });
    }
}
