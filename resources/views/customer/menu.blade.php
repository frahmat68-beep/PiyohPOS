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
                <p class="text-xs text-[#889180]">Pesan mandiri &amp; sajian langsung disiapkan</p>
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
                                    $totalQty     = $cartCountByProduct[$product->id] ?? 0;
                                    $isAvailable  = $product->base_price !== null && (float) $product->base_price > 0;
                                    $image        = $product->image_url;
                                    $formattedPrice = $isAvailable ? 'Rp ' . number_format($product->base_price, 0, ',', '.') : 'Tanya Barista';
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
                                            
                                            {{-- Total qty badge --}}
                                            <span class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-[#C4823F] text-white text-[10px] font-bold items-center justify-center shadow-sm {{ $totalQty > 0 ? 'flex' : 'hidden' }}" id="qty-badge-{{ $product->id }}">
                                                {{ $totalQty }}
                                            </span>
                                        </div>

                                        {{-- Name & Price --}}
                                        <h3 class="font-bold text-[#22261E] text-xs sm:text-sm font-serif line-clamp-1 leading-snug" title="{{ $product->name }}">{{ $product->name }}</h3>
                                        <p class="text-[11px] sm:text-xs text-[#575E50] line-clamp-2 mt-1 leading-tight min-h-[28px] font-light">{{ $product->description ?: 'Racikan istimewa barista Piyoh Kopi.' }}</p>
                                        
                                        <div class="mt-2 flex items-baseline justify-between">
                                            @if($isAvailable)
                                                <span class="text-xs sm:text-sm font-bold text-[#475638]">
                                                    {{ $formattedPrice }}
                                                </span>
                                            @else
                                                <span class="text-[11px] font-bold text-[#C4823F]">
                                                    Tanya Barista
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Action: Tambah Button --}}
                                    <div class="mt-3 pt-2.5 border-t border-[#F3ECE1]" id="action-wrapper-{{ $product->id }}">
                                        @if(!$isAvailable)
                                            <span class="w-full block text-center rounded-xl bg-[#FAF7F2] border border-[#DDD4C5] py-2 text-[11px] font-bold text-[#C4823F]">
                                                Tanya Barista
                                            </span>
                                        @else
                                            <button type="button" onclick="openCustomizer({{ $product->id }}, '{{ addslashes($product->name) }}', {{ (float) $product->base_price }}, '{{ addslashes($category->name) }}', '{{ addslashes($product->description ?? '') }}', {{ $isBeverage ? 'true' : 'false' }})" class="touch-target-44 w-full inline-flex items-center justify-center gap-1.5 bg-[#475638] hover:bg-[#36422A] text-white text-xs font-bold py-2 px-3 rounded-xl shadow-2xs transition active:scale-95">
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

    {{-- Modern Bottom-Sheet / Modal Customizer --}}
    <div id="customization-modal" class="fixed inset-0 z-50 hidden transition-opacity duration-300 opacity-0 pointer-events-none" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-[#161A14]/60 backdrop-blur-xs transition-opacity" onclick="closeCustomizer()"></div>

        {{-- Modal Container --}}
        <div class="fixed inset-x-0 bottom-0 max-w-lg mx-auto sm:top-auto sm:bottom-6 sm:inset-x-4 sm:rounded-3xl bg-white border border-[#EBE4D8] rounded-t-3xl shadow-2xl overflow-hidden transform transition-all duration-300 translate-y-full flex flex-col max-h-[90vh]">
            
            {{-- Modal Drag Handle / Header --}}
            <div class="px-5 pt-4 pb-3 border-b border-[#F3ECE1] flex items-start justify-between bg-[#FAF7F2]/50">
                <div>
                    <span id="modal-category-badge" class="text-[10px] font-bold uppercase tracking-wider text-[#C4823F] block">Kategori</span>
                    <h3 id="modal-product-name" class="text-base sm:text-lg font-bold font-serif text-[#22261E]">Nama Produk</h3>
                    <p id="modal-product-price" class="text-sm font-bold text-[#475638] mt-0.5">Rp 0</p>
                </div>
                <button type="button" onclick="closeCustomizer()" class="w-8 h-8 rounded-full bg-white border border-[#EBE4D8] flex items-center justify-center text-[#889180] hover:text-[#22261E] transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal Body (Scrollable) --}}
            <div class="p-5 space-y-5 overflow-y-auto flex-1">
                
                {{-- Beverage Options Section --}}
                <div id="modal-beverage-options" class="space-y-4">
                    {{-- Ice Level --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold text-[#22261E] flex items-center gap-1.5">
                                <span>🧊 Level Es</span>
                            </label>
                            <span class="text-[10px] text-[#889180]">Pilih 1</span>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button type="button" onclick="setModalIce('Normal')" data-modal-ice="Normal" class="modal-ice-btn active py-2 rounded-xl text-xs font-semibold border border-[#475638] bg-[#475638] text-white transition">Normal</button>
                            <button type="button" onclick="setModalIce('Less Ice')" data-modal-ice="Less Ice" class="modal-ice-btn py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">Less</button>
                            <button type="button" onclick="setModalIce('No Ice')" data-modal-ice="No Ice" class="modal-ice-btn py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">None</button>
                            <button type="button" onclick="setModalIce('Extra Ice')" data-modal-ice="Extra Ice" class="modal-ice-btn py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">Extra</button>
                        </div>
                    </div>

                    {{-- Sugar Level --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold text-[#22261E] flex items-center gap-1.5">
                                <span>🍯 Level Gula (Sweetness)</span>
                            </label>
                            <span class="text-[10px] text-[#889180]">Pilih 1</span>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button type="button" onclick="setModalSugar('Normal')" data-modal-sugar="Normal" class="modal-sugar-btn active py-2 rounded-xl text-xs font-semibold border border-[#475638] bg-[#475638] text-white transition">Normal</button>
                            <button type="button" onclick="setModalSugar('Less Sugar')" data-modal-sugar="Less Sugar" class="modal-sugar-btn py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">Less</button>
                            <button type="button" onclick="setModalSugar('No Sugar')" data-modal-sugar="No Sugar" class="modal-sugar-btn py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">None</button>
                            <button type="button" onclick="setModalSugar('Extra Sugar')" data-modal-sugar="Extra Sugar" class="modal-sugar-btn py-2 rounded-xl text-xs font-semibold border border-[#EBE4D8] bg-white text-[#575E50] hover:bg-[#FAF7F2] transition">Extra</button>
                        </div>
                    </div>
                </div>

                {{-- Additional / Ekstra Toppings (from database) --}}
                @if(isset($additionalProducts) && $additionalProducts->count() > 0)
                    <div class="pt-2 border-t border-[#F3ECE1]">
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="text-xs font-bold text-[#22261E] flex items-center gap-1.5">
                                <span>✨ Pilihan Tambahan (Additional)</span>
                            </label>
                            <span class="text-[10px] text-[#889180]">Opsional</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($additionalProducts as $addon)
                                <label class="flex items-center justify-between p-3 rounded-xl border border-[#EBE4D8] bg-white hover:bg-[#FAF7F2] cursor-pointer transition select-none">
                                    <div class="flex items-center gap-2.5">
                                        <input type="checkbox" name="addons[]" value="{{ $addon->id }}" data-name="{{ $addon->name }}" data-price="{{ (float) $addon->base_price }}" onchange="recalculateModalTotal()" class="modal-addon-checkbox w-4 h-4 rounded text-[#475638] focus:ring-[#475638]">
                                        <span class="text-xs font-semibold text-[#22261E]">{{ $addon->name }}</span>
                                    </div>
                                    <span class="text-xs font-bold text-[#475638]">+ Rp {{ number_format($addon->base_price, 0, ',', '.') }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Special Notes Input --}}
                <div class="pt-2 border-t border-[#F3ECE1]">
                    <label for="modal-notes-input" class="block text-xs font-bold text-[#22261E] mb-1.5">
                        📝 Catatan Khusus untuk Barista
                    </label>
                    <input type="text" id="modal-notes-input" placeholder="Contoh: Pisahkan es, jangan terlalu manis, dll." class="w-full bg-[#FAF7F2] border border-[#DDD4C5] rounded-xl px-3.5 py-2.5 text-xs text-[#22261E] focus:outline-none focus:border-[#475638]">
                </div>

            </div>

            {{-- Modal Footer with Stepper & CTA --}}
            <div class="p-4 bg-white border-t border-[#F3ECE1] flex items-center gap-3">
                {{-- Quantity Stepper --}}
                <div class="flex items-center border border-[#DDD4C5] rounded-2xl p-1 bg-[#FAF7F2]">
                    <button type="button" onclick="adjustModalQty(-1)" class="w-9 h-9 rounded-xl bg-white border border-[#EBE4D8] flex items-center justify-center text-sm font-bold text-[#22261E] hover:bg-[#FAF7F2] active:scale-95 transition">&minus;</button>
                    <span id="modal-qty-display" class="font-bold text-sm px-3 text-[#22261E]">1</span>
                    <button type="button" onclick="adjustModalQty(1)" class="w-9 h-9 rounded-xl bg-white border border-[#EBE4D8] flex items-center justify-center text-sm font-bold text-[#22261E] hover:bg-[#FAF7F2] active:scale-95 transition">&plus;</button>
                </div>

                {{-- Add to Cart Submit Button --}}
                <button type="button" id="modal-submit-btn" onclick="submitModalToCart()" class="flex-1 bg-[#475638] hover:bg-[#36422A] text-white font-bold py-3 px-4 rounded-2xl shadow-md transition active:scale-98 flex items-center justify-between text-xs sm:text-sm">
                    <span>Tambahkan Pesanan</span>
                    <span id="modal-total-btn-price" class="bg-white/20 px-2 py-0.5 rounded-lg">Rp 0</span>
                </button>
            </div>

        </div>
    </div>

    {{-- Toast Notification --}}
    <div id="toast-notify" class="fixed top-5 inset-x-0 z-50 max-w-sm mx-auto px-4 pointer-events-none hidden transition-all duration-300 transform -translate-y-4 opacity-0">
        <div class="bg-[#161A14] text-white border border-white/20 px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-xs font-semibold">
            <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm">✓</span>
            <span id="toast-message">Item berhasil ditambahkan ke keranjang!</span>
        </div>
    </div>

    {{-- Interactive Javascript Controller --}}
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Modal Active State
        let currentModalProduct = {
            id: null,
            name: '',
            basePrice: 0,
            isBeverage: true,
            ice: 'Normal',
            sugar: 'Normal',
            qty: 1
        };

        // ─── Modal Open / Close ────────────────────────────────────────────────

        function openCustomizer(id, name, basePrice, categoryName, description, isBeverage) {
            currentModalProduct = {
                id: id,
                name: name,
                basePrice: basePrice,
                isBeverage: isBeverage,
                ice: 'Normal',
                sugar: 'Normal',
                qty: 1
            };

            // Set UI elements
            document.getElementById('modal-product-name').textContent = name;
            document.getElementById('modal-category-badge').textContent = categoryName;
            document.getElementById('modal-product-price').textContent = 'Rp ' + Number(basePrice).toLocaleString('id-ID');
            document.getElementById('modal-notes-input').value = '';
            document.getElementById('modal-qty-display').textContent = '1';

            // Show/Hide beverage sections
            const bevSection = document.getElementById('modal-beverage-options');
            if (bevSection) {
                bevSection.style.display = isBeverage ? 'block' : 'none';
            }

            // Reset ice & sugar buttons to Normal
            setModalIce('Normal');
            setModalSugar('Normal');

            // Reset checkboxes
            document.querySelectorAll('.modal-addon-checkbox').forEach(cb => cb.checked = false);

            // Recalculate total price
            recalculateModalTotal();

            // Show modal
            const modal = document.getElementById('customization-modal');
            const container = modal.querySelector('.max-w-lg');
            modal.classList.remove('hidden', 'pointer-events-none');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                container.classList.remove('translate-y-full');
                container.classList.add('translate-y-0');
            }, 10);
        }

        function closeCustomizer() {
            const modal = document.getElementById('customization-modal');
            const container = modal.querySelector('.max-w-lg');
            if (modal) {
                container.classList.remove('translate-y-0');
                container.classList.add('translate-y-full');
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden', 'pointer-events-none');
                }, 300);
            }
        }

        // ─── Ice & Sugar Options ───────────────────────────────────────────────

        function setModalIce(level) {
            currentModalProduct.ice = level;
            document.querySelectorAll('.modal-ice-btn').forEach(btn => {
                if (btn.getAttribute('data-modal-ice') === level) {
                    btn.classList.add('active', 'bg-[#475638]', 'text-white', 'border-[#475638]');
                    btn.classList.remove('bg-white', 'text-[#575E50]', 'border-[#EBE4D8]');
                } else {
                    btn.classList.remove('active', 'bg-[#475638]', 'text-white', 'border-[#475638]');
                    btn.classList.add('bg-white', 'text-[#575E50]', 'border-[#EBE4D8]');
                }
            });
        }

        function setModalSugar(level) {
            currentModalProduct.sugar = level;
            document.querySelectorAll('.modal-sugar-btn').forEach(btn => {
                if (btn.getAttribute('data-modal-sugar') === level) {
                    btn.classList.add('active', 'bg-[#475638]', 'text-white', 'border-[#475638]');
                    btn.classList.remove('bg-white', 'text-[#575E50]', 'border-[#EBE4D8]');
                } else {
                    btn.classList.remove('active', 'bg-[#475638]', 'text-white', 'border-[#475638]');
                    btn.classList.add('bg-white', 'text-[#575E50]', 'border-[#EBE4D8]');
                }
            });
        }

        function adjustModalQty(delta) {
            currentModalProduct.qty = Math.max(1, currentModalProduct.qty + delta);
            document.getElementById('modal-qty-display').textContent = currentModalProduct.qty;
            recalculateModalTotal();
        }

        function recalculateModalTotal() {
            let unitTotal = currentModalProduct.basePrice;

            // Add selected additions
            document.querySelectorAll('.modal-addon-checkbox:checked').forEach(cb => {
                unitTotal += parseFloat(cb.getAttribute('data-price')) || 0;
            });

            const grandTotal = unitTotal * currentModalProduct.qty;
            document.getElementById('modal-total-btn-price').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
        }

        // ─── Submit Customizer to Cart ─────────────────────────────────────────

        async function submitModalToCart() {
            const submitBtn = document.getElementById('modal-submit-btn');
            submitBtn.disabled = true;

            try {
                // Build notes string
                let noteParts = [];
                if (currentModalProduct.isBeverage) {
                    noteParts.push(`Level Es: ${currentModalProduct.ice}`);
                    noteParts.push(`Level Gula: ${currentModalProduct.sugar}`);
                }

                // Addons list
                const checkedAddons = [];
                document.querySelectorAll('.modal-addon-checkbox:checked').forEach(cb => {
                    checkedAddons.push(cb.getAttribute('data-name'));
                });
                if (checkedAddons.length > 0) {
                    noteParts.push(`Ekstra: ${checkedAddons.join(', ')}`);
                }

                const customNote = document.getElementById('modal-notes-input').value.trim();
                if (customNote) {
                    noteParts.push(customNote);
                }

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
                    submitBtn.disabled = false;
                    return;
                }

                closeCustomizer();
                updateCartBar(data.cart_count, data.cart_total_formatted);
                showToast(`${currentModalProduct.name} ditambahkan ke keranjang!`);

                // Update card badge
                const badge = document.getElementById('qty-badge-' + currentModalProduct.id);
                if (badge) {
                    const currentBadgeCount = parseInt(badge.textContent) || 0;
                    const newCount = currentBadgeCount + currentModalProduct.qty;
                    badge.textContent = newCount;
                    badge.classList.remove('hidden');
                    badge.classList.add('flex');
                }

            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat memproses pesanan.');
            } finally {
                submitBtn.disabled = false;
            }
        }

        // ─── UI Helpers ────────────────────────────────────────────────────────

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
    </script>
</body>
</html>
