<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PiyohPOS - Cart</title>
    <style>
        body { font-family: sans-serif; background: #fafafa; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #eee; }
        .item-details h3 { margin: 0 0 5px 0; font-size: 16px; }
        .item-details p { margin: 0; color: #666; font-size: 14px; }
        .item-price { font-weight: bold; }
        .total-section { margin-top: 20px; padding-top: 15px; border-top: 2px solid #eee; text-align: right; }
        .total-price { font-size: 20px; font-weight: bold; color: #059669; }
        .checkout-form { margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn-checkout { width: 100%; background: #10b981; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .back-link { display: inline-block; margin-top: 15px; color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Your Cart</h1>
        <p>Table: {{ $tableSession->table->number }}</p>

        @if(count($items) === 0)
            <p>Your cart is empty.</p>
            <a href="{{ route('qr.menu') }}" class="back-link">Back to Menu</a>
        @else
            @foreach($items as $item)
                <div class="cart-item">
                    <div class="item-details">
                        <h3>{{ $item['product']->name }}</h3>
                        <p>Quantity: {{ $item['quantity'] }} @ Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                    </div>
                    <span class="item-price">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach

            <div class="total-section">
                <p>Subtotal: <strong>Rp {{ number_format($total, 0, ',', '.') }}</strong></p>
                <p>Tax (10%): <strong>Rp {{ number_format($total * 0.1, 0, ',', '.') }}</strong></p>
                <p>Service (5%): <strong>Rp {{ number_format($total * 0.05, 0, ',', '.') }}</strong></p>
                <p class="total-price">Total: Rp {{ number_format($total * 1.15, 0, ',', '.') }}</p>
            </div>

            <form action="{{ route('qr.checkout') }}" method="POST" class="checkout-form">
                @csrf
                <div class="form-group">
                    <label for="customer_name">Your Name</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Enter your name" required>
                </div>
                <button type="submit" class="btn-checkout">Place Order</button>
            </form>

            <a href="{{ route('qr.menu') }}" class="back-link">← Add More Items</a>
        @endif
    </div>
</body>
</html>
