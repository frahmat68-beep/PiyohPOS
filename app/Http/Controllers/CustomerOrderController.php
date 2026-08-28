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
        $this->cartService = $cartService;
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
                'status' => 'closed',
                'closed_at' => now(),
            ]);

        // Create new session (expires in 4 hours)
        $sessionCode = Str::random(32);
        TableSession::create([
            'table_id' => $table->id,
            'session_code' => $sessionCode,
            'status' => 'open',
            'opened_at' => now(),
            'expires_at' => now()->addHours(4),
        ]);

        // Put in PHP session
        Session::put('qr_session_code', $sessionCode);
        Session::put('qr_table_id', $table->id);

        // Redirect to menu page
        return redirect()->route('qr.menu');
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

        // Fetch custom pricing overrides for this outlet
        $items = $this->cartService->get();

        if (request()->wantsJson()) {
            return response()->json([
                'table' => $tableSession->table,
                'categories' => $categories,
                'cart_count' => count($items),
            ]);
        }

        return view('customer.menu', compact('tableSession', 'categories', 'items'));
    }

    /**
     * Add product to cart.
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($request->product_id);
        if ($product->base_price === null || (float) $product->base_price <= 0) {
            $errorMessage = 'Item ini harus dipesan langsung ke kasir, silakan hubungi staff kami.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $errorMessage], 422);
            }

            return redirect()->back()->with('error', $errorMessage);
        }

        $this->cartService->add(
            $request->product_id,
            $request->quantity,
            $request->input('options', []),
            $request->notes
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product added to cart successfully.']);
        }

        return redirect()->route('qr.menu')->with('success', 'Product added to cart!');
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
            $order = $this->orderService->checkout($request->customer_name);
            $removedItems = $order->removed_items ?? [];

            $warningMessage = null;
            if (! empty($removedItems)) {
                $warningMessage = 'Item berikut dihapus dari pesananmu karena sedang habis: ' . implode(', ', $removedItems);
            }

            if ($request->wantsJson()) {
                $response = [
                    'message' => 'Order placed successfully!',
                    'order' => $order,
                ];
                if (! empty($removedItems)) {
                    $response['removed_items'] = $removedItems;
                    $response['warning'] = $warningMessage;
                }

                return response()->json($response);
            }

            return view('customer.order_success', compact('order', 'removedItems', 'warningMessage'));
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }

            return redirect()->route('qr.cart')->with('error', $e->getMessage());
        }
    }
}
