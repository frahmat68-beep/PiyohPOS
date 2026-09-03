<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Piyoh Kopi — Menu Meja {{ $tableSession->table->number }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#22261E] pb-32 antialiased selection:bg-[#475638] selection:text-white" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <main id="main-content" class="max-w-xl mx-auto px-3.5 sm:px-5 pt-5" role="main">
        
        {{-- Table Session Header (Clean, Premium, Calm) --}}
        <header class="bg-white border border-[#EBE4D8] rounded-3xl p-4 sm:p-5 shadow-sm mb-5 flex items-center justify-between">
            <div class="space-y-0.5">
                <span class="text-xs font-bold uppercase tracking-wider text-[#9A5A1A]">Meja {{ $tableSession->table->number }}</span>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight font-serif text-[#22261E]">{{ $tableSession->table->outlet->name }}</h1>
            </div>
            <a href="/cart" id="top-cart-btn" class="relative inline-flex items-center gap-2 rounded-full bg-[#475638] hover:bg-[#36422A] min-h-[44px] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all duration-200 active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span id="top-cart-text">Pesanan (<span class="cart-count-badge">{{ $cartCount }}</span>)</span>
            </a>
        </header>

        {{-- Cart Lock Banner --}}
        <div id="cart-lock-banner" class="{{ $isLocked ? 'flex' : 'hidden' }} mb-5 p-3.5 bg-[#FFFBEB] border border-[#FDE68A] text-[#92400E] rounded-2xl text-xs font-semibold items-center gap-2.5 shadow-sm">
            <svg class="w-4 h-4 shrink-0 text-[#D97706] animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Perangkat lain di Meja {{ $tableSession->table->number }} sedang memproses pembayaran... Menu sementara terkunci.</span>
        </div>

        {{-- Sticky Category Quick-Jump Bar --}}
        <div class="sticky top-2 z-30 bg-[#FAF7F2]/95 backdrop-blur-md py-2 -mx-3.5 px-3.5 sm:-mx-5 sm:px-5 mb-5 overflow-x-auto scrollbar-none border-b border-[#EBE4D8]/60">
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-none">
                @foreach($categories as $cat)
                    @if($cat->products->count() > 0)
                        <a href="#cat-{{ $cat->slug }}" class="shrink-0 whitespace-nowrap min-h-[44px] px-4 py-2 rounded-full text-sm font-semibold bg-white border border-[#EBE4D8] text-[#575E50] hover:bg-[#475638] hover:text-white transition-all duration-200 active:scale-95 shadow-2xs inline-flex items-center">
                            {{ $cat->name }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Categories & 2-Column Product Grid --}}
        <div class="space-y-8">
            @foreach($categories as $category)
                @if($category->products->count() > 0)
                    @php
                        $isBeverage = in_array($category->slug, [
                            'hot-coffee', 'iced-coffee', 'non-coffee', 'signature-drink',
                            'blended', 'artisan-tea', 'iced-tea', 'baristas-present', 'choco-series'
                        ]);
                    @endphp
                    <section id="cat-{{ $category->slug }}" class="scroll-mt-20">
                        {{-- Clean Category Heading without clutter badges/bars --}}
                        <div class="mb-3.5">
                            <h2 class="text-xl font-bold font-serif text-[#22261E] tracking-tight">{{ $category->name }}</h2>
                        </div>

                        {{-- 2-Column Grid with Stagger Animation --}}
                        <div class="grid grid-cols-2 gap-3 sm:gap-4">
                            @foreach($category->products as $product)
                                @php
                                    $totalQty       = $cartCountByProduct[$product->id] ?? 0;
                                    $isOutOfStock   = $product->isOutOfStock();
                                    $isAvailable    = ! $isOutOfStock && $product->base_price !== null && (float) $product->base_price > 0;
                                    $image          = $product->getEffectiveImageUrl();
                                    $formattedPrice = $isAvailable ? 'Rp ' . number_format($product->base_price, 0, ',', '.') : ($isOutOfStock ? 'Habis' : 'Tanya Barista');
                                    $staggerDelay   = min($loop->index * 40, 360);
                                @endphp
                                <div class="product-card animate-fade-in-up bg-white border border-[#EBE4D8] rounded-2xl p-3 sm:p-3.5 shadow-2xs hover:shadow-md transition-all duration-200 flex flex-col justify-between {{ $isOutOfStock ? 'opacity-60 grayscale' : '' }}" 
                                     style="animation-delay: {{ $staggerDelay }}ms;"
                                     id="product-card-{{ $product->id }}">
                                    <div>
                                        {{-- Image Thumbnail with Shimmer Skeleton --}}
                                        <div class="aspect-[4/3] rounded-xl bg-[#FAF7F2] border border-[#F3ECE1] overflow-hidden mb-2.5 relative skeleton-shimmer">
                                            @if($image)
                                                <img src="{{ $image }}" 
                                                     alt="{{ $product->name }}" 
                                                     class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" 
                                                     loading="lazy"
                                                     onload="this.parentElement.classList.remove('skeleton-shimmer')">
                                            @else
                                                <div class="w-full h-full flex flex-col items-center justify-center text-[#889180] font-serif text-xs bg-[#F7F3EB]">
                                                    <span class="text-base font-bold text-[#475638]">P</span>
                                                    <span class="text-[10px] uppercase tracking-wider mt-0.5">Piyoh</span>
                                                </div>
                                            @endif
                                            
                                            {{-- Total qty badge --}}
                                            <span class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-[#C4823F] text-white text-[10px] font-bold items-center justify-center shadow-sm {{ $totalQty > 0 ? 'flex' : 'hidden' }}" id="qty-badge-{{ $product->id }}">
                                                {{ $totalQty }}
                                            </span>

                                            @if($isOutOfStock)
                                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                                    <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-bold uppercase tracking-wider shadow">Habis</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Name & Clean Price (High Readability) --}}
                                        <h3 class="font-bold text-[#22261E] text-sm sm:text-base font-serif line-clamp-1 leading-snug" title="{{ $product->name }}">{{ $product->name }}</h3>
                                        @if(!empty($product->description))
                                            <p class="text-xs sm:text-sm text-[#575E50] line-clamp-2 mt-1 leading-snug font-normal">{{ $product->description }}</p>
                                        @endif
                                        
                                        <div class="mt-2.5 flex items-baseline justify-between">
                                            <span class="text-sm sm:text-base font-bold text-[#475638] font-serif tracking-tight">{{ $formattedPrice }}</span>
                                        </div>
                                    </div>

                                    {{-- Action Button (44px Minimum Touch Target) --}}
                                    <div class="mt-3">
                                        @if($isAvailable)
                                            <button type="button" 
                                                    onclick="openCustomizer({{ $product->id }}, '{{ addslashes($product->name) }}', {{ (float)$product->base_price }}, {{ $isBeverage ? 'true' : 'false' }})"
                                                    class="btn-add-product w-full min-h-[44px] rounded-xl bg-[#FAF7F2] hover:bg-[#475638] text-[#475638] hover:text-white border border-[#EBE4D8] hover:border-[#475638] py-2.5 px-3 text-sm font-bold transition-all duration-150 flex items-center justify-center gap-1.5 active:scale-95 shadow-2xs cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                                <span>Pilih</span>
                                            </button>
                                        @elseif($isOutOfStock)
                                            <button disabled class="w-full min-h-[44px] rounded-xl bg-stone-100 text-stone-400 py-2.5 text-xs font-medium cursor-not-allowed">
                                                Stok Habis
                                            </button>
                                        @else
                                            <span class="block text-center text-xs text-[#575E50] font-medium py-2.5">Tanya Kasir</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>

    </main>

    {{-- Floating Cart Bottom Bar (Thumb Zone Action) --}}
    <div id="floating-cart-bar" class="fixed bottom-4 left-3.5 right-3.5 max-w-xl mx-auto z-40 {{ $cartCount > 0 ? '' : 'hidden' }} transition-all duration-300 transform translate-y-0">
        <a href="/cart" class="flex items-center justify-between rounded-full bg-[#222920] text-white p-3 pl-5 sm:p-3.5 sm:pl-6 shadow-2xl border border-white/10 backdrop-blur-md transition-all duration-200 hover:bg-[#161A14] active:scale-[0.98] min-h-[56px]">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <span id="floating-cart-badge" class="w-7 h-7 rounded-full bg-[#C4823F] text-white text-xs font-bold flex items-center justify-center cart-count-badge transition-transform">
                        {{ $cartCount }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-[#889180] block font-light">Keranjang Bersama</span>
                    <span class="text-sm sm:text-base font-bold font-serif text-[#FAF7F2]" id="floating-cart-total">
                        Rp {{ number_format($total, 0, ',', '.') }}
                    </span>
                </div>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full bg-[#475638] px-5 py-2.5 text-sm font-bold text-white shadow-sm min-h-[44px]">
                <span>Lihat Pesanan</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
    </div>

    {{-- Toast Notification --}}
    <div id="toast-notify" class="fixed top-4 left-4 right-4 max-w-sm mx-auto z-50 hidden transition-all duration-300 transform -translate-y-4 opacity-0">
        <div class="bg-[#222920]/95 backdrop-blur-md text-white text-sm font-semibold px-4 py-3 rounded-2xl shadow-xl border border-white/10 flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="toast-message">Item ditambahkan</span>
        </div>
    </div>

    {{-- Item Customizer Mobile Bottom-Sheet Modal --}}
    <div id="customizer-modal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 hidden transition-opacity duration-300">
        {{-- Backdrop --}}
        <div id="customizer-backdrop" onclick="closeCustomizer()" class="fixed inset-0 bg-black/60 backdrop-blur-xs opacity-0 transition-opacity duration-300"></div>

        {{-- Bottom Sheet Container --}}
        <div id="customizer-sheet" class="relative z-10 bg-[#FAF7F2] w-full max-w-lg rounded-t-[2.5rem] sm:rounded-3xl p-6 sm:p-7 max-h-[90vh] overflow-y-auto space-y-5 shadow-2xl border border-[#EBE4D8] transform translate-y-full sm:translate-y-0 transition-transform duration-320 ease-out">
            {{-- Mobile Drawer Drag Handle --}}
            <div class="w-12 h-1.5 bg-[#DDD4C5] rounded-full mx-auto -mt-1 mb-3 sm:hidden"></div>

            {{-- Header (Clean, High Visibility) --}}
            <div class="flex items-start justify-between border-b border-[#EBE4D8] pb-4">
                <div>
                    <h3 id="modal-product-name" class="font-bold text-xl sm:text-2xl font-serif text-[#161A14]">Custom Menu</h3>
                    <span id="modal-product-price" class="text-base sm:text-lg font-bold font-serif text-[#475638] mt-1 block">Rp 0</span>
                </div>
                <button type="button" onclick="closeCustomizer()" class="min-w-[44px] min-h-[44px] w-11 h-11 rounded-full bg-white border border-[#EBE4D8] text-[#575E50] hover:text-[#161A14] hover:bg-[#F3ECE1] transition-all flex items-center justify-center shadow-2xs active:scale-90" aria-label="Tutup">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Beverage Options --}}
            <div id="modal-beverage-options" class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-bold text-[#475638] uppercase tracking-wider">Level Es</label>
                        <span class="text-xs text-[#889180]">Pilih 1</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2.5">
                        <button type="button" data-modal-ice="Normal Ice" onclick="setModalIce('Normal Ice')" class="modal-ice-btn active min-h-[50px] px-2.5 py-2.5 rounded-2xl text-sm font-bold border-2 border-[#475638] bg-[#475638] text-white shadow-sm flex flex-col items-center justify-center transition-all duration-150 active:scale-95">
                            <span>🧊 Normal</span>
                        </button>
                        <button type="button" data-modal-ice="Less Ice" onclick="setModalIce('Less Ice')" class="modal-ice-btn min-h-[50px] px-2.5 py-2.5 rounded-2xl text-sm font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:border-[#DDD4C5] hover:bg-[#FAF7F2] flex flex-col items-center justify-center transition-all duration-150 active:scale-95">
                            <span>❄️ Sedikit Es</span>
                        </button>
                        <button type="button" data-modal-ice="No Ice" onclick="setModalIce('No Ice')" class="modal-ice-btn min-h-[50px] px-2.5 py-2.5 rounded-2xl text-sm font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:border-[#DDD4C5] hover:bg-[#FAF7F2] flex flex-col items-center justify-center transition-all duration-150 active:scale-95">
                            <span>🚫 Tanpa Es</span>
                        </button>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-bold text-[#475638] uppercase tracking-wider">Level Gula</label>
                        <span class="text-xs text-[#889180]">Pilih 1</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2.5">
                        <button type="button" data-modal-sugar="Normal Sugar" onclick="setModalSugar('Normal Sugar')" class="modal-sugar-btn active min-h-[50px] px-2.5 py-2.5 rounded-2xl text-sm font-bold border-2 border-[#475638] bg-[#475638] text-white shadow-sm flex flex-col items-center justify-center transition-all duration-150 active:scale-95">
                            <span>🍯 Normal</span>
                        </button>
                        <button type="button" data-modal-sugar="Less Sugar" onclick="setModalSugar('Less Sugar')" class="modal-sugar-btn min-h-[50px] px-2.5 py-2.5 rounded-2xl text-sm font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:border-[#DDD4C5] hover:bg-[#FAF7F2] flex flex-col items-center justify-center transition-all duration-150 active:scale-95">
                            <span>🌿 Sedikit</span>
                        </button>
                        <button type="button" data-modal-sugar="No Sugar" onclick="setModalSugar('No Sugar')" class="modal-sugar-btn min-h-[50px] px-2.5 py-2.5 rounded-2xl text-sm font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:border-[#DDD4C5] hover:bg-[#FAF7F2] flex flex-col items-center justify-center transition-all duration-150 active:scale-95">
                            <span>☕ Tanpa Gula</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Notes Input (16px to prevent iOS zoom) --}}
            <div>
                <label for="modal-notes-input" class="block text-sm font-bold text-[#575E50] mb-1.5 uppercase tracking-wider">Catatan Khusus (Opsional)</label>
                <input type="text" id="modal-notes-input" placeholder="Contoh: jangan terlalu manis..." class="w-full bg-white border border-[#DDD4C5] rounded-2xl px-4 py-3.5 text-base text-[#22261E] placeholder:text-[#889180] focus:outline-none focus:border-[#475638] focus:ring-2 focus:ring-[#475638]/20 shadow-2xs transition-all">
            </div>

            {{-- Quantity Stepper & Submit Button (44px Minimum Touch Targets) --}}
            <div class="pt-2 flex items-center gap-3">
                <div class="flex items-center border border-[#EBE4D8] rounded-2xl bg-white p-1.5 shadow-2xs">
                    <button type="button" onclick="adjustModalQty(-1)" class="min-w-[44px] min-h-[44px] w-11 h-11 rounded-xl bg-[#FAF7F2] hover:bg-[#F3ECE1] text-[#22261E] flex items-center justify-center font-bold text-lg active:scale-90 transition-transform cursor-pointer" aria-label="Kurangi Jumlah">-</button>
                    <span id="modal-qty-display" class="w-10 text-center text-base font-bold text-[#22261E] transition-transform">1</span>
                    <button type="button" onclick="adjustModalQty(1)" class="min-w-[44px] min-h-[44px] w-11 h-11 rounded-xl bg-[#FAF7F2] hover:bg-[#F3ECE1] text-[#22261E] flex items-center justify-center font-bold text-lg active:scale-90 transition-transform cursor-pointer" aria-label="Tambah Jumlah">+</button>
                </div>
                <button type="button" id="modal-submit-btn" onclick="submitModalToCart()" class="flex-1 min-h-[52px] rounded-2xl bg-[#475638] hover:bg-[#36422A] text-white font-bold py-3.5 px-5 text-sm sm:text-base shadow-md hover:shadow-lg transition-all duration-150 flex items-center justify-between active:scale-[0.98] cursor-pointer">
                    <span id="modal-submit-label">Tambah ke Keranjang</span>
                    <span id="modal-total-btn-price" class="bg-white/20 px-3 py-1 rounded-xl font-serif text-sm sm:text-base">Rp 0</span>
                </button>
            </div>
        </div>
    </div>

    {{-- JavaScript Logic --}}
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let currentModalProduct = { id: null, name: '', basePrice: 0, isBeverage: false, qty: 1, ice: 'Normal Ice', sugar: 'Normal Sugar' };
        let lastCartCount = {{ $cartCount }};
        let isCartLockedState = {{ $isLocked ? 'true' : 'false' }};

        function openCustomizer(id, name, basePrice, isBeverage) {
            if (isCartLockedState) {
                alert('Meja sedang memproses checkout dari perangkat lain. Mohon tunggu sebentar.');
                return;
            }
            currentModalProduct = { id, name, basePrice, isBeverage, qty: 1, ice: 'Normal Ice', sugar: 'Normal Sugar' };
            document.getElementById('modal-product-name').textContent = name;
            document.getElementById('modal-product-price').textContent = 'Rp ' + basePrice.toLocaleString('id-ID');
            document.getElementById('modal-qty-display').textContent = '1';
            document.getElementById('modal-notes-input').value = '';
            
            const bevOptions = document.getElementById('modal-beverage-options');
            if (isBeverage) {
                bevOptions.classList.remove('hidden');
                setModalIce('Normal Ice');
                setModalSugar('Normal Sugar');
            } else {
                bevOptions.classList.add('hidden');
            }

            recalculateModalTotal();
            
            // Bottom-sheet opening animation
            const modalEl = document.getElementById('customizer-modal');
            const backdrop = document.getElementById('customizer-backdrop');
            const sheet = document.getElementById('customizer-sheet');
            
            modalEl.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                sheet.classList.remove('translate-y-full');
                sheet.classList.add('translate-y-0');
            }, 10);
        }

        function closeCustomizer() {
            const modalEl = document.getElementById('customizer-modal');
            const backdrop = document.getElementById('customizer-backdrop');
            const sheet = document.getElementById('customizer-sheet');
            
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            sheet.classList.remove('translate-y-0');
            sheet.classList.add('translate-y-full');
            
            setTimeout(() => {
                modalEl.classList.add('hidden');
            }, 300);
        }

        function setModalIce(level) {
            currentModalProduct.ice = level;
            document.querySelectorAll('.modal-ice-btn').forEach(btn => {
                if (btn.getAttribute('data-modal-ice') === level) {
                    btn.className = "modal-ice-btn active min-h-[48px] px-2.5 py-2 rounded-2xl text-xs font-bold border-2 border-[#475638] bg-[#475638] text-white shadow-sm flex flex-col items-center justify-center transition-all duration-150 active:scale-95";
                } else {
                    btn.className = "modal-ice-btn min-h-[48px] px-2.5 py-2 rounded-2xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:border-[#DDD4C5] hover:bg-[#FAF7F2] flex flex-col items-center justify-center transition-all duration-150 active:scale-95";
                }
            });
        }

        function setModalSugar(level) {
            currentModalProduct.sugar = level;
            document.querySelectorAll('.modal-sugar-btn').forEach(btn => {
                if (btn.getAttribute('data-modal-sugar') === level) {
                    btn.className = "modal-sugar-btn active min-h-[48px] px-2.5 py-2 rounded-2xl text-xs font-bold border-2 border-[#475638] bg-[#475638] text-white shadow-sm flex flex-col items-center justify-center transition-all duration-150 active:scale-95";
                } else {
                    btn.className = "modal-sugar-btn min-h-[48px] px-2.5 py-2 rounded-2xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:border-[#DDD4C5] hover:bg-[#FAF7F2] flex flex-col items-center justify-center transition-all duration-150 active:scale-95";
                }
            });
        }

        function adjustModalQty(delta) {
            currentModalProduct.qty = Math.max(1, currentModalProduct.qty + delta);
            const qtyDisplay = document.getElementById('modal-qty-display');
            qtyDisplay.textContent = currentModalProduct.qty;
            qtyDisplay.classList.add('animate-qty-pop');
            setTimeout(() => qtyDisplay.classList.remove('animate-qty-pop'), 200);
            recalculateModalTotal();
        }

        function recalculateModalTotal() {
            const grandTotal = currentModalProduct.basePrice * currentModalProduct.qty;
            document.getElementById('modal-total-btn-price').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
        }

        async function submitModalToCart() {
            const submitBtn = document.getElementById('modal-submit-btn');
            const submitLabel = document.getElementById('modal-submit-label');
            
            // Visual loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-80', 'pointer-events-none');
            submitLabel.innerHTML = `
                <svg class="w-4 h-4 animate-spin inline mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Menambahkan...
            `;

            try {
                let noteParts = [];
                if (currentModalProduct.isBeverage) {
                    noteParts.push(`Level Es: ${currentModalProduct.ice}`);
                    noteParts.push(`Level Gula: ${currentModalProduct.sugar}`);
                }
                const customNote = document.getElementById('modal-notes-input').value.trim();
                if (customNote) noteParts.push(customNote);

                const finalNotes = noteParts.length > 0 ? noteParts.join(' | ') : null;

                const res = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        product_id: currentModalProduct.id,
                        quantity: currentModalProduct.qty,
                        notes: finalNotes
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    alert(data.error || 'Gagal menambahkan ke keranjang.');
                    return;
                }

                closeCustomizer();
                updateCartBar(data.cart_count, data.cart_total_formatted);
                showToast(`${currentModalProduct.name} ditambahkan ke pesanan meja!`);

                const badge = document.getElementById('qty-badge-' + currentModalProduct.id);
                if (badge) {
                    const currentBadgeCount = parseInt(badge.textContent) || 0;
                    badge.textContent = currentBadgeCount + currentModalProduct.qty;
                    badge.classList.remove('hidden');
                    badge.classList.add('flex');
                }
                lastCartCount = data.cart_count;
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan jaringan.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-80', 'pointer-events-none');
                submitLabel.textContent = 'Tambah ke Keranjang';
            }
        }

        function updateCartBar(count, totalFormatted) {
            document.querySelectorAll('.cart-count-badge').forEach(b => {
                b.textContent = count;
                b.classList.add('animate-cart-bounce');
                setTimeout(() => b.classList.remove('animate-cart-bounce'), 450);
            });
            const totalText = document.getElementById('floating-cart-total');
            if (totalText) totalText.textContent = totalFormatted;

            const bar = document.getElementById('floating-cart-bar');
            if (bar) {
                if (count > 0) bar.classList.remove('hidden');
                else bar.classList.add('hidden');
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('toast-notify');
            const msgEl = document.getElementById('toast-message');
            if (!toast || !msgEl) return;

            msgEl.textContent = msg;
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.remove('-translate-y-4', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
            }, 10);

            setTimeout(() => {
                toast.classList.add('-translate-y-4', 'opacity-0');
                toast.classList.remove('translate-y-0', 'opacity-100');
                setTimeout(() => toast.classList.add('hidden'), 300);
            }, 2500);
        }

        // Real-time multi-device polling (every 5 seconds)
        async function pollCartSync() {
            try {
                const res = await fetch('/cart/sync', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;

                const data = await res.json();
                
                // Handle cart lock banner
                const lockBanner = document.getElementById('cart-lock-banner');
                isCartLockedState = data.is_locked;
                if (lockBanner) {
                    if (data.is_locked) lockBanner.classList.remove('hidden');
                    else lockBanner.classList.add('hidden');
                }

                // If cart count changed from another device
                if (data.cart_count !== lastCartCount) {
                    if (data.cart_count > lastCartCount) {
                        showToast(`Keranjang diperbarui (+${data.cart_count - lastCartCount} item dari meja)`);
                    }
                    lastCartCount = data.cart_count;
                    updateCartBar(data.cart_count, data.cart_total_formatted);

                    // Update product badges
                    document.querySelectorAll('[id^="qty-badge-"]').forEach(badge => {
                        const pid = badge.id.replace('qty-badge-', '');
                        const qty = data.cart_count_by_product[pid] || 0;
                        if (qty > 0) {
                            badge.textContent = qty;
                            badge.classList.remove('hidden');
                            badge.classList.add('flex');
                        } else {
                            badge.classList.add('hidden');
                            badge.classList.remove('flex');
                        }
                    });
                }
            } catch (err) {
                console.error('Polling error', err);
            }
        }

        setInterval(pollCartSync, 5000);
    </script>
</body>
</html>
