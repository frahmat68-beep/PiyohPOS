<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CustomerOrderController extends Controller
{
    protected CartService $cartService;

    protected OrderService $orderService;

    public function __construct(CartService $cartService, OrderService $orderService)
    {
        $this->cartService  = $cartService;
        $this->orderService = $orderService;
    }

    /**
     * Scan QR code and open or reuse table session.
     */
    public function scan(string $token)
    {
        $table = Table::where('qr_token', $token)->first();
        if (! $table) {
            abort(404, 'Invalid QR Table Token.');
        }

        // Reuse active open session if exists and not expired
        $tableSession = TableSession::where('table_id', $table->id)
            ->where('status', 'open')
            ->first();

        if ($tableSession && $tableSession->isExpired()) {
            $tableSession->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);
            $tableSession = null;
        }

        if (! $tableSession) {
            $sessionCode = Str::random(32);
            $tableSession = TableSession::create([
                'table_id'     => $table->id,
                'session_code' => $sessionCode,
                'status'       => 'open',
                'opened_at'    => now(),
                'expires_at'   => now()->addHours(4),
            ]);
        }

        // Ensure unique device token per browser / phone
        $deviceId = request()->cookie('piyoh_device_token') ?? (string) Str::uuid();

        // Put in PHP session
        Session::put('qr_session_code', $tableSession->session_code);
        Session::put('qr_table_id', $table->id);
        Session::put('piyoh_device_token', $deviceId);

        Cookie::queue('piyoh_device_token', $deviceId, 60 * 24 * 7); // 7 days
        Cookie::queue('qr_session_code', $tableSession->session_code, 60 * 4); // 4 hours

        return redirect()->to('/qr/menu');
    }

    /**
     * Display the menu.
     */
    public function menu()
    {
        $tableSession = $this->cartService->getActiveTableSession();
        if (! $tableSession) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'No active session.'], 403);
            }
            abort(403, 'No active session.');
        }

        $deviceId = $this->cartService->getDeviceId();

        // Get active categories with active products
        $categories = Category::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->get();

        $items     = $this->cartService->get($tableSession);
        $total     = $this->cartService->total($tableSession);
        $cartCount = array_sum(array_column($items, 'quantity'));

        // Build per-product cart summary for initial page render
        $cartPrimary        = [];
        $cartCountByProduct = [];
        foreach ($items as $item) {
            $pid = $item['product']->id;
            $cartCountByProduct[$pid] = ($cartCountByProduct[$pid] ?? 0) + $item['quantity'];
            if (! isset($cartPrimary[$pid])) {
                $cartPrimary[$pid] = $item;
            }
        }

        // Full cart keyed by cart_key
        $cartItemsByKey = [];
        foreach ($items as $item) {
            $cartItemsByKey[$item['cart_key']] = [
                'cart_key'   => $item['cart_key'],
                'product_id' => $item['product']->id,
                'quantity'   => $item['quantity'],
                'notes'      => $item['notes'],
                'device_id'  => $item['device_id'] ?? null,
            ];
        }

        // Additional items for cross-selling
        $additionalCategory = Category::where('slug', 'additional')->first();
        $additionalProducts = $additionalCategory
            ? $additionalCategory->products()->where('is_active', true)->whereNotNull('base_price')->get()
            : collect();

        $isLocked = $tableSession->isLockedForDevice($deviceId);

        if (request()->wantsJson()) {
            return response()->json([
                'table'               => $tableSession->table,
                'categories'          => $categories,
                'cart_count'          => $cartCount,
                'cart_total'          => $total,
                'cart_total_formatted'=> 'Rp '.number_format($total, 0, ',', '.'),
                'cart_items'          => $cartItemsByKey,
                'is_locked'           => $isLocked,
                'device_id'           => $deviceId,
                'additional_products' => $additionalProducts,
            ]);
        }

        return view('customer.menu', compact(
            'tableSession',
            'categories',
            'items',
            'total',
            'cartCount',
            'cartPrimary',
            'cartCountByProduct',
            'cartItemsByKey',
            'additionalProducts',
            'isLocked',
            'deviceId'
        ));
    }

    /**
     * Real-time polling endpoint for multi-device synchronization.
     */
    public function sync(Request $request)
    {
        $tableSession = $this->cartService->getActiveTableSession();
        if (! $tableSession) {
            return response()->json(['error' => 'No active table session.'], 403);
        }

        $deviceId  = $this->cartService->getDeviceId();
        $items     = $this->cartService->get($tableSession);
        $total     = $this->cartService->total($tableSession);
        $cartCount = array_sum(array_column($items, 'quantity'));

        $cartItemsByKey = [];
        $cartCountByProduct = [];
        foreach ($items as $item) {
            $pid = $item['product']->id;
            $cartCountByProduct[$pid] = ($cartCountByProduct[$pid] ?? 0) + $item['quantity'];
            $cartItemsByKey[$item['cart_key']] = [
                'cart_key'   => $item['cart_key'],
                'product_id' => $item['product']->id,
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'notes'      => $item['notes'],
                'device_id'  => $item['device_id'] ?? null,
                'is_mine'    => ($item['device_id'] ?? null) === $deviceId,
            ];
        }

        $isLocked = $tableSession->isLockedForDevice($deviceId);

        return response()->json([
            'status'                => 'ok',
            'table_number'          => $tableSession->table ? $tableSession->table->number : null,
            'cart_count'            => $cartCount,
            'cart_total'            => $total,
            'cart_total_formatted'  => 'Rp '.number_format($total, 0, ',', '.'),
            'cart_items'            => $cartItemsByKey,
            'cart_count_by_product' => $cartCountByProduct,
            'is_locked'             => $isLocked,
            'device_id'             => $deviceId,
        ]);
    }

    /**
     * Add product to shared cart.
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'notes'      => 'nullable|string|max:255',
        ]);

        $deviceId = $this->cartService->getDeviceId();

        try {
            $cartKey = $this->cartService->add(
                $request->product_id,
                $request->quantity,
                $request->input('options', []),
                $request->notes,
                $deviceId
            );

            $items     = $this->cartService->get();
            $total     = $this->cartService->total();
            $cartCount = array_sum(array_column($items, 'quantity'));

            $currentQty = 0;
            foreach ($items as $item) {
                if ($item['cart_key'] === $cartKey) {
                    $currentQty = $item['quantity'];
                    break;
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message'              => 'Product added to shared cart successfully.',
                    'product_id'           => (int) $request->product_id,
                    'cart_key'             => $cartKey,
                    'quantity'             => $currentQty,
                    'cart_count'           => $cartCount,
                    'cart_total'           => $total,
                    'cart_total_formatted' => 'Rp '.number_format($total, 0, ',', '.'),
                ]);
            }

            return redirect()->to('/qr/menu')->with('success', 'Product added to cart!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update product quantity in shared cart.
     */
    public function updateCart(Request $request)
    {
        $request->validate([
            'cart_key' => 'required|string|max:500',
            'quantity' => 'required|integer|min:0',
        ]);

        $deviceId = $this->cartService->getDeviceId();

        try {
            $this->cartService->updateQuantity($request->cart_key, (int) $request->quantity, $deviceId);

            $items     = $this->cartService->get();
            $total     = $this->cartService->total();
            $cartCount = array_sum(array_column($items, 'quantity'));

            $currentQty = 0;
            $productId  = null;
            if (str_contains($request->cart_key, '|')) {
                $productId = (int) explode('|', $request->cart_key)[0];
            }
            foreach ($items as $item) {
                if ($item['cart_key'] === $request->cart_key) {
                    $currentQty = $item['quantity'];
                    $productId  = $item['product']->id;
                    break;
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message'              => 'Shared cart updated successfully.',
                    'cart_key'             => $request->cart_key,
                    'product_id'           => $productId,
                    'quantity'             => $currentQty,
                    'cart_count'           => $cartCount,
                    'cart_total'           => $total,
                    'cart_total_formatted' => 'Rp '.number_format($total, 0, ',', '.'),
                ]);
            }

            return redirect()->to('/qr/menu');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove product from shared cart.
     */
    public function removeFromCart(Request $request)
    {
        $request->validate([
            'cart_key' => 'required|string|max:500',
        ]);

        $deviceId = $this->cartService->getDeviceId();

        try {
            $this->cartService->remove($request->cart_key, $deviceId);

            $items     = $this->cartService->get();
            $total     = $this->cartService->total();
            $cartCount = array_sum(array_column($items, 'quantity'));

            if ($request->wantsJson()) {
                return response()->json([
                    'message'              => 'Item removed from shared cart.',
                    'cart_key'             => $request->cart_key,
                    'quantity'             => 0,
                    'cart_count'           => $cartCount,
                    'cart_total'           => $total,
                    'cart_total_formatted' => 'Rp '.number_format($total, 0, ',', '.'),
                ]);
            }

            return redirect()->to('/qr/menu');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show shared cart details.
     */
    public function cart()
    {
        $tableSession = $this->cartService->getActiveTableSession();
        if (! $tableSession) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'No active session.'], 403);
            }
            abort(403, 'No active session.');
        }

        $deviceId = $this->cartService->getDeviceId();
        $items    = $this->cartService->get($tableSession);
        $total    = $this->cartService->total($tableSession);
        $isLocked = $tableSession->isLockedForDevice($deviceId);

        if (request()->wantsJson()) {
            return response()->json([
                'items'                => $items,
                'total'                => $total,
                'total_formatted'      => 'Rp '.number_format($total, 0, ',', '.'),
                'is_locked'            => $isLocked,
                'device_id'            => $deviceId,
            ]);
        }

        return view('customer.cart', compact('tableSession', 'items', 'total', 'isLocked', 'deviceId'));
    }

    /**
     * Process checkout / payment generation.
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'customer_name'  => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|in:midtrans,cash',
        ]);

        $deviceId    = $this->cartService->getDeviceId();
        $useMidtrans = $request->input('payment_method', 'midtrans') === 'midtrans';

        try {
            $order        = $this->orderService->checkout($request->customer_name, $deviceId, $useMidtrans);
            $removedItems = $order->removed_items ?? [];

            $warningMessage = null;
            if (! empty($removedItems)) {
                $warningMessage = 'Item berikut dihapus dari pesananmu karena sedang habis: '.implode(', ', $removedItems);
            }

            if ($request->wantsJson()) {
                $response = [
                    'message'    => 'Order created successfully.',
                    'order'      => $order,
                    'snap_token' => $order->midtrans_snap_token,
                ];
                if (! empty($removedItems)) {
                    $response['removed_items'] = $removedItems;
                    $response['warning']       = $warningMessage;
                }

                return response()->json($response);
            }

            return view('customer.order_success', compact('order', 'removedItems', 'warningMessage'));
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }

            return redirect()->to('/cart')->with('error', $e->getMessage());
        }
    }

    /**
     * Unlock cart (e.g. when customer dismisses payment popup).
     */
    public function unlockCart()
    {
        $this->cartService->unlockCart();

        return response()->json(['status' => 'unlocked']);
    }

    /**
     * Live order status tracker.
     */
    public function orderStatus(string $orderNumber)
    {
        $order = Order::with(['orderItems.product', 'table', 'payments'])->where('order_number', $orderNumber)->first();
        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json([
            'order_number'    => $order->order_number,
            'status'          => $order->status,
            'payment_status'  => $order->payment_status,
            'payment_method'  => $order->payment_method,
            'snap_token'      => $order->midtrans_snap_token,
            'total_amount'    => $order->total_amount,
            'delivered'       => (bool) $order->delivered_at,
            'delivered_at'    => $order->delivered_at?->format('H:i'),
            'status_label'    => match ($order->status) {
                'pending_payment' => 'Menunggu Pembayaran Online',
                'pending'         => 'Menunggu Konfirmasi Kasir',
                'confirmed'       => 'Pesanan Dikonfirmasi, Masuk Antrian Dapur',
                'preparing'       => 'Sedang Diracik oleh Barista',
                'ready'           => 'Pesanan Siap Diantar ke Meja',
                'served'          => 'Pesanan Telah Diantar — Selamat Menikmati!',
                'completed'       => 'Pesanan Selesai',
                'cancelled'       => 'Pesanan Dibatalkan / Gagal',
                default           => ucfirst($order->status),
            },
            'progress_step'   => match ($order->status) {
                'pending_payment'      => 1,
                'pending'              => 1,
                'confirmed'            => 2,
                'preparing'            => 3,
                'ready'                => 4,
                'served', 'completed'  => 5,
                'cancelled'            => 0,
                default                => 1,
            },
            'updated_at'      => $order->updated_at?->toIso8601String(),
        ]);
    }
}
