<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey    = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized  = config('midtrans.is_sanitized');
        Config::$is3ds        = config('midtrans.is_3ds');
    }

    /**
     * Create Snap Token for an Order.
     */
    public function createSnapToken(Order $order): string
    {
        $itemDetails = [];

        foreach ($order->orderItems as $item) {
            $name = $item->product ? $item->product->name : 'Item';
            if ($item->options && is_array($item->options) && ! empty($item->options)) {
                $opts = [];
                foreach ($item->options as $k => $v) {
                    $opts[] = "{$k}:{$v}";
                }
                $name .= ' ('.implode(', ', $opts).')';
            }

            $itemDetails[] = [
                'id'       => (string) $item->product_id,
                'price'    => (int) round($item->price),
                'quantity' => (int) $item->quantity,
                'name'     => mb_substr($name, 0, 50),
            ];
        }

        // Add Tax as line item if > 0
        if ((float) $order->tax_amount > 0) {
            $itemDetails[] = [
                'id'       => 'TAX-10',
                'price'    => (int) round($order->tax_amount),
                'quantity' => 1,
                'name'     => 'PB1 (Pajak Restoran 10%)',
            ];
        }

        // Add Service Charge as line item if > 0
        if ((float) $order->service_charge > 0) {
            $itemDetails[] = [
                'id'       => 'SERVICE-5',
                'price'    => (int) round($order->service_charge),
                'quantity' => 1,
                'name'     => 'Service Charge (5%)',
            ];
        }

        // Calculate expected gross amount
        $calculatedTotal = (int) round($order->total_amount);

        $params = [
            'transaction_details' => [
                'order_id'     => $order->order_number,
                'gross_amount' => $calculatedTotal,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $order->customer_name ?: 'Pelanggan Meja '.($order->table ? $order->table->number : ''),
                'email'      => 'customer@piyohkopi.com',
            ],
            'callbacks' => [
                'finish' => url("/orders/{$order->order_number}/status"),
            ],
        ];

        if (app()->environment('testing') && (empty(config('midtrans.server_key')) || str_contains(config('midtrans.server_key'), 'TEST'))) {
            $snapToken = 'snap-token-' . $order->order_number;
            $order->update(['midtrans_snap_token' => $snapToken]);
            return $snapToken;
        }

        try {
            $snapToken = Snap::getSnapToken($params);
            $order->update(['midtrans_snap_token' => $snapToken]);

            return $snapToken;
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Token Generation Failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Gagal menghubungkan ke sistem pembayaran Midtrans: '.$e->getMessage());
        }
    }

    /**
     * Validate Midtrans SHA512 Signature.
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $serverKey = config('midtrans.server_key');
        $expected  = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expected, $signatureKey);
    }

    /**
     * Process Webhook Notification payload from Midtrans.
     */
    public function processNotification(array $payload): array
    {
        $orderId           = $payload['order_id'] ?? '';
        $statusCode        = $payload['status_code'] ?? '';
        $grossAmount       = $payload['gross_amount'] ?? '';
        $signatureKey      = $payload['signature_key'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus       = $payload['fraud_status'] ?? 'accept';
        $paymentType       = $payload['payment_type'] ?? 'midtrans';
        $transactionId     = $payload['transaction_id'] ?? null;

        if (! $this->verifySignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
            Log::warning('Midtrans Webhook: Invalid signature detected', ['order_id' => $orderId]);
            throw new \InvalidArgumentException('Invalid Midtrans signature.');
        }

        $order = Order::with(['orderItems', 'table'])->where('order_number', $orderId)->first();
        if (! $order) {
            Log::error('Midtrans Webhook: Order not found', ['order_id' => $orderId]);
            throw new \RuntimeException('Order not found.');
        }

        return DB::transaction(function () use ($order, $transactionStatus, $fraudStatus, $paymentType, $transactionId, $grossAmount) {
            $order->update([
                'midtrans_transaction_id' => $transactionId,
                'midtrans_payment_type'   => $paymentType,
            ]);

            // Handle Settlement / Capture (Success)
            if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
                // Deduct stock atomically if stock is tracked
                foreach ($order->orderItems as $item) {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    if ($product && $product->stock_quantity !== null) {
                        $newStock = max(0, $product->stock_quantity - $item->quantity);
                        $product->update(['stock_quantity' => $newStock]);
                    }
                }

                // Record Payment
                Payment::updateOrCreate([
                    'order_id' => $order->id,
                ], [
                    'payment_method' => "midtrans_{$paymentType}",
                    'payment_status' => 'paid',
                    'amount'         => (float) $grossAmount,
                    'paid_at'        => now(),
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'payment_method' => "midtrans_{$paymentType}",
                ]);

                // Transition Order to Confirmed (Queued for kitchen)
                if (in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PENDING])) {
                    $order->transitionTo(Order::STATUS_CONFIRMED, "Pembayaran Midtrans ({$paymentType}) berhasil dikonfirmasi.");
                }

                // Clear table session cart & check for force unlock race condition
                $tableSession = \App\Models\TableSession::where('table_id', $order->table_id)->where('status', 'open')->first();
                if ($tableSession) {
                    // If session was force unlocked within last 30 minutes, flag this order
                    if ($tableSession->force_unlocked_at && $tableSession->force_unlocked_at->diffInMinutes(now()) <= 30) {
                        $order->update([
                            'paid_after_force_unlock_at' => now(),
                        ]);
                    }

                    \App\Models\CartItem::where('table_session_id', $tableSession->id)->delete();
                    $tableSession->unlockCart();
                }

                return [
                    'status'  => 'success',
                    'message' => 'Payment settled successfully.',
                ];
            }

            // Handle Pending Payment
            if ($transactionStatus === 'pending') {
                $order->update([
                    'payment_status' => 'pending',
                    'status'         => Order::STATUS_PENDING_PAYMENT,
                ]);

                return [
                    'status'  => 'pending',
                    'message' => 'Payment is pending.',
                ];
            }

            // Handle Denied, Cancelled, Expired (Failed)
            if (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                Payment::updateOrCreate([
                    'order_id' => $order->id,
                ], [
                    'payment_method' => "midtrans_{$paymentType}",
                    'payment_status' => 'failed',
                    'amount'         => (float) $grossAmount,
                ]);

                $order->update([
                    'payment_status' => 'failed',
                ]);

                if (in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PENDING])) {
                    $order->transitionTo(Order::STATUS_CANCELLED, "Pembayaran Midtrans ({$paymentType}) gagal/kedaluwarsa [{$transactionStatus}].");
                }

                // Unlock table session cart so user can retry or adjust items
                $tableSession = \App\Models\TableSession::where('table_id', $order->table_id)->where('status', 'open')->first();
                if ($tableSession) {
                    $tableSession->unlockCart();
                }

                return [
                    'status'  => 'failed',
                    'message' => "Payment {$transactionStatus}.",
                ];
            }

            return [
                'status'  => 'unhandled',
                'message' => "Transaction status {$transactionStatus} received.",
            ];
        });
    }
}
