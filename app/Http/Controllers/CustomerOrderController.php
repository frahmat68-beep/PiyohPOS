<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
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
     * Scan QR code and open table session.
     */
    public function scan(string $token)
    {
        $table = Table::where('qr_token', $token)->first();
        if (! $table) {
            abort(404, 'Invalid QR Table Token.');
        }

        // Close any existing open sessions for this table if any (optional cleanup)
        TableSession::where('table_id', $table->id)
            ->where('status', 'open')
            ->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

        // Create new session (expires in 4 hours)
        $sessionCode = Str::random(32);
        TableSession::create([
            'table_id'    => $table->id,
            'session_code' => $sessionCode,
            'status'      => 'open',
            'opened_at'   => now(),
            'expires_at'  => now()->addHours(4),
        ]);

        // Put in PHP session
        Session::put('qr_session_code', $sessionCode);
        Session::put('qr_table_id', $table->id);

        // Redirect to menu page
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

        $outletId = $this->cartService->getOutletId();

        // Get active categories with active products
        $categories = Category::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->get();

        $items = $this->cartService->get();
        $total = $this->cartService->total();
        $cartCount = array_sum(array_column($items, 'quantity'));

        // Build per-product cart summary for the initial page render:
        //   $cartPrimary[productId]  = first cart item for that product (for stepper)
        //   $cartCountByProduct[pid] = total qty across ALL customization variants
        $cartPrimary        = [];
        $cartCountByProduct = [];
        foreach ($items as $item) {
            $pid = $item['product']->id;
            $cartCountByProduct[$pid] = ($cartCountByProduct[$pid] ?? 0) + $item['quantity'];
            if (! isset($cartPrimary[$pid])) {
                $cartPrimary[$pid] = $item;   // first entry (used for initial stepper render)
            }
        }

        // Full cart keyed by cart_key — passed as JSON to JS for chip-change detection
        $cartItemsByKey = [];
        foreach ($items as $item) {
            $cartItemsByKey[$item['cart_key']] = [
                'cart_key'   => $item['cart_key'],
                'product_id' => $item['product']->id,
                'quantity'   => $item['quantity'],
                'notes'      => $item['notes'],
            ];
        }

        // Additional items for cross-selling suggestion
        $additionalCategory = Category::where('slug', 'additional')->first();
        $additionalProducts = $additionalCategory
            ? $additionalCategory->products()->where('is_active', true)->whereNotNull('base_price')->get()
            : collect();

        if (request()->wantsJson()) {
            return response()->json([
                'table'              => $tableSession->table,
                'categories'         => $categories,
                'cart_count'         => $cartCount,
                'cart_total'         => $total,
                'cart_items'         => $cartItemsByKey,
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
            'additionalProducts'
        ));
    }

    /**
     * Add product to cart.
     *
     * Returns the cart_key for the added / merged line item so the frontend
     * can track it for subsequent update / remove calls.
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'notes'      => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($request->product_id);
        if ($product->base_price === null || (float) $product->base_price <= 0) {
            $errorMessage = 'Item ini harus dipesan langsung ke kasir, silakan hubungi staff kami.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $errorMessage], 422);
            }

            return redirect()->back()->with('error', $errorMessage);
        }

        $cartKey = $this->cartService->add(
            $request->product_id,
            $request->quantity,
            $request->input('options', []),
            $request->notes
        );

        $items     = $this->cartService->get();
        $total     = $this->cartService->total();
        $cartCount = array_sum(array_column($items, 'quantity'));

        // Look up the current quantity for this specific cart_key
        $currentQty = 0;
        foreach ($items as $item) {
            if ($item['cart_key'] === $cartKey) {
                $currentQty = $item['quantity'];
                break;
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message'           => 'Product added to cart successfully.',
                'product_id'        => (int) $request->product_id,
                'cart_key'          => $cartKey,
                'quantity'          => $currentQty,
                'cart_count'        => $cartCount,
                'cart_total'        => $total,
                'cart_total_formatted' => 'Rp '.number_format($total, 0, ',', '.'),
            ]);
        }

        return redirect()->to('/qr/menu')->with('success', 'Product added to cart!');
    }

    /**
     * Update product quantity in cart.
     *
     * Accepts 'cart_key' (the composite key returned by addToCart) and 'quantity'.
     * Setting quantity to 0 automatically removes the line item.
     */
    public function updateCart(Request $request)
    {
        $request->validate([
            'cart_key' => 'required|string|max:500',
            'quantity' => 'required|integer|min:0',
        ]);

        // Capture product_id before the update (entry may be removed when qty → 0)
        $rawCart   = Session::get('qr_cart', []);
        $productId = $rawCart[$request->cart_key]['product_id'] ?? null;

        $this->cartService->updateQuantity($request->cart_key, (int) $request->quantity);

        $items     = $this->cartService->get();
        $total     = $this->cartService->total();
        $cartCount = array_sum(array_column($items, 'quantity'));

        // After update the entry may or may not still exist (qty 0 removes it)
        $currentQty = 0;
        foreach ($items as $item) {
            if ($item['cart_key'] === $request->cart_key) {
                $currentQty = $item['quantity'];
                break;
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message'           => 'Cart updated successfully.',
                'cart_key'          => $request->cart_key,
                'product_id'        => (int) $productId,
                'quantity'          => $currentQty,
                'cart_count'        => $cartCount,
                'cart_total'        => $total,
                'cart_total_formatted' => 'Rp '.number_format($total, 0, ',', '.'),
            ]);
        }

        return redirect()->to('/qr/menu');
    }

    /**
     * Remove product from cart.
     *
     * Accepts 'cart_key' (the composite key returned by addToCart).
     */
    public function removeFromCart(Request $request)
    {
        $request->validate([
            'cart_key' => 'required|string|max:500',
        ]);

        // Capture product_id before removal for the response
        $rawCart   = Session::get('qr_cart', []);
        $productId = $rawCart[$request->cart_key]['product_id'] ?? null;

        $this->cartService->remove($request->cart_key);

        $items     = $this->cartService->get();
        $total     = $this->cartService->total();
        $cartCount = array_sum(array_column($items, 'quantity'));

        if ($request->wantsJson()) {
            return response()->json([
                'message'           => 'Item removed from cart.',
                'cart_key'          => $request->cart_key,
                'product_id'        => (int) $productId,
                'quantity'          => 0,
                'cart_count'        => $cartCount,
                'cart_total'        => $total,
                'cart_total_formatted' => 'Rp '.number_format($total, 0, ',', '.'),
            ]);
        }

        return redirect()->to('/qr/menu');
    }

    /**
     * Show cart details.
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

        $items = $this->cartService->get();
        $total = $this->cartService->total();

        if (request()->wantsJson()) {
            return response()->json([
                'items' => $items,
                'total' => $total,
            ]);
        }

        return view('customer.cart', compact('tableSession', 'items', 'total'));
    }

    /**
     * Process checkout/order placement.
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'customer_name' => 'nullable|string|max:100',
        ]);

        try {
            $order        = $this->orderService->checkout($request->customer_name);
            $removedItems = $order->removed_items ?? [];

            $warningMessage = null;
            if (! empty($removedItems)) {
                $warningMessage = 'Item berikut dihapus dari pesananmu karena sedang habis: '.implode(', ', $removedItems);
            }

            if ($request->wantsJson()) {
                $response = [
                    'message' => 'Order placed successfully!',
                    'order'   => $order,
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
     * Get live order status for real-time customer tracker polling.
     */
    public function orderStatus(string $orderNumber)
    {
        $order = \App\Models\Order::where('order_number', $orderNumber)->first();
        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status'       => $order->status,
            'status_label' => match ($order->status) {
                'pending'   => 'Menunggu Konfirmasi Kasir',
                'confirmed' => 'Pesanan Dikonfirmasi, Masuk Antrian Dapur',
                'preparing' => 'Sedang Diracik oleh Barista',
                'ready'     => 'Pesanan Siap Diantar ke Meja',
                'served'    => 'Pesanan Telah Diantar — Selamat Menikmati!',
                'completed' => 'Pesanan Selesai',
                'cancelled' => 'Pesanan Dibatalkan',
                default     => ucfirst($order->status),
            },
            'progress_step' => match ($order->status) {
                'pending'              => 1,
                'confirmed'            => 2,
                'preparing'            => 3,
                'ready'                => 4,
                'served', 'completed'  => 5,
                'cancelled'            => 0,
                default                => 1,
            },
            'updated_at' => $order->updated_at?->toIso8601String(),
        ]);
    }
}
