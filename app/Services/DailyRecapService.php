<?php

namespace App\Services;

use App\Models\DailyRecap;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DailyRecapService
{
    /**
     * Compute daily summary for a given outlet and date.
     */
    public function compute(int $outletId, CarbonInterface $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay   = $date->copy()->endOfDay();

        $orders = Order::with(['orderItems.product.category', 'payments', 'table'])
            ->where('outlet_id', $outletId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->get();

        $paidOrders      = $orders->where('payment_status', 'paid');
        $cancelledOrders = $orders->where('status', Order::STATUS_CANCELLED);

        $totalRevenue       = (float) $paidOrders->sum('total_amount');
        $taxTotal           = (float) $paidOrders->sum('tax_amount');
        $serviceChargeTotal = (float) $paidOrders->sum('service_charge');

        $cashRevenue     = 0.0;
        $midtransRevenue = 0.0;
        $qrisRevenue     = 0.0;
        $otherRevenue    = 0.0;

        $paymentBreakdown = [];

        foreach ($paidOrders as $order) {
            $method = strtolower($order->payment_method ?? 'unknown');
            $ptype  = strtolower($order->midtrans_payment_type ?? '');
            $amount = (float) $order->total_amount;

            $key = $ptype ? "midtrans_{$ptype}" : $method;
            $paymentBreakdown[$key] = ($paymentBreakdown[$key] ?? 0.0) + $amount;

            if (str_contains($method, 'cash')) {
                $cashRevenue += $amount;
            } elseif (str_contains($method, 'midtrans') || ! empty($ptype)) {
                $midtransRevenue += $amount;
                if (str_contains($ptype, 'qris') || str_contains($method, 'qris')) {
                    $qrisRevenue += $amount;
                }
            } elseif (str_contains($method, 'qris')) {
                $qrisRevenue += $amount;
                $otherRevenue += $amount;
            } else {
                $otherRevenue += $amount;
            }
        }

        // Product Breakdown
        $itemsBreakdown = [];
        foreach ($paidOrders as $order) {
            foreach ($order->orderItems as $item) {
                $prod = $item->product;
                if (! $prod) continue;

                $pid   = $prod->id;
                $pname = $prod->name;
                $cname = $prod->category ? $prod->category->name : 'Uncategorized';

                if (! isset($itemsBreakdown[$pid])) {
                    $itemsBreakdown[$pid] = [
                        'product_id'    => $pid,
                        'product_name'  => $pname,
                        'category_name' => $cname,
                        'unit_price'    => (float) $item->price,
                        'quantity'      => 0,
                        'total_sales'   => 0.0,
                    ];
                }

                $itemsBreakdown[$pid]['quantity']    += $item->quantity;
                $itemsBreakdown[$pid]['total_sales'] += (float) $item->price * $item->quantity;
            }
        }

        // Sort items by quantity descending
        usort($itemsBreakdown, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        return [
            'outlet_id'               => $outletId,
            'recap_date'              => $date->toDateString(),
            'total_orders'            => $paidOrders->count(),
            'total_revenue'           => $totalRevenue,
            'cash_revenue'            => $cashRevenue,
            'midtrans_revenue'        => $midtransRevenue,
            'qris_revenue'            => $qrisRevenue,
            'other_revenue'           => $otherRevenue,
            'tax_total'               => $taxTotal,
            'service_charge_total'    => $serviceChargeTotal,
            'cancelled_orders_count'  => $cancelledOrders->count(),
            'payment_method_breakdown'=> $paymentBreakdown,
            'items_summary'           => $itemsBreakdown,
            'orders'                  => $orders,
        ];
    }

    /**
     * Persist or update the daily recap record.
     */
    public function saveDailyRecap(int $outletId, CarbonInterface $date, ?int $userId = null, bool $closeStore = false): DailyRecap
    {
        $computed = $this->compute($outletId, $date);

        $payload = [
            'total_orders'            => $computed['total_orders'],
            'total_revenue'           => $computed['total_revenue'],
            'cash_revenue'            => $computed['cash_revenue'],
            'midtrans_revenue'        => $computed['midtrans_revenue'],
            'qris_revenue'            => $computed['qris_revenue'],
            'other_revenue'           => $computed['other_revenue'],
            'tax_total'               => $computed['tax_total'],
            'service_charge_total'    => $computed['service_charge_total'],
            'cancelled_orders_count'  => $computed['cancelled_orders_count'],
            'payment_method_breakdown'=> $computed['payment_method_breakdown'],
            'items_summary'           => $computed['items_summary'],
        ];

        if ($closeStore) {
            $payload['is_closed']         = true;
            $payload['closed_at']          = now();
            $payload['closed_by_user_id']  = $userId;
        }

        return DailyRecap::updateOrCreate([
            'outlet_id'  => $outletId,
            'recap_date' => $date->toDateString(),
        ], $payload);
    }

    /**
     * Generate Accurate-compatible CSV export string.
     */
    public function exportCsv(int $outletId, CarbonInterface $date): string
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay   = $date->copy()->endOfDay();

        $orders = Order::with(['orderItems.product.category', 'table', 'payments'])
            ->where('outlet_id', $outletId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('payment_status', 'paid')
            ->orderBy('created_at', 'asc')
            ->get();

        $output = fopen('php://temp', 'r+');

        // CSV Header for Accurate Import
        fputcsv($output, [
            'Tanggal',
            'No. Transaksi',
            'Waktu',
            'Meja',
            'Nama Pemesan',
            'Kode Item',
            'Nama Produk',
            'Kategori',
            'Kuantitas',
            'Harga Satuan (Rp)',
            'Subtotal Item (Rp)',
            'Catatan/Opsi',
            'Pajak PB1 (Rp)',
            'Service Charge (Rp)',
            'Total Order (Rp)',
            'Metode Pembayaran',
            'Channel / Tipe',
            'Status Pembayaran',
        ]);

        foreach ($orders as $order) {
            $orderDate    = $order->created_at->format('Y-m-d');
            $orderTime    = $order->created_at->format('H:i:s');
            $tableNo      = $order->table ? "Meja {$order->table->number}" : 'Takeaway';
            $custName     = $order->customer_name;
            $payMethod    = $order->payment_method;
            $payType      = $order->midtrans_payment_type ?: $payMethod;

            foreach ($order->orderItems as $item) {
                $prod = $item->product;
                $sku  = $prod ? ($prod->sku ?: "PROD-{$prod->id}") : 'ITEM';
                $name = $prod ? $prod->name : 'Item';
                $cat  = $prod && $prod->category ? $prod->category->name : 'General';
                $subtotal = $item->price * $item->quantity;

                $notes = [];
                if ($item->options && is_array($item->options)) {
                    foreach ($item->options as $k => $v) $notes[] = "{$k}:{$v}";
                }
                if ($item->notes) $notes[] = $item->notes;
                $notesStr = implode(' | ', $notes);

                fputcsv($output, [
                    $orderDate,
                    $order->order_number,
                    $orderTime,
                    $tableNo,
                    $custName,
                    $sku,
                    $name,
                    $cat,
                    $item->quantity,
                    number_format($item->price, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                    $notesStr,
                    number_format($order->tax_amount, 2, '.', ''),
                    number_format($order->service_charge, 2, '.', ''),
                    number_format($order->total_amount, 2, '.', ''),
                    strtoupper($payMethod),
                    strtoupper($payType),
                    strtoupper($order->payment_status),
                ]);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
