<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\TableSession;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Generate a deterministic cart key from product ID, options, and notes.
     */
    public function makeCartKey(int $productId, array $options = [], ?string $notes = null): string
    {
        ksort($options);
        $optParts = [];
        foreach ($options as $k => $v) {
            $optParts[] = "{$k}:{$v}";
        }

        return "{$productId}|" . implode(',', $optParts) . '|' . ($notes ?? '');
    }

    /**
     * Get or create a unique device identifier for the current client.
     */
    public function getDeviceId(): string
    {
        $deviceId = request()->cookie('piyoh_device_token')
            ?? Session::get('piyoh_device_token');

        if (! $deviceId) {
            $deviceId = (string) Str::uuid();
            Session::put('piyoh_device_token', $deviceId);
            Cookie::queue('piyoh_device_token', $deviceId, 60 * 24 * 7); // 7 days
        }

        return $deviceId;
    }

    /**
     * Get the active table session.
     */
    public function getActiveTableSession(): ?TableSession
    {
        $sessionCode = Session::get('qr_session_code')
            ?? request()->header('X-Table-Session')
            ?? request()->input('session_code');

        if (! $sessionCode) {
            return null;
        }

        $tableSession = TableSession::with('table')
            ->where('session_code', $sessionCode)
            ->where('status', 'open')
            ->first();

        if ($tableSession && $tableSession->isExpired()) {
            $tableSession->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);
            Session::forget('qr_session_code');

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

        return $tableSession && $tableSession->table ? $tableSession->table->outlet_id : null;
    }

    /**
     * Add an item to the shared table session cart.
     *
     * Handles race conditions using DB transactions with row-level locking.
     */
    public function add(int $productId, int $quantity = 1, array $options = [], ?string $notes = null, ?string $deviceId = null): string
    {
        $tableSession = $this->getActiveTableSession();
        if (! $tableSession) {
            throw new \InvalidArgumentException('Sesi meja tidak aktif atau telah kedaluwarsa.');
        }

        $deviceId = $deviceId ?: $this->getDeviceId();

        if ($tableSession->isLockedForDevice($deviceId)) {
            throw new \RuntimeException('Meja sedang memproses checkout dari perangkat lain. Mohon tunggu sebentar.');
        }

        $product = Product::find($productId);
        if (! $product || $product->base_price === null || (float) $product->base_price <= 0) {
            throw new \InvalidArgumentException('Item ini harus dipesan langsung ke kasir, silakan hubungi staff kami.');
        }

        if ($product->isOutOfStock()) {
            throw new \InvalidArgumentException("Mohon maaf, {$product->name} sedang habis saat ini.");
        }

        $cartKey = $this->makeCartKey($productId, $options, $notes);

        DB::transaction(function () use ($tableSession, $productId, $cartKey, $quantity, $options, $notes, $deviceId) {
            $cartItem = CartItem::where('table_session_id', $tableSession->id)
                ->where('cart_key', $cartKey)
                ->lockForUpdate()
                ->first();

            if ($cartItem) {
                $cartItem->increment('quantity', $quantity);
                $cartItem->update(['device_id' => $deviceId]);
            } else {
                CartItem::create([
                    'table_session_id' => $tableSession->id,
                    'product_id'       => $productId,
                    'cart_key'         => $cartKey,
                    'quantity'         => $quantity,
                    'options'          => $options,
                    'notes'            => $notes,
                    'device_id'        => $deviceId,
                ]);
            }
        });

        return $cartKey;
    }

    /**
     * Update item quantity in the cart by cart_key.
     * Automatically removes the entry if quantity drops to <= 0.
     */
    public function updateQuantity(string $cartKey, int $quantity, ?string $deviceId = null): void
    {
        $tableSession = $this->getActiveTableSession();
        if (! $tableSession) {
            return;
        }

        $deviceId = $deviceId ?: $this->getDeviceId();
        if ($tableSession->isLockedForDevice($deviceId)) {
            throw new \RuntimeException('Meja sedang memproses checkout dari perangkat lain.');
        }

        DB::transaction(function () use ($tableSession, $cartKey, $quantity) {
            $cartItem = CartItem::where('table_session_id', $tableSession->id)
                ->where('cart_key', $cartKey)
                ->lockForUpdate()
                ->first();

            if ($cartItem) {
                if ($quantity <= 0) {
                    $cartItem->delete();
                } else {
                    $cartItem->update(['quantity' => $quantity]);
                }
            }
        });
    }

    /**
     * Remove an item from the shared cart by cart_key.
     */
    public function remove(string $cartKey, ?string $deviceId = null): void
    {
        $tableSession = $this->getActiveTableSession();
        if (! $tableSession) {
            return;
        }

        $deviceId = $deviceId ?: $this->getDeviceId();
        if ($tableSession->isLockedForDevice($deviceId)) {
            throw new \RuntimeException('Meja sedang memproses checkout dari perangkat lain.');
        }

        CartItem::where('table_session_id', $tableSession->id)
            ->where('cart_key', $cartKey)
            ->delete();
    }

    /**
     * Get all items in the shared cart with full Product details and calculated prices.
     */
    public function get(?TableSession $session = null): array
    {
        $tableSession = $session ?: $this->getActiveTableSession();
        if (! $tableSession) {
            return [];
        }

        $cartItems = CartItem::with('product')
            ->where('table_session_id', $tableSession->id)
            ->orderBy('id', 'asc')
            ->get();

        if ($cartItems->isEmpty()) {
            return [];
        }

        $productIds = $cartItems->pluck('product_id')->unique()->all();
        $outletId   = $tableSession->table ? $tableSession->table->outlet_id : null;

        $overrides = [];
        if ($outletId) {
            $overrides = ProductPrice::where('outlet_id', $outletId)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
        }

        $items = [];
        foreach ($cartItems as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $price = $product->base_price;
            if (isset($overrides[$product->id])) {
                $price = $overrides[$product->id]->price;
            }

            $items[] = [
                'id'         => $item->id,
                'cart_key'   => $item->cart_key,
                'product_id' => $product->id,
                'product'    => $product,
                'quantity'   => $item->quantity,
                'price'      => (float) $price,
                'options'    => $item->options ?? [],
                'notes'      => $item->notes,
                'device_id'  => $item->device_id,
                'subtotal'   => (float) $price * $item->quantity,
            ];
        }

        return $items;
    }

    /**
     * Get the grand total of the shared cart.
     */
    public function total(?TableSession $session = null): float
    {
        $items = $this->get($session);

        return array_sum(array_column($items, 'subtotal'));
    }

    /**
     * Get total quantity of items in the cart.
     */
    public function count(?TableSession $session = null): int
    {
        $items = $this->get($session);

        return (int) array_sum(array_column($items, 'quantity'));
    }

    /**
     * Clear all items in the shared cart.
     */
    public function clear(?TableSession $session = null): void
    {
        $tableSession = $session ?: $this->getActiveTableSession();
        if ($tableSession) {
            CartItem::where('table_session_id', $tableSession->id)->delete();
        }
    }

    /**
     * Lock the table session cart during checkout.
     */
    public function lockCart(?string $deviceId = null): void
    {
        $tableSession = $this->getActiveTableSession();
        if ($tableSession) {
            $deviceId = $deviceId ?: $this->getDeviceId();
            $tableSession->lockCart($deviceId);
        }
    }

    /**
     * Unlock the table session cart if checkout is cancelled or failed.
     */
    public function unlockCart(): void
    {
        $tableSession = $this->getActiveTableSession();
        if ($tableSession) {
            $tableSession->unlockCart();
        }
    }
}
