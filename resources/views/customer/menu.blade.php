<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Piyoh Kopi — Menu Meja {{ $tableSession->table->number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#22261E] pb-28 antialiased selection:bg-[#475638] selection:text-white" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div class="max-w-lg mx-auto px-4 pt-6">
        
        {{-- Table Session Header --}}
        <header class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm mb-6 flex items-center justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#C4823F]">Meja {{ $tableSession->table->number }}</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight font-serif text-[#22261E]">{{ $tableSession->table->outlet->name }}</h1>
                <p class="text-xs text-[#889180]">Silakan pilih menu & pesanan langsung ke dapur</p>
            </div>
            <a href="{{ route('qr.cart') }}" id="view-cart-btn" class="cart-link relative inline-flex items-center gap-2 rounded-full bg-[#475638] hover:bg-[#36422A] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span>Cart ({{ count($items) }})</span>
            </a>
        </header>

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="mb-5 p-4 bg-[#F0FDF4] border border-[#BBF7D0] text-[#15803D] rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-5 p-4 bg-[#FEF2F2] border border-[#FECACA] text-[#B91C1C] rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Categories & Menu Items --}}
        <div class="space-y-8">
            @foreach($categories as $category)
                @if($category->products->count() > 0)
                    <section class="category-section">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-2 h-4 bg-[#475638] rounded-full"></span>
                            <h2 class="text-lg font-bold font-serif text-[#22261E]">{{ $category->name }}</h2>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#EBF0E6] text-[#475638]">
                                {{ $category->products->count() }}
                            </span>
                        </div>
                        <div class="space-y-3">
                            @foreach($category->products as $product)
                                <div class="product-card bg-white border border-[#EBE4D8] rounded-2xl p-4 shadow-sm flex flex-col justify-between gap-3 transition hover:border-[#DDD4C5]">
                                    <div class="product-info flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <h3 class="font-bold text-[#22261E] text-base font-serif">{{ $product->name }}</h3>
                                            <p class="text-xs text-[#575E50] leading-relaxed line-clamp-2">{{ $product->description }}</p>
                                        </div>
                                        <span class="price whitespace-nowrap text-sm font-bold text-[#475638] bg-[#FAF7F2] border border-[#EBE4D8] px-3 py-1 rounded-full">
                                            Rp {{ number_format($product->base_price, 0, ',', '.') }}
                                        </span>
                                    </div>
                                    <form action="{{ route('qr.cart.add') }}" method="POST" class="add-form flex items-center justify-between pt-3 border-t border-[#F3ECE1] gap-2">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <div class="flex items-center gap-1.5">
                                            <label class="text-[11px] text-[#889180] font-medium">Qty:</label>
                                            <input type="number" name="quantity" value="1" min="1" class="qty-input w-14 bg-[#FAF7F2] border border-[#DDD4C5] rounded-lg py-1.5 px-2 text-xs font-bold text-center text-[#22261E] focus:outline-none focus:border-[#475638]">
                                        </div>
                                        <button type="submit" class="add-btn inline-flex items-center gap-1.5 bg-[#475638] hover:bg-[#36422A] text-white text-xs font-bold py-2 px-5 rounded-full shadow-sm transition active:scale-95">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            <span>Tambah</span>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>

    </div>

    {{-- Sticky Floating Mobile Cart Bar --}}
    @if(count($items) > 0)
        <div class="fixed bottom-4 inset-x-0 z-40 px-4 max-w-lg mx-auto pointer-events-none">
            <a href="{{ route('qr.cart') }}" class="pointer-events-auto flex items-center justify-between bg-[#161A14] text-white border border-[#3A4437] rounded-full px-6 py-3.5 shadow-xl hover:bg-[#222920] transition transform active:scale-98">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-[#C4823F] text-white text-xs font-bold flex items-center justify-center">
                        {{ count($items) }}
                    </span>
                    <span class="text-sm font-semibold">Lihat Pesanan</span>
                </div>
                <span class="text-xs font-bold text-[#C4823F] flex items-center gap-1">
                    Buka Keranjang &rarr;
                </span>
            </a>
        </div>
    @endif
</body>
</html>
