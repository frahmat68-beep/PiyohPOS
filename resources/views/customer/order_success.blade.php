<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PiyohPOS - Order Success</title>
    <style>
        body { font-family: sans-serif; background: #fafafa; margin: 0; padding: 20px; color: #333; text-align: center; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 40px 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { color: #10b981; margin-bottom: 10px; }
        .order-number { font-size: 24px; font-weight: bold; margin: 20px 0; padding: 10px; background: #f0fdf4; border: 1px dashed #10b981; border-radius: 6px; display: inline-block; }
        p { color: #666; line-height: 1.6; }
        .btn-home { display: inline-block; background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; text-decoration: none; margin-top: 25px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Order Placed Successfully!</h1>
        <p>Your order has been sent to the kitchen. Please wait at your table.</p>
        
        <div class="order-number">
            {{ $order->order_number }}
        </div>

        <p>Total Amount (Inc. Tax & Service): <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></p>

        <a href="{{ route('qr.menu') }}" class="btn-home">Order More</a>
    </div>
</body>
</html>
