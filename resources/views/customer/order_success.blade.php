<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Piyoh Kopi — Pesanan Berhasil Dikirim</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#22261E] pb-16 antialiased selection:bg-[#475638] selection:text-white" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div class="max-w-lg mx-auto px-4 pt-10 text-center">
        
        <div class="bg-white border border-[#EBE4D8] rounded-3xl p-8 sm:p-10 shadow-sm space-y-6">
            
            {{-- Success Icon --}}
            <div class="w-16 h-16 bg-[#EBF0E6] text-[#475638] rounded-full flex items-center justify-center mx-auto shadow-sm">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>

            <div>
                <h1 class="text-2xl font-bold font-serif text-[#22261E]">Order Placed Successfully!</h1>
                <p class="mt-2 text-xs sm:text-sm text-[#575E50] leading-relaxed">Pesananmu telah otomatis diteruskan ke Kitchen Display System dan siap diracik barista.</p>
            </div>

            {{-- Warning if some items were removed due to stock out --}}
            @if(!empty($warningMessage))
                <div class="p-4 bg-[#FFFBEB] border border-[#FDE68A] text-[#B45309] rounded-2xl text-xs sm:text-sm text-left flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-[#B45309] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <strong class="font-bold">Perhatian:</strong> {{ $warningMessage }}
                    </div>
                </div>
            @endif

            {{-- Order Number Box --}}
            <div class="p-5 bg-[#FAF7F2] border border-[#EBE4D8] rounded-2xl space-y-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#889180]">Nomor Pesanan</span>
                <div class="order-number text-xl sm:text-2xl font-mono font-bold text-[#475638]">
                    {{ $order->order_number }}
                </div>
            </div>

            {{-- Total Amount --}}
            <div class="pt-2 border-t border-[#F3ECE1] text-xs sm:text-sm text-[#575E50] flex justify-between items-center">
                <span>Total Tagihan (Inc. Tax & Service):</span>
                <strong class="text-base font-bold text-[#22261E]">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong>
            </div>

            {{-- CTA --}}
            <div class="pt-4">
                <a href="{{ route('qr.menu') }}" class="btn-home block w-full rounded-full bg-[#475638] hover:bg-[#36422A] text-white font-bold py-3.5 text-sm shadow-md transition transform active:scale-98">
                    Pesan Menu Tambahan
                </a>
            </div>
            
        </div>

        <p class="mt-6 text-xs text-[#889180]">Harap tetap menunggu di meja, tim barista kami akan segera mengantarkan pesananmu.</p>

    </div>
</body>
</html>
