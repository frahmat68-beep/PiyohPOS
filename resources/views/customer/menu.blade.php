<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PiyohPOS - Menu</title>
    <style>
        body { font-family: sans-serif; background: #fafafa; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1, h2 { color: #111; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .cart-link { background: #3b82f6; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .category-section { margin-bottom: 25px; }
        .product-card { display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #eaeaea; border-radius: 8px; margin-bottom: 10px; }
        .product-info h3 { margin: 0 0 5px 0; font-size: 16px; }
        .product-info p { margin: 0; color: #666; font-size: 14px; }
        .price { font-weight: bold; color: #059669; }
        .add-form { display: flex; gap: 8px; align-items: center; }
        .qty-input { width: 50px; padding: 6px; border: 1px solid #ccc; border-radius: 4px; text-align: center; }
        .add-btn { background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>PiyohPOS</h1>
                <p>Table: {{ $tableSession->table->number }} ({{ $tableSession->table->outlet->name }})</p>
            </div>
            <a href="{{ route('qr.cart') }}" class="cart-link" id="view-cart-btn">Cart ({{ count($items) }})</a>
        </div>

        @if(session('success'))
            <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                {{ session('success') }}
            </div>
        @endif

        @foreach($categories as $category)
            @if($category->products->count() > 0)
                <div class="category-section">
                    <h2>{{ $category->name }}</h2>
                    @foreach($category->products as $product)
                        <div class="product-card">
                            <div class="product-info">
                                <h3>{{ $product->name }}</h3>
                                <p>{{ $product->description }}</p>
                                <span class="price">
                                    Rp {{ number_format($product->base_price, 0, ',', '.') }}
                                </span>
                            </div>
                            <form action="{{ route('qr.cart.add') }}" method="POST" class="add-form">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="number" name="quantity" value="1" min="1" class="qty-input">
                                <button type="submit" class="add-btn">Add</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
</body>
</html>
