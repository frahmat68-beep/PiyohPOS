<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Piyoh Kopi — Keranjang Meja {{ $tableSession->table->number }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    {{-- Midtrans Snap JS SDK --}}
    <script type="text/javascript"
            src="{{ config('midtrans.snap_url') }}"
            data-client-key="{{ config('midtrans.client_key') }}"></script>
</head>
<body class="bg-[#FAF7F2] text-[#22261E] pb-16 antialiased selection:bg-[#475638] selection:text-white" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div class="max-w-lg mx-auto px-4 pt-6">
        
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="/qr/menu" class="w-10 h-10 rounded-full bg-white border border-[#EBE4D8] flex items-center justify-center text-[#575E50] hover:text-[#22261E] transition shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold font-serif text-[#22261E]">Keranjang Pesanan</h1>
                    <p class="text-xs text-[#889180]">Meja {{ $tableSession->table->number }} • Shared Cart</p>
                </div>
            </div>
            <span class="rounded-full bg-[#EBF0E6] text-[#475638] text-xs font-bold px-3 py-1" id="cart-header-count">
                {{ count($items) }} Item
            </span>
        </div>

        {{-- Cart Lock Alert --}}
        <div id="cart-lock-warning" class="{{ $isLocked ? 'flex' : 'hidden' }} mb-5 p-4 bg-[#FFFBEB] border border-[#FDE68A] text-[#92400E] rounded-2xl text-xs font-semibold items-center gap-2.5 shadow-sm">
            <svg class="w-4 h-4 shrink-0 text-[#D97706] animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Perangkat lain di Meja ini sedang menyelesaikan pembayaran. Pembayaran baru dapat dilakukan setelah proses selesai.</span>
        </div>

        {{-- Error Alerts --}}
        @if(session('error'))
            <div class="mb-5 p-4 bg-[#FEF2F2] border border-[#FECACA] text-[#B91C1C] rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div id="empty-cart-view" class="{{ count($items) === 0 ? 'block' : 'hidden' }}">
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-10 text-center shadow-sm space-y-4">
                <div class="w-16 h-16 bg-[#F3ECE1] text-[#C4823F] rounded-full flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <h2 class="text-lg font-bold font-serif text-[#22261E]">Keranjang Masih Kosong</h2>
                <p class="text-xs text-[#575E50]">Pilih menu kopi atau pastry favoritmu terlebih dahulu sebelum melakukan pemesanan.</p>
                <div class="pt-2">
                    <a href="/qr/menu" class="inline-flex items-center gap-2 rounded-full bg-[#475638] hover:bg-[#36422A] px-6 py-3 text-xs font-bold text-white shadow-sm transition">
                        <span>Lihat Buku Menu</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div id="active-cart-view" class="{{ count($items) > 0 ? 'block' : 'hidden' }}">
            {{-- Cart Items List --}}
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm divide-y divide-[#F3ECE1] mb-6" id="cart-items-container">
                @foreach($items as $item)
                    @php $isMine = ($item['device_id'] ?? null) === $deviceId; @endphp
                    <div class="cart-item py-4 first:pt-0 last:pb-0 flex items-start justify-between gap-4" id="item-row-{{ md5($item['cart_key']) }}">
                        <div class="item-details space-y-1 flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-[#22261E] text-base font-serif truncate">{{ $item['product']->name }}</h3>
                                @if($isMine)
                                    <span class="text-[9px] bg-[#EBF0E6] text-[#475638] font-bold px-1.5 py-0.5 rounded">HP Kamu</span>
                                @else
                                    <span class="text-[9px] bg-stone-100 text-[#889180] font-bold px-1.5 py-0.5 rounded">Teman Meja</span>
                                @endif
                            </div>
                            <p class="text-xs text-[#889180]">
                                {{ $item['quantity'] }} &times; Rp {{ number_format($item['price'], 0, ',', '.') }}
                            </p>
                            @if(!empty($item['notes']))
                                <p class="text-[11px] text-[#C4823F] italic bg-[#FBF2E8] px-2 py-0.5 rounded-md inline-block">
                                    {{ $item['notes'] }}
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <span class="item-price text-sm font-bold text-[#475638] whitespace-nowrap">
                                Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                            </span>
                            <button type="button" onclick="removeItem('{{ addslashes($item['cart_key']) }}')" class="text-[11px] font-semibold text-[#889180] hover:text-red-500 transition px-1 py-0.5 rounded">
                                Hapus
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Price Breakdown --}}
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm space-y-2.5 text-xs text-[#575E50] mb-6">
                <div class="flex justify-between">
                    <span>Subtotal Menu</span>
                    <span class="font-semibold text-[#22261E]" id="summary-subtotal">Rp {{ number_format($total, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>PB1 / Pajak Restoran (10%)</span>
                    <span class="font-semibold text-[#22261E]" id="summary-tax">Rp {{ number_format($total * 0.1, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Biaya Layanan (5%)</span>
                    <span class="font-semibold text-[#22261E]" id="summary-service">Rp {{ number_format($total * 0.05, 0, ',', '.') }}</span>
                </div>
                <div class="total-section pt-3 border-t border-[#F3ECE1] flex justify-between items-baseline">
                    <span class="text-sm font-bold text-[#22261E]">Total Pembayaran</span>
                    <span class="total-price text-lg font-bold text-[#475638]" id="summary-grand-total">
                        Rp {{ number_format($total * 1.15, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Checkout Form with Midtrans Snap & Cash Option --}}
            <div class="bg-white border border-[#EBE4D8] rounded-3xl p-5 shadow-sm">
                <form id="checkout-form" onsubmit="handleCheckout(event)" class="checkout-form space-y-4">
                    @csrf
                    <div class="form-group space-y-1.5">
                        <label for="customer_name" class="block text-xs font-bold uppercase tracking-wider text-[#575E50]">Nama Pemesan *</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control w-full bg-[#FAF7F2] border border-[#DDD4C5] rounded-xl px-4 py-3 text-sm text-[#22261E] focus:outline-none focus:border-[#475638] transition placeholder:text-[#889180]" placeholder="Contoh: Kiki / Meja 01" required>
                    </div>

                    {{-- Payment Method Info (QR Order is exclusively Midtrans) --}}
                    <input type="hidden" name="payment_method" value="midtrans">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#575E50]">Metode Pembayaran</label>
                        <div class="flex items-center justify-between p-3.5 rounded-2xl border border-[#475638]/20 bg-[#FAF7F2]">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-[#EBF0E6] flex items-center justify-center text-[#475638]">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <span class="text-xs font-bold text-[#22261E] block">Online / QRIS (Midtrans)</span>
                                    <span class="text-[11px] text-[#889180]">QRIS, GoPay, ShopeePay, Transfer VA Bank</span>
                                </div>
                            </div>
                            <span class="text-[10px] bg-[#EBF0E6] text-[#475638] font-bold px-2.5 py-1 rounded-full border border-[#475638]/20">Otomatis</span>
                        </div>
                    </div>

                    <button type="submit" id="btn-pay-now" {{ $isLocked ? 'disabled' : '' }} class="btn-checkout w-full rounded-full bg-[#475638] hover:bg-[#36422A] disabled:bg-stone-300 disabled:cursor-not-allowed text-white font-bold py-4 text-sm shadow-md transition transform active:scale-98 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span id="pay-btn-text">Bayar Sekarang (Midtrans QRIS)</span>
                    </button>
                </form>
            </div>

            <div class="mt-4 text-center">
                <a href="/qr/menu" class="back-link inline-flex items-center gap-1 text-xs font-bold text-[#475638] hover:text-[#36422A] transition">
                    &larr; Tambah Menu Lainnya
                </a>
            </div>
        </div>

    </div>

    {{-- Script --}}
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let isProcessing = false;
        const selectedPaymentMethod = 'midtrans';

        async function removeItem(cartKey) {
            if (!confirm('Hapus item ini dari pesanan meja?')) return;

            try {
                const res = await fetch('/cart/remove', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ cart_key: cartKey })
                });

                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json();
                    alert(data.error || 'Gagal menghapus item.');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan jaringan.');
            }
        }

        async function handleCheckout(e) {
            e.preventDefault();
            if (isProcessing) return;

            const nameInput = document.getElementById('customer_name').value.trim();
            if (!nameInput) {
                alert('Silakan masukkan nama pemesan.');
                return;
            }

            const payBtn = document.getElementById('btn-pay-now');
            const payText = document.getElementById('pay-btn-text');
            payBtn.disabled = true;
            payText.textContent = selectedPaymentMethod === 'midtrans' ? 'Menyiapkan Pembayaran...' : 'Mengirim Pesanan...';
            isProcessing = true;

            try {
                const res = await fetch('/checkout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        customer_name: nameInput,
                        payment_method: selectedPaymentMethod
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    alert(data.error || 'Checkout gagal.');
                    payBtn.disabled = false;
                    payText.textContent = 'Bayar Sekarang (Midtrans QRIS)';
                    isProcessing = false;
                    return;
                }

                const targetTrackingUrl = data.tracking_url || `/orders/${data.order.order_number}/tracking/${data.tracking_token || data.order.tracking_token}`;

                if (selectedPaymentMethod === 'midtrans' && data.snap_token) {
                    // Trigger Midtrans Snap
                    window.snap.pay(data.snap_token, {
                        onSuccess: function(result) {
                            window.location.href = targetTrackingUrl;
                        },
                        onPending: function(result) {
                            window.location.href = targetTrackingUrl;
                        },
                        onError: function(result) {
                            alert('Pembayaran gagal atau dibatalkan.');
                            fetch('/cart/unlock', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
                            payBtn.disabled = false;
                            payText.textContent = 'Coba Bayar Lagi';
                            isProcessing = false;
                        },
                        onClose: function() {
                            fetch('/cart/unlock', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
                            payBtn.disabled = false;
                            payText.textContent = 'Bayar Sekarang (Midtrans QRIS)';
                            isProcessing = false;
                        }
                    });
                } else {
                    window.location.href = targetTrackingUrl;
                }

            } catch (err) {
                console.error(err);
                alert('Gagal memproses pesanan.');
                payBtn.disabled = false;
                payText.textContent = 'Bayar Sekarang (Midtrans QRIS)';
                isProcessing = false;
            }
        }

        // Realtime cart polling on cart page (every 5 seconds)
        async function pollCartSync() {
            if (isProcessing) return;

            try {
                const res = await fetch('/cart/sync', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;

                const data = await res.json();
                const lockWarning = document.getElementById('cart-lock-warning');
                const payBtn = document.getElementById('btn-pay-now');

                if (lockWarning && payBtn) {
                    if (data.is_locked) {
                        lockWarning.classList.remove('hidden');
                        lockWarning.classList.add('flex');
                        payBtn.disabled = true;
                    } else {
                        lockWarning.classList.add('hidden');
                        lockWarning.classList.remove('flex');
                        payBtn.disabled = false;
                    }
                }
            } catch (err) {
                // Silently retry on next interval
            }
        }

        setInterval(pollCartSync, 5000);
    </script>
</body>
</html>
