<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Piyoh Kopi — Keranjang Meja {{ $tableSession->table->number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#22261E] pb-16 antialiased selection:bg-[#475638] selection:text-white" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div class="max-w-lg mx-auto px-4 pt-6">
        
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('qr.menu') }}" class="w-10 h-10 rounded-full bg-white border border-[#EBE4D8] flex items-center justify-center text-[#575E50] hover:text-[#22261E] transition shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold font-serif text-[#22261E]">Keranjang Pesanan</h1>
                    <p class="text-xs text-[#889180]">Meja {{ $tableSession->table->number }} • {{ $tableSession->table->outlet->name }}</p>
                </div>
            </div>
            <span class="rounded-full bg-[#EBF0E6] text-[#475638] text-xs font-bold px-3 py-1">
                {{ count($items) }} Item
            </span>
        </div>

        {{-- Error Alerts --}}
        @if(session('error'))
            <div class="mb-5 p-4 bg-[#FEF2F2] border border-[#FECACA] text-[#B91C1C] rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(count($items) === 0)
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-10 text-center shadow-sm space-y-4">
                <div class="w-16 h-16 bg-[#F3ECE1] text-[#C4823F] rounded-full flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <h2 class="text-lg font-bold font-serif text-[#22261E]">Keranjang Masih Kosong</h2>
                <p class="text-xs text-[#575E50]">Pilih menu kopi atau pastry favoritmu terlebih dahulu sebelum melakukan pemesanan.</p>
                <div class="pt-2">
                    <a href="{{ route('qr.menu') }}" class="inline-flex items-center gap-2 rounded-full bg-[#475638] hover:bg-[#36422A] px-6 py-3 text-xs font-bold text-white shadow-sm transition">
                        <span>Lihat Buku Menu</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        @else
            {{-- Cart Items List --}}
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm divide-y divide-[#F3ECE1] mb-6">
                @foreach($items as $item)
                    <div class="cart-item py-4 first:pt-0 last:pb-0 flex items-start justify-between gap-4">
                        <div class="item-details space-y-1">
                            <h3 class="font-bold text-[#22261E] text-base font-serif">{{ $item['product']->name }}</h3>
                            <p class="text-xs text-[#889180]">
                                {{ $item['quantity'] }} &times; Rp {{ number_format($item['price'], 0, ',', '.') }}
                            </p>
                            @if(!empty($item['notes']))
                                <p class="text-[11px] text-[#C4823F] italic bg-[#FBF2E8] px-2 py-0.5 rounded-md inline-block">
                                    Catatan: {{ $item['notes'] }}
                                </p>
                            @endif
                        </div>
                        <span class="item-price text-sm font-bold text-[#475638] whitespace-nowrap pt-1">
                            Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Price Breakdown --}}
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm space-y-2.5 text-xs text-[#575E50] mb-6">
                <div class="flex justify-between">
                    <span>Subtotal Menu</span>
                    <span class="font-semibold text-[#22261E]">Rp {{ number_format($total, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>PB1 / Pajak Restoran (10%)</span>
                    <span class="font-semibold text-[#22261E]">Rp {{ number_format($total * 0.1, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Biaya Layanan (5%)</span>
                    <span class="font-semibold text-[#22261E]">Rp {{ number_format($total * 0.05, 0, ',', '.') }}</span>
                </div>
                <div class="total-section pt-3 border-t border-[#F3ECE1] flex justify-between items-baseline">
                    <span class="text-sm font-bold text-[#22261E]">Total Tagihan</span>
                    <span class="total-price text-lg font-bold text-[#475638]">
                        Rp {{ number_format($total * 1.15, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Checkout Form --}}
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm">
                <form action="{{ route('qr.checkout') }}" method="POST" class="checkout-form space-y-4">
                    @csrf
                    <div class="form-group space-y-1.5">
                        <label for="customer_name" class="block text-xs font-bold uppercase tracking-wider text-[#575E50]">Nama Pemesan *</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control w-full bg-[#FAF7F2] border border-[#DDD4C5] rounded-xl px-4 py-3 text-sm text-[#22261E] focus:outline-none focus:border-[#475638] transition placeholder:text-[#889180]" placeholder="Contoh: Kiki / Meja 01" required>
                    </div>
                    <button type="submit" class="btn-checkout w-full rounded-full bg-[#475638] hover:bg-[#36422A] text-white font-bold py-4 text-sm shadow-md transition transform active:scale-98 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Kirim Pesanan Sekarang</span>
                    </button>
                </form>
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('qr.menu') }}" class="back-link inline-flex items-center gap-1 text-xs font-bold text-[#475638] hover:text-[#36422A] transition">
                    &larr; Tambah Menu Lainnya
                </a>
            </div>
        @endif
    </div>
</body>
</html>
