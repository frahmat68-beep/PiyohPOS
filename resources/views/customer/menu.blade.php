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
                </div>
                <h1 class="text-lg sm:text-xl font-bold tracking-tight font-serif text-[#22261E]">{{ $tableSession->table->outlet->name }}</h1>
                <p class="text-xs text-[#889180]">Pesan mandiri & sajian langsung disiapkan</p>
            </div>
            <a href="/cart" id="top-cart-btn" class="relative inline-flex items-center gap-1.5 rounded-full bg-[#475638] hover:bg-[#36422A] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span id="top-cart-text">Cart (<span class="cart-count-badge">{{ $cartCount }}</span>)</span>
            </a>
        </header>

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
                                    $itemInCart = $cartItemsByProduct[$product->id] ?? null;
                                    $qtyInCart = $itemInCart ? $itemInCart['quantity'] : 0;
                                    $isAvailable = $product->base_price !== null && (float) $product->base_price > 0;
                                    $image = $product->image_url;
                                @endphp
                                <div class="product-card bg-white border border-[#EBE4D8] rounded-2xl p-3 sm:p-3.5 shadow-2xs hover:shadow-md transition-all duration-200 flex flex-col justify-between" id="product-card-{{ $product->id }}">
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
                                        </div>

                                        {{-- Name & Price --}}
                                        <h3 class="font-bold text-[#22261E] text-xs sm:text-sm font-serif line-clamp-1 leading-snug" title="{{ $product->name }}">{{ $product->name }}</h3>
                                        <p class="text-[11px] sm:text-xs text-[#575E50] line-clamp-2 mt-1 leading-tight min-h-[28px] font-light">{{ $product->description ?: 'Racikan istimewa barista Piyoh Kopi.' }}</p>
                                        
                                        <div class="mt-2 flex items-baseline justify-between">
                                            @if($isAvailable)
                                                <span class="text-xs sm:text-sm font-bold text-[#475638]">
                                                    Rp {{ number_format($product->base_price, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-[11px] font-bold text-[#C4823F]">
                                                    Tanya Barista
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Customization Preset Chips for Beverages --}}
                                        @if($isBeverage && $isAvailable)
                                            <div class="mt-2.5 pt-2 border-t border-[#F3ECE1] text-[10px]">
                                                <button type="button" onclick="toggleCustomizer({{ $product->id }})" class="w-full flex items-center justify-between text-[#575E50] hover:text-[#475638] font-medium py-1">
                                                    <span>Opsi Es & Gula</span>
                                                    <svg id="chevron-{{ $product->id }}" class="w-3.5 h-3.5 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                
                                                <div id="customizer-{{ $product->id }}" class="hidden space-y-2 pt-2">
                                                    {{-- Ice Level Chips --}}
                                                    <div>
                                                        <span class="text-[9px] font-bold uppercase tracking-wider text-[#889180] block mb-1">Level Es</span>
                                                        <div class="grid grid-cols-3 gap-1" data-custom-group="ice-{{ $product->id }}">
                                                            <button type="button" onclick="selectChip(this, 'ice-{{ $product->id }}', 'Normal')" class="chip-btn active px-1 py-1 rounded text-[10px] font-semibold text-center border border-[#475638] bg-[#475638] text-white transition">Normal</button>
                                                            <button type="button" onclick="selectChip(this, 'ice-{{ $product->id }}', 'Less Ice')" class="chip-btn px-1 py-1 rounded text-[10px] font-semibold text-center border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">Less</button>
                                                            <button type="button" onclick="selectChip(this, 'ice-{{ $product->id }}', 'No Ice')" class="chip-btn px-1 py-1 rounded text-[10px] font-semibold text-center border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">None</button>
                                                        </div>
                                                    </div>

                                                    {{-- Sugar Level Chips --}}
                                                    <div>
                                                        <span class="text-[9px] font-bold uppercase tracking-wider text-[#889180] block mb-1">Level Gula</span>
                                                        <div class="grid grid-cols-3 gap-1" data-custom-group="sugar-{{ $product->id }}">
                                                            <button type="button" onclick="selectChip(this, 'sugar-{{ $product->id }}', 'Normal')" class="chip-btn active px-1 py-1 rounded text-[10px] font-semibold text-center border border-[#475638] bg-[#475638] text-white transition">Normal</button>
                                                            <button type="button" onclick="selectChip(this, 'sugar-{{ $product->id }}', 'Less Sugar')" class="chip-btn px-1 py-1 rounded text-[10px] font-semibold text-center border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">Less</button>
                                                            <button type="button" onclick="selectChip(this, 'sugar-{{ $product->id }}', 'No Sugar')" class="chip-btn px-1 py-1 rounded text-[10px] font-semibold text-center border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">None</button>
                                                        </div>
                                                    </div>

                                                    {{-- Extra notes --}}
                                                    <input type="text" id="note-{{ $product->id }}" placeholder="Catatan opsional..." class="w-full bg-[#FAF7F2] border border-[#DDD4C5] rounded-lg px-2 py-1 text-[10px] text-[#22261E] focus:outline-none focus:border-[#475638]">
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Action: Stepper vs Tambah Button --}}
                                    <div class="mt-3 pt-2.5 border-t border-[#F3ECE1]" id="action-wrapper-{{ $product->id }}">
                                        @if(!$isAvailable)
                                            <span class="w-full block text-center rounded-xl bg-[#FAF7F2] border border-[#DDD4C5] py-2 text-[11px] font-bold text-[#C4823F]">
                                                Tanya Barista
                                            </span>
                                        @elseif($qtyInCart > 0)
                                            {{-- Active Stepper --}}
                                            <div class="flex items-center justify-between bg-[#475638] text-white rounded-xl p-1 shadow-sm">
                                                <button type="button" onclick="stepQty({{ $product->id }}, -1)" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center text-white font-bold transition active:scale-95">
                                                    &minus;
                                                </button>
                                                <span class="font-bold text-sm px-2" id="qty-label-{{ $product->id }}">{{ $qtyInCart }}</span>
                                                <button type="button" onclick="stepQty({{ $product->id }}, 1)" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center text-white font-bold transition active:scale-95">
                                                    &plus;
                                                </button>
                                            </div>
                                        @else
                                            {{-- Initial Tambah Button --}}
                                            <button type="button" onclick="handleInitialAdd({{ $product->id }}, {{ $isBeverage ? 'true' : 'false' }})" class="touch-target-44 w-full inline-flex items-center justify-center gap-1 bg-[#475638] hover:bg-[#36422A] text-white text-xs font-bold py-2 px-3 rounded-xl shadow-2xs transition active:scale-95">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                <span>Tambah</span>
                                            </button>
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

    {{-- Cross-Sell Suggestion Drawer / Bar --}}
    @if(isset($additionalProducts) && $additionalProducts->count() > 0)
        <div id="cross-sell-drawer" class="hidden fixed bottom-20 inset-x-0 z-40 px-3.5 sm:px-5 max-w-xl mx-auto transition-all duration-300 transform translate-y-4 opacity-0">
            <div class="bg-white border border-[#EBE4D8] rounded-2xl p-3.5 shadow-xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold font-serif text-[#22261E] flex items-center gap-1.5">
                        <span class="text-[#C4823F]">✨</span> Mau tambah ekstra?
                    </span>
                    <button type="button" onclick="dismissCrossSell()" class="text-xs text-[#889180] hover:text-[#22261E] px-1.5 py-0.5">&times; Lewati</button>
                </div>
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1">
                    @foreach($additionalProducts as $extra)
                        <button type="button" onclick="addCrossSell({{ $extra->id }})" class="shrink-0 whitespace-nowrap inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#FAF7F2] border border-[#DDD4C5] hover:border-[#475638] text-[11px] font-semibold text-[#22261E] transition active:scale-95 shadow-2xs">
                            <span>+ {{ $extra->name }}</span>
                            <span class="text-[#475638] font-bold">Rp {{ number_format($extra->base_price, 0, ',', '.') }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Sticky Floating Mobile Cart Bar --}}
    <div id="floating-cart-bar" class="{{ $cartCount > 0 ? '' : 'hidden' }} fixed bottom-4 inset-x-0 z-40 px-3.5 sm:px-5 max-w-xl mx-auto pointer-events-none transition-all duration-300">
        <a href="/cart" class="pointer-events-auto flex items-center justify-between bg-[#161A14] text-white border border-white/15 rounded-full px-5 py-3.5 shadow-2xl hover:bg-[#222920] transition transform active:scale-98">
            <div class="flex items-center gap-3">
                <span class="cart-count-badge w-7 h-7 rounded-full bg-[#C4823F] text-white text-xs font-bold flex items-center justify-center shadow-xs">
                    {{ $cartCount }}
                </span>
                <div class="text-left">
                    <p class="text-xs font-medium text-[#B2BBAE]"><span id="cart-item-count-text">{{ $cartCount }} item</span> di keranjang</p>
                    <p class="text-sm font-bold font-serif text-[#FAF7F2]" id="floating-cart-total">Rp {{ number_format($total ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#475638] hover:bg-[#36422A] text-white px-4 py-2 text-xs font-bold transition shadow-sm">
                <span>Buka Keranjang</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </span>
        </a>
    </div>

    {{-- Interactive Javascript Controller --}}
    <script>
        const customValues = {};
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function toggleCustomizer(productId) {
            const el = document.getElementById('customizer-' + productId);
            const chv = document.getElementById('chevron-' + productId);
            if (el) {
                el.classList.toggle('hidden');
                if (chv) chv.classList.toggle('rotate-180');
            }
        }

        function selectChip(btn, groupKey, value) {
            const container = document.querySelector(`[data-custom-group="${groupKey}"]`);
            if (!container) return;
            container.querySelectorAll('.chip-btn').forEach(b => {
                b.classList.remove('active', 'bg-[#475638]', 'text-white', 'border-[#475638]');
                b.classList.add('bg-white', 'text-[#575E50]', 'border-[#EBE4D8]');
            });
            btn.classList.add('active', 'bg-[#475638]', 'text-white', 'border-[#475638]');
            btn.classList.remove('bg-white', 'text-[#575E50]', 'border-[#EBE4D8]');
            customValues[groupKey] = value;
        }

        function getCustomNotes(productId, isBeverage) {
            if (!isBeverage) return null;
            const ice = customValues['ice-' + productId] || 'Normal';
            const sugar = customValues['sugar-' + productId] || 'Normal';
            const extraInput = document.getElementById('note-' + productId);
            const extra = extraInput ? extraInput.value.trim() : '';
            
            let notes = `Level Es: ${ice}, Level Gula: ${sugar}`;
            if (extra) {
                notes += ` — ${extra}`;
            }
            return notes;
        }

        async function handleInitialAdd(productId, isBeverage) {
            const notes = getCustomNotes(productId, isBeverage);
            await addToCart(productId, 1, notes, isBeverage);
        }

        async function addToCart(productId, qty, notes, isBeverage = false) {
            try {
                const res = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: qty,
                        notes: notes
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    alert(data.error || 'Gagal menambahkan ke keranjang.');
                    return;
                }

                updateCardAction(productId, data.quantity);
                updateCartBar(data.cart_count, data.cart_total_formatted);

                // Trigger cross-sell suggestion if it was a drink
                if (isBeverage) {
                    showCrossSell();
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function stepQty(productId, delta) {
            const currentLabel = document.getElementById('qty-label-' + productId);
            const currentQty = currentLabel ? parseInt(currentLabel.textContent) || 0 : 0;
            const newQty = Math.max(0, currentQty + delta);

            try {
                const res = await fetch('/cart/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: newQty
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    alert(data.error || 'Gagal mengupdate keranjang.');
                    return;
                }

                updateCardAction(productId, data.quantity);
                updateCartBar(data.cart_count, data.cart_total_formatted);
            } catch (err) {
                console.error(err);
            }
        }

        async function addCrossSell(productId) {
            await addToCart(productId, 1, null, false);
            dismissCrossSell();
        }

        function showCrossSell() {
            const drawer = document.getElementById('cross-sell-drawer');
            if (drawer) {
                drawer.classList.remove('hidden');
                setTimeout(() => {
                    drawer.classList.remove('translate-y-4', 'opacity-0');
                    drawer.classList.add('translate-y-0', 'opacity-100');
                }, 50);
            }
        }

        function dismissCrossSell() {
            const drawer = document.getElementById('cross-sell-drawer');
            if (drawer) {
                drawer.classList.add('translate-y-4', 'opacity-0');
                drawer.classList.remove('translate-y-0', 'opacity-100');
                setTimeout(() => drawer.classList.add('hidden'), 300);
            }
        }

        function updateCardAction(productId, qty) {
            const wrapper = document.getElementById('action-wrapper-' + productId);
            if (!wrapper) return;

            if (qty > 0) {
                wrapper.innerHTML = `
                    <div class="flex items-center justify-between bg-[#475638] text-white rounded-xl p-1 shadow-sm">
                        <button type="button" onclick="stepQty(${productId}, -1)" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center text-white font-bold transition active:scale-95">
                            &minus;
                        </button>
                        <span class="font-bold text-sm px-2" id="qty-label-${productId}">${qty}</span>
                        <button type="button" onclick="stepQty(${productId}, 1)" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center text-white font-bold transition active:scale-95">
                            &plus;
                        </button>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `
                    <button type="button" onclick="handleInitialAdd(${productId}, false)" class="touch-target-44 w-full inline-flex items-center justify-center gap-1 bg-[#475638] hover:bg-[#36422A] text-white text-xs font-bold py-2 px-3 rounded-xl shadow-2xs transition active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah</span>
                    </button>
                `;
            }
        }

        function updateCartBar(count, totalFormatted) {
            const badges = document.querySelectorAll('.cart-count-badge');
            badges.forEach(b => b.textContent = count);

            const countText = document.getElementById('cart-item-count-text');
            if (countText) countText.textContent = `${count} item`;

            const totalText = document.getElementById('floating-cart-total');
            if (totalText) totalText.textContent = totalFormatted;

            const bar = document.getElementById('floating-cart-bar');
            if (bar) {
                if (count > 0) {
                    bar.classList.remove('hidden');
                } else {
                    bar.classList.add('hidden');
                }
            }
        }
    </script>
</body>
</html>
