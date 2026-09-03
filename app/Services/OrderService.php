<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    protected CartService $cartService;

    protected MidtransService $midtransService;

    public function __construct(CartService $cartService, MidtransService $midtransService)
    {
        $this->cartService     = $cartService;
        $this->midtransService = $midtransService;
    }

    /**
     * Generate atomic, non-predictable, readable order number based on outlet and date.
     * Uses DB row locking on daily_order_sequences to prevent race conditions during concurrent checkouts.
     * Includes a randomized alphanumeric segment (e.g. A47, K01) to protect daily order volume privacy.
     */
    public function generateOrderNumber(int $outletId): string
    {
        $outlet = Outlet::findOrFail($outletId);

        $prefix = 'OUT';
        $slug   = Str::slug($outlet->name);
        if (str_contains($slug, 'galaxy')) {
            $prefix = 'GLX';
        } elseif (str_contains($slug, 'bekasi')) {
            $prefix = 'BKS';
        }

        $todayDate = today()->toDateString();
        $dateStr   = now()->format('Ymd');

        // Atomically increment daily sequence with row locking
        $sequence = DB::transaction(function () use ($outletId, $todayDate) {
            DB::table('daily_order_sequences')->insertOrIgnore([
                'outlet_id'     => $outletId,
                'order_date'    => $todayDate,
                'last_sequence' => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $record = DB::table('daily_order_sequences')
                ->where('outlet_id', $outletId)
                ->where('order_date', $todayDate)
                ->lockForUpdate()
                ->first();

            $next = ($record ? $record->last_sequence : 0) + 1;

            DB::table('daily_order_sequences')
                ->where('outlet_id', $outletId)
                ->where('order_date', $todayDate)
                ->update([
                    'last_sequence' => $next,
                    'updated_at'    => now(),
                ]);

            return $next;
        });

        // 1 random uppercase letter (A-Z) + sequence (e.g. A47, B01, Z99)
        // Keeps it readable for cashier calling while preventing total daily volume guessing
        $randTag   = chr(65 + rand(0, 25));
        $seqPadded = sprintf('%02d', $sequence);

        return "{$prefix}-{$dateStr}-{$randTag}{$seqPadded}";
    }

    /**
     * Create order from the current shared cart session.
     */
    public function checkout(?string $customerName = null, ?string $deviceId = null, bool $useMidtrans = true): Order
    {
        $tableSession = $this->cartService->getActiveTableSession();
        if (! $tableSession) {
            throw new \Exception('No active QR table session found.');
        }

        $deviceId = $deviceId ?: $this->cartService->getDeviceId();

        // Check if cart is already locked by another device
        if ($tableSession->isLockedForDevice($deviceId)) {
            throw new \Exception('Meja sedang memproses checkout dari perangkat lain. Mohon tunggu sebentar.');
        }

        $outletId = $tableSession->table->outlet_id;
        $tableId  = $tableSession->table->id;

        // Retrieve items from DB shared cart
        $items = $this->cartService->get($tableSession);
        if (empty($items)) {
            throw new \Exception('Cart is empty.');
        }

        $removedItems = [];
        $productIds   = array_unique(array_map(fn ($it) => $it['product']->id, $items));
        $products     = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $overrides    = ProductPrice::where('outlet_id', $outletId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        // Validate availability & stock
        foreach ($items as $item) {
            $productId = $item['product']->id;
            $product   = $products[$productId] ?? null;
            $isAvailable = true;

            if (! $product || ! $product->is_active) {
                $isAvailable = false;
            } elseif ($product->isOutOfStock()) {
                $isAvailable = false;
            } elseif (isset($overrides[$productId]) && ! $overrides[$productId]->is_available) {
                $isAvailable = false;
            }

            if (! $isAvailable) {
                $productName    = $product ? $product->name : ('Product #' . $productId);
                $removedItems[] = $productName;
                $this->cartService->remove($item['cart_key'], $deviceId);
            }
        }

        $validItems = $this->cartService->get($tableSession);
        if (empty($validItems)) {
            if (! empty($removedItems)) {
                throw new \Exception('Semua item di keranjangmu sedang tidak tersedia saat ini.');
            }
            throw new \Exception('Cart is empty.');
        }

        // Lock the table session cart during checkout
        $this->cartService->lockCart($deviceId);

        $subtotal      = $this->cartService->total($tableSession);
        $taxAmount     = round($subtotal * 0.10, 2);
        $serviceCharge = round($subtotal * 0.05, 2);
        $totalAmount   = $subtotal + $taxAmount + $serviceCharge;

        $order = DB::transaction(function () use ($outletId, $tableId, $tableSession, $customerName, $taxAmount, $serviceCharge, $totalAmount, $validItems, $removedItems, $useMidtrans) {
            $orderNumber = $this->generateOrderNumber($outletId);

            // Create Order header
            $order = Order::create([
                'outlet_id'            => $outletId,
                'table_id'             => $tableId,
                'order_number'         => $orderNumber,
                'customer_name'        => $customerName ?: 'Customer Table '.$tableSession->table->number,
                'status'               => $useMidtrans ? Order::STATUS_PENDING_PAYMENT : Order::STATUS_PENDING,
                'payment_status'       => 'pending',
                'payment_method'       => $useMidtrans ? 'midtrans' : 'cash',
                'tax_amount'           => $taxAmount,
                'service_charge'       => $serviceCharge,
                'total_amount'         => $totalAmount,
                'accurate_sync_status' => 'unsynced',
            ]);

            // Create Order items
            foreach ($validItems as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'options'    => $item['options'],
                    'notes'      => $item['notes'],
                ]);
            }

            $order->removed_items = $removedItems;

            return $order;
        });

        // Generate Midtrans Snap Token if using online payment
        if ($useMidtrans) {
            try {
                $snapToken = $this->midtransService->createSnapToken($order);
                $order->midtrans_snap_token = $snapToken;
            } catch (\Exception $e) {
                // If snap token fails, unlock cart and throw
                $this->cartService->unlockCart();
                throw $e;
            }
        } else {
            // For manual cash, clear cart immediately
            $this->cartService->clear($tableSession);
            $this->cartService->unlockCart();
        }

        return $order;
    }
}
