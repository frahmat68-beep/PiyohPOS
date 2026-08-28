<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\TableSession;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected string $sessionKey = 'qr_cart';

    /**
     * Generate a deterministic cart key from product ID, options, and notes.
     *
     * Same product + SAME customization  → same key  → quantities are merged.
     * Same product + DIFFERENT customization → different key → separate line item.
     *
     * The key is a plain-text string (no hashing) so that the JavaScript layer
     * can independently compute the same key using the matching makeCartKey() helper
     * in menu.blade.php, enabling chip-change detection entirely on the client side.
     */
    private function makeCartKey(int $productId, array $options, ?string $notes): string
    {
        ksort($options);
        $optParts = [];
        foreach ($options as $k => $v) {
            $optParts[] = "{$k}:{$v}";
        }

        return "{$productId}|" . implode(',', $optParts) . '|' . ($notes ?? '');
    }

    /**
     * Get the active table session from PHP Session.
     */
    public function getActiveTableSession(): ?TableSession
    {
        $sessionCode = Session::get('qr_session_code');
        if (! $sessionCode) {
            return null;
        }

        $tableSession = TableSession::where('session_code', $sessionCode)
            ->where('status', 'open')
            ->first();

        if ($tableSession && $tableSession->isExpired()) {
            $tableSession->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);
            Session::forget('qr_session_code');
            Session::forget($this->sessionKey);

            return null;
        }

        return $tableSession;
    }

    /**
     * Get current outlet ID based on the active table session.
     */
    public function getOutletId(): ?int
    {
        $tableSession = $this->getActiveTableSession();

        return $tableSession ? $tableSession->table->outlet_id : null;
    }

    /**
     * Add an item to the cart.
     *
     * If an entry with the same (product, options, notes) combination already exists
     * its quantity is incremented; otherwise a new line item is created.
     *
     * @return string The cart_key that uniquely identifies this line item.
     */
    public function add(int $productId, int $quantity = 1, array $options = [], ?string $notes = null): string
    {
        $product = Product::find($productId);
        if (! $product || $product->base_price === null || (float) $product->base_price <= 0) {
            throw new \InvalidArgumentException('Item ini harus dipesan langsung ke kasir, silakan hubungi staff kami.');
        }

        $cartKey = $this->makeCartKey($productId, $options, $notes);
        $cart    = Session::get($this->sessionKey, []);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'cart_key'   => $cartKey,
                'product_id' => $productId,
                'quantity'   => $quantity,
                'options'    => $options,
                'notes'      => $notes,
            ];
        }

        Session::put($this->sessionKey, $cart);

        return $cartKey;
    }

    /**
     * Update item quantity in the cart by cart_key.
     * Automatically removes the entry if quantity drops to ≤ 0.
     */
    public function updateQuantity(string $cartKey, int $quantity): void
    {
        $cart = Session::get($this->sessionKey, []);

        if (isset($cart[$cartKey])) {
            if ($quantity <= 0) {
                $this->remove($cartKey);

                return;
            }
            $cart[$cartKey]['quantity'] = $quantity;
            Session::put($this->sessionKey, $cart);
        }
    }

    /**
     * Remove an item from the cart by cart_key.
     */
    public function remove(string $cartKey): void
    {
        $cart = Session::get($this->sessionKey, []);

        if (isset($cart[$cartKey])) {
            unset($cart[$cartKey]);
            Session::put($this->sessionKey, $cart);
        }
    }

    /**
     * Get all items in the cart with full Product details and calculated prices.
     *
     * Each returned item includes 'cart_key' so the controller / view can pass it
     * back to the client for subsequent update / remove operations.
     */
    public function get(): array
    {
        $cart = Session::get($this->sessionKey, []);
        if (empty($cart)) {
            return [];
        }

        // Collect unique product IDs from cart values (keys are now cart_keys, not IDs)
        $productIds = array_unique(array_column(array_values($cart), 'product_id'));
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $outletId   = $this->getOutletId();

        // Fetch outlet-specific price overrides
        $overrides = [];
        if ($outletId) {
            $overrides = ProductPrice::where('outlet_id', $outletId)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
        }

        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'];
            if (! isset($products[$productId])) {
                continue;
            }

            $product = $products[$productId];

            // Resolve correct price (outlet override or global base price)
            $price = $product->base_price;
            if (isset($overrides[$productId])) {
                $price = $overrides[$productId]->price;
            }

            $items[] = [
                'cart_key' => $cartKey,
                'product'  => $product,
                'quantity' => $item['quantity'],
                'price'    => $price,
                'options'  => $item['options'],
                'notes'    => $item['notes'],
                'subtotal' => $price * $item['quantity'],
            ];
        }

        return $items;
    }

    /**
     * Get the grand total of the cart.
     */
    public function total(): float
    {
        $items = $this->get();

        return array_sum(array_column($items, 'subtotal'));
    }

    /**
     * Clear the cart.
     */
    public function clear(): void
    {
        Session::forget($this->sessionKey);
    }
}
