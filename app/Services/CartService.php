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
                'status' => 'closed',
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
     */
    public function add(int $productId, int $quantity = 1, array $options = [], ?string $notes = null): void
    {
        $cart = Session::get($this->sessionKey, []);

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
            if ($notes) {
                $cart[$productId]['notes'] = $notes;
            }
            if (! empty($options)) {
                $cart[$productId]['options'] = array_merge($cart[$productId]['options'], $options);
            }
        } else {
            $cart[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'options' => $options,
                'notes' => $notes,
            ];
        }

        Session::put($this->sessionKey, $cart);
    }

    /**
     * Update item quantity in the cart.
     */
    public function updateQuantity(int $productId, int $quantity): void
    {
        $cart = Session::get($this->sessionKey, []);

        if (isset($cart[$productId])) {
            if ($quantity <= 0) {
                $this->remove($productId);

                return;
            }
            $cart[$productId]['quantity'] = $quantity;
            Session::put($this->sessionKey, $cart);
        }
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(int $productId): void
    {
        $cart = Session::get($this->sessionKey, []);

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
            Session::put($this->sessionKey, $cart);
        }
    }

    /**
     * Get all items in the cart with full Product details and calculated prices.
     */
    public function get(): array
    {
        $cart = Session::get($this->sessionKey, []);
        if (empty($cart)) {
            return [];
        }

        $productIds = array_keys($cart);
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $outletId = $this->getOutletId();

        // Fetch outlet-specific price overrides
        $overrides = [];
        if ($outletId) {
            $overrides = ProductPrice::where('outlet_id', $outletId)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
        }

        $items = [];
        foreach ($cart as $productId => $item) {
            if (! isset($products[$productId])) {
                continue;
            }

            $product = $products[$productId];

            // Resolve correct price (override or base price)
            $price = $product->base_price;
            if (isset($overrides[$productId])) {
                $price = $overrides[$productId]->price;
            }

            $items[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'price' => $price,
                'options' => $item['options'],
                'notes' => $item['notes'],
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
