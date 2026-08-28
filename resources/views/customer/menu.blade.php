<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
    <div class="max-w-xl mx-auto px-3.5 sm:px-5 pt-5">
        
        {{-- Table Session Header --}}
        <header class="bg-white border border-[#EBE4D8] rounded-3xl p-4 sm:p-5 shadow-sm mb-5 flex items-center justify-between">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#C4823F]">Meja {{ $tableSession->table->number }}</span>
                    <span class="text-[10px] bg-[#EBF0E6] text-[#475638] font-bold px-2 py-0.5 rounded-full">Shared Cart</span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold tracking-tight font-serif text-[#22261E]">{{ $tableSession->table->outlet->name }}</h1>
                <p class="text-xs text-[#889180]">Pesan bersama satu meja secara real-time</p>
            </div>
            <a href="/cart" id="top-cart-btn" class="relative inline-flex items-center gap-1.5 rounded-full bg-[#475638] hover:bg-[#36422A] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span id="top-cart-text">Cart (<span class="cart-count-badge">{{ $cartCount }}</span>)</span>
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
                        <a href="#cat-{{ $cat->slug }}" class="shrink-0 whitespace-nowrap px-3.5 py-1.5 rounded-full text-xs font-semibold bg-white border border-[#EBE4D8] text-[#575E50] hover:bg-[#475638] hover:text-white transition shadow-2xs">
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
                        <div class="flex items-center gap-2 mb-3.5">
                            <span class="w-1.5 h-4 bg-[#475638] rounded-full"></span>
                            <h2 class="text-base sm:text-lg font-bold font-serif text-[#22261E]">{{ $category->name }}</h2>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#EBF0E6] text-[#475638]">
                                {{ $category->products->count() }}
                            </span>
                        </div>

                        {{-- 2-Column Grid for Mobile --}}
                        <div class="grid grid-cols-2 gap-3 sm:gap-4">
                            @foreach($category->products as $product)
                                @php
                                    $totalQty       = $cartCountByProduct[$product->id] ?? 0;
                                    $isOutOfStock   = $product->isOutOfStock();
                                    $isAvailable    = ! $isOutOfStock && $product->base_price !== null && (float) $product->base_price > 0;
                                    $image          = $product->image_url;
                                    $formattedPrice = $isAvailable ? 'Rp ' . number_format($product->base_price, 0, ',', '.') : ($isOutOfStock ? 'Habis' : 'Tanya Barista');
                                @endphp
                                <div class="product-card bg-white border border-[#EBE4D8] rounded-2xl p-3 sm:p-3.5 shadow-2xs hover:shadow-md transition-all duration-200 flex flex-col justify-between {{ $isOutOfStock ? 'opacity-60 grayscale' : '' }}" id="product-card-{{ $product->id }}">
                                    <div>
                                        {{-- Image Thumbnail --}}
                                        <div class="aspect-[4/3] rounded-xl bg-[#FAF7F2] border border-[#F3ECE1] overflow-hidden mb-2.5 relative">
                                            @if($image)
                                                <img src="{{ $image }}" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" loading="lazy">
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

                                        {{-- Name & Price --}}
                                        <h3 class="font-bold text-[#22261E] text-xs sm:text-sm font-serif line-clamp-1 leading-snug" title="{{ $product->name }}">{{ $product->name }}</h3>
                                        <p class="text-[11px] sm:text-xs text-[#575E50] line-clamp-2 mt-1 leading-tight min-h-[28px] font-light">{{ $product->description ?: 'Racikan istimewa barista Piyoh Kopi.' }}</p>
                                        
                                        <div class="mt-2 flex items-baseline justify-between">
                                            <span class="text-xs sm:text-sm font-bold text-[#475638]">{{ $formattedPrice }}</span>
                                            @if($product->isLowStock() && ! $isOutOfStock)
                                                <span class="text-[9px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Sisa {{ $product->stock_quantity }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Action Button --}}
                                    <div class="mt-3 pt-2 border-t border-[#F3ECE1]">
                                        @if($isAvailable)
                                            <button type="button" 
                                                    onclick="openCustomizer({{ $product->id }}, '{{ addslashes($product->name) }}', {{ (float)$product->base_price }}, {{ $isBeverage ? 'true' : 'false' }})"
                                                    class="btn-add-product w-full rounded-xl bg-[#FAF7F2] hover:bg-[#475638] text-[#475638] hover:text-white border border-[#EBE4D8] hover:border-[#475638] py-2 text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95 shadow-2xs">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                                <span>Pilih</span>
                                            </button>
                                        @elseif($isOutOfStock)
                                            <button disabled class="w-full rounded-xl bg-stone-100 text-stone-400 py-2 text-xs font-medium cursor-not-allowed">
                                                Stok Habis
                                            </button>
                                        @else
                                            <span class="block text-center text-[10px] text-[#889180] font-medium py-1">Tanya Kasir</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>

    </div>

    {{-- Floating Cart Bottom Bar --}}
    <div id="floating-cart-bar" class="fixed bottom-4 left-3.5 right-3.5 max-w-xl mx-auto z-40 {{ $cartCount > 0 ? '' : 'hidden' }} transition-all duration-300 transform translate-y-0">
        <a href="/cart" class="flex items-center justify-between rounded-full bg-[#222920] text-white p-2.5 pl-5 sm:p-3 sm:pl-6 shadow-2xl border border-white/10 backdrop-blur-md transition hover:bg-[#161A14] active:scale-98">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <span class="w-7 h-7 rounded-full bg-[#C4823F] text-white text-xs font-bold flex items-center justify-center cart-count-badge">
                        {{ $cartCount }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-[#889180] block font-light">Keranjang Bersama</span>
                    <span class="text-sm font-bold font-serif text-[#FAF7F2]" id="floating-cart-total">
                        Rp {{ number_format($total, 0, ',', '.') }}
                    </span>
                </div>
            </div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#475638] px-4 py-2 text-xs font-bold text-white shadow-sm">
                <span>Lihat Pesanan</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
    </div>

    {{-- Toast Notification --}}
    <div id="toast-notify" class="fixed top-4 left-4 right-4 max-w-sm mx-auto z-50 hidden transition-all duration-300 transform -translate-y-4 opacity-0">
        <div class="bg-[#222920]/95 backdrop-blur-md text-white text-xs font-semibold px-4 py-3 rounded-2xl shadow-xl border border-white/10 flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="toast-message">Item ditambahkan</span>
        </div>
    </div>

    {{-- Item Customizer Modal --}}
    <div id="customizer-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 hidden transition-opacity duration-300">
        <div class="bg-white w-full max-w-md rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 max-h-[90vh] overflow-y-auto space-y-5 shadow-2xl">
            <div class="flex items-start justify-between border-b border-[#F3ECE1] pb-3.5">
                <div>
                    <h3 id="modal-product-name" class="font-bold text-base sm:text-lg font-serif text-[#22261E]">Custom Menu</h3>
                    <p id="modal-product-price" class="text-xs font-semibold text-[#475638] mt-0.5">Rp 0</p>
                </div>
                <button type="button" onclick="closeCustomizer()" class="w-8 h-8 rounded-full bg-[#FAF7F2] text-[#889180] hover:text-[#22261E] flex items-center justify-center font-bold">
                    &times;
                </button>
            </div>

            {{-- Beverage Options --}}
            <div id="modal-beverage-options" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-[#575E50] mb-2 uppercase tracking-wider">Level Es</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" data-modal-ice="Normal Ice" onclick="setModalIce('Normal Ice')" class="modal-ice-btn active px-3 py-2 rounded-xl text-xs font-semibold border border-[#475638] bg-[#475638] text-white transition">Normal</button>
                        <button type="button" data-modal-ice="Less Ice" onclick="setModalIce('Less Ice')" class="modal-ice-btn px-3 py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] transition">Less Ice</button>
                        <button type="button" data-modal-ice="No Ice" onclick="setModalIce('No Ice')" class="modal-ice-btn px-3 py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] transition">No Ice</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#575E50] mb-2 uppercase tracking-wider">Level Gula</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" data-modal-sugar="Normal Sugar" onclick="setModalSugar('Normal Sugar')" class="modal-sugar-btn active px-3 py-2 rounded-xl text-xs font-semibold border border-[#475638] bg-[#475638] text-white transition">Normal</button>
                        <button type="button" data-modal-sugar="Less Sugar" onclick="setModalSugar('Less Sugar')" class="modal-sugar-btn px-3 py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] transition">Less Sugar</button>
                        <button type="button" data-modal-sugar="No Sugar" onclick="setModalSugar('No Sugar')" class="modal-sugar-btn px-3 py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] transition">No Sugar</button>
                    </div>
                </div>
            </div>

            {{-- Notes Input --}}
            <div>
                <label for="modal-notes-input" class="block text-xs font-bold text-[#575E50] mb-1.5 uppercase tracking-wider">Catatan Khusus</label>
                <input type="text" id="modal-notes-input" placeholder="Contoh: jangan terlalu manis, pisah sedotan..." class="w-full bg-[#FAF7F2] border border-[#DDD4C5] rounded-xl px-3.5 py-2.5 text-xs text-[#22261E] focus:outline-none focus:border-[#475638]">
            </div>

            {{-- Quantity & Submit Button --}}
            <div class="pt-2 flex items-center gap-3">
                <div class="flex items-center border border-[#EBE4D8] rounded-xl bg-[#FAF7F2] p-1">
                    <button type="button" onclick="adjustModalQty(-1)" class="w-8 h-8 rounded-lg bg-white flex items-center justify-center font-bold text-[#575E50] active:scale-95 shadow-2xs">-</button>
                    <span id="modal-qty-display" class="w-8 text-center text-xs font-bold text-[#22261E]">1</span>
                    <button type="button" onclick="adjustModalQty(1)" class="w-8 h-8 rounded-lg bg-white flex items-center justify-center font-bold text-[#575E50] active:scale-95 shadow-2xs">+</button>
                </div>
                <button type="button" id="modal-submit-btn" onclick="submitModalToCart()" class="flex-1 rounded-xl bg-[#475638] hover:bg-[#36422A] text-white font-bold py-3 text-xs shadow-md transition flex items-center justify-between px-4 active:scale-98">
                    <span>Tambah Pesanan</span>
                    <span id="modal-total-btn-price">Rp 0</span>
                </button>
            </div>
        </div>
    </div>

    {{-- JavaScript --}}
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
            document.getElementById('customizer-modal').classList.remove('hidden');
        }

        function closeCustomizer() {
            document.getElementById('customizer-modal').classList.add('hidden');
        }

        function setModalIce(level) {
            currentModalProduct.ice = level;
            document.querySelectorAll('.modal-ice-btn').forEach(btn => {
                if (btn.getAttribute('data-modal-ice') === level) {
                    btn.className = "modal-ice-btn active px-3 py-2 rounded-xl text-xs font-semibold border border-[#475638] bg-[#475638] text-white transition";
                } else {
                    btn.className = "modal-ice-btn px-3 py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] transition";
                }
            });
        }

        function setModalSugar(level) {
            currentModalProduct.sugar = level;
            document.querySelectorAll('.modal-sugar-btn').forEach(btn => {
                if (btn.getAttribute('data-modal-sugar') === level) {
                    btn.className = "modal-sugar-btn active px-3 py-2 rounded-xl text-xs font-semibold border border-[#475638] bg-[#475638] text-white transition";
                } else {
                    btn.className = "modal-sugar-btn px-3 py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] transition";
                }
            });
        }

        function adjustModalQty(delta) {
            currentModalProduct.qty = Math.max(1, currentModalProduct.qty + delta);
            document.getElementById('modal-qty-display').textContent = currentModalProduct.qty;
            recalculateModalTotal();
        }

        function recalculateModalTotal() {
            const grandTotal = currentModalProduct.basePrice * currentModalProduct.qty;
            document.getElementById('modal-total-btn-price').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
        }

        async function submitModalToCart() {
            const submitBtn = document.getElementById('modal-submit-btn');
            submitBtn.disabled = true;

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
                showToast(`${currentModalProduct.name} ditambahkan ke keranjang bersama!`);

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
            }
        }

        function updateCartBar(count, totalFormatted) {
            document.querySelectorAll('.cart-count-badge').forEach(b => b.textContent = count);
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
