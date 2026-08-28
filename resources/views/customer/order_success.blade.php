<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Piyoh Kopi — Status Pesanan {{ $order->order_number }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#22261E] pb-16 antialiased selection:bg-[#475638] selection:text-white" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div class="max-w-lg mx-auto px-4 pt-8 text-center space-y-6">
        
        {{-- Main Status Card --}}
        <div class="bg-white border border-[#EBE4D8] rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            
            {{-- Status Icon & Heading --}}
            <div id="status-icon-wrapper" class="w-16 h-16 bg-[#EBF0E6] text-[#475638] rounded-full flex items-center justify-center mx-auto shadow-2xs transition-all duration-500">
                <svg id="status-icon" class="w-8 h-8 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#FAF7F2] border border-[#EBE4D8] text-xs font-bold text-[#C4823F] uppercase tracking-wider mb-2">
                    <span class="w-2 h-2 rounded-full bg-[#C4823F] animate-ping"></span>
                    Live Update
                </span>
                <h1 id="status-title" class="text-2xl font-bold font-serif text-[#22261E] transition-all duration-300">
                    Pesanan Berhasil Terkirim
                </h1>
                <p id="status-desc" class="mt-2 text-xs sm:text-sm text-[#575E50] leading-relaxed transition-all duration-300">
                    Pesananmu sedang menunggu konfirmasi kasir sebelum diracik tim barista.
                </p>
            </div>

            {{-- Step Progress Tracker --}}
            <div class="pt-2 pb-2">
                <div class="flex items-center justify-between relative">
                    <div class="absolute top-1/2 left-0 right-0 h-1 bg-[#EBE4D8] -translate-y-1/2 -z-0"></div>
                    <div id="progress-bar-fill" class="absolute top-1/2 left-0 h-1 bg-[#475638] -translate-y-1/2 -z-0 transition-all duration-700" style="width: 20%;"></div>

                    {{-- Step 1 --}}
                    <div class="relative z-10 flex flex-col items-center">
                        <span id="step-dot-1" class="w-7 h-7 rounded-full bg-[#475638] text-white text-xs font-bold flex items-center justify-center shadow-sm transition-all duration-300">1</span>
                        <span class="text-[10px] font-semibold text-[#475638] mt-1.5">Pending</span>
                    </div>

                    {{-- Step 2 --}}
                    <div class="relative z-10 flex flex-col items-center">
                        <span id="step-dot-2" class="w-7 h-7 rounded-full bg-[#EBE4D8] text-[#889180] text-xs font-bold flex items-center justify-center shadow-sm transition-all duration-300">2</span>
                        <span class="text-[10px] font-medium text-[#889180] mt-1.5">Antrian</span>
                    </div>

                    {{-- Step 3 --}}
                    <div class="relative z-10 flex flex-col items-center">
                        <span id="step-dot-3" class="w-7 h-7 rounded-full bg-[#EBE4D8] text-[#889180] text-xs font-bold flex items-center justify-center shadow-sm transition-all duration-300">3</span>
                        <span class="text-[10px] font-medium text-[#889180] mt-1.5">Diseduh</span>
                    </div>

                    {{-- Step 4 --}}
                    <div class="relative z-10 flex flex-col items-center">
                        <span id="step-dot-4" class="w-7 h-7 rounded-full bg-[#EBE4D8] text-[#889180] text-xs font-bold flex items-center justify-center shadow-sm transition-all duration-300">4</span>
                        <span class="text-[10px] font-medium text-[#889180] mt-1.5">Siap</span>
                    </div>
                </div>
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
            <div class="p-4 bg-[#FAF7F2] border border-[#EBE4D8] rounded-2xl flex items-center justify-between">
                <div class="text-left">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#889180] block">Nomor Pesanan</span>
                    <span class="order-number text-lg font-mono font-bold text-[#475638]">{{ $order->order_number }}</span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#889180] block">Total Tagihan</span>
                    <span class="text-base font-bold text-[#22261E]">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- Action Button --}}
            <div class="pt-2">
                <a href="/qr/menu" class="touch-target-44 flex items-center justify-center w-full rounded-full bg-[#475638] hover:bg-[#36422A] text-white font-bold py-3.5 text-sm shadow-sm transition active:scale-98">
                    Pesan Menu Tambahan
                </a>
            </div>
            
        </div>

        <p class="text-xs text-[#889180]">
            Harap tetap berada di meja Anda. Barista kami akan segera mengantarkan pesanan saat siap.
        </p>

    </div>

    {{-- Live Status Polling Script --}}
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const orderNumber = "{{ $order->order_number }}";
        let currentStatus = "{{ $order->status }}";

        const updateUI = (data) => {
            const titleEl = document.getElementById('status-title');
            const descEl = document.getElementById('status-desc');
            const fillEl = document.getElementById('progress-bar-fill');
            const step = data.progress_step || 1;

            // Update title & description
            titleEl.textContent = data.status_label;
            
            if (data.status === 'confirmed') {
                descEl.textContent = 'Pesanan telah dikonfirmasi kasir dan masuk antrian peracikan.';
                fillEl.style.width = '40%';
            } else if (data.status === 'preparing') {
                descEl.textContent = 'Barista sedang meracik menu pesananmu dengan sepenuh hati.';
                fillEl.style.width = '70%';
            } else if (data.status === 'ready' || data.status === 'served' || data.status === 'completed') {
                descEl.textContent = 'Pesanan sudah selesai diracik dan siap diantarkan ke meja Anda!';
                fillEl.style.width = '100%';
            }

            // Update step dots
            for (let i = 1; i <= 4; i++) {
                const dot = document.getElementById(`step-dot-${i}`);
                if (dot) {
                    if (i <= step) {
                        dot.className = "w-7 h-7 rounded-full bg-[#475638] text-white text-xs font-bold flex items-center justify-center shadow-sm transition-all duration-300";
                    } else {
                        dot.className = "w-7 h-7 rounded-full bg-[#EBE4D8] text-[#889180] text-xs font-bold flex items-center justify-center shadow-sm transition-all duration-300";
                    }
                }
            }
        };

        const pollStatus = async () => {
            try {
                const res = await fetch(`/orders/${orderNumber}/status`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.status !== currentStatus) {
                        currentStatus = data.status;
                        updateUI(data);
                    }
                }
            } catch (err) {
                console.error('Status poll error', err);
            }
        };

        // Poll every 4 seconds
        setInterval(pollStatus, 4000);
    });
    </script>
</body>
</html>
