<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter & Actions Card --}}
        <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Tanggal</label>
                    <input type="date" wire:model.live="selectedDate" class="rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm font-medium focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Outlet</label>
                    <select wire:model.live="selectedOutletId" class="rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm font-medium focus:ring-2 focus:ring-primary-500">
                        @foreach(\App\Models\Outlet::all() as $out)
                            <option value="{{ $out->id }}">{{ $out->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" wire:click="downloadAccurateCsv" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download CSV (Format Accurate)</span>
                </button>

                <button type="button" wire:click="closeStore" wire:confirm="Apakah Anda yakin ingin menutup kasir hari ini dan membekukan rekap?" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Tutup Toko &amp; Rekap Hari Ini</span>
                </button>
            </div>
        </div>

        {{-- Summary Stats Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Total Pendapatan Bersih</span>
                <span class="text-2xl font-bold font-serif text-emerald-600 dark:text-emerald-400 mt-1 block">
                    Rp {{ number_format($recapData['total_revenue'] ?? 0, 0, ',', '.') }}
                </span>
                <span class="text-xs text-gray-400 mt-1 block">{{ $recapData['total_orders'] ?? 0 }} Transaksi Lunas</span>
            </div>

            <div class="p-5 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Midtrans / Online (QRIS)</span>
                <span class="text-2xl font-bold font-serif text-primary-600 dark:text-primary-400 mt-1 block">
                    Rp {{ number_format($recapData['midtrans_revenue'] ?? 0, 0, ',', '.') }}
                </span>
                <span class="text-xs text-gray-400 mt-1 block">Termasuk QRIS &amp; E-Wallet</span>
            </div>

            <div class="p-5 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Tunai (Cash Kasir)</span>
                <span class="text-2xl font-bold font-serif text-amber-600 dark:text-amber-400 mt-1 block">
                    Rp {{ number_format($recapData['cash_revenue'] ?? 0, 0, ',', '.') }}
                </span>
                <span class="text-xs text-gray-400 mt-1 block">Penerimaan fisik di kasir</span>
            </div>

            <div class="p-5 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Pajak PB1 + Service</span>
                <span class="text-2xl font-bold font-serif text-gray-700 dark:text-gray-300 mt-1 block">
                    Rp {{ number_format(($recapData['tax_total'] ?? 0) + ($recapData['service_charge_total'] ?? 0), 0, ',', '.') }}
                </span>
                <span class="text-xs text-gray-400 mt-1 block">PB1: Rp {{ number_format($recapData['tax_total'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- Product Sales Breakdown Table --}}
        <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Rincian Penjualan Menu (Kuantitas &amp; Nilai)</h3>
                <span class="text-xs text-gray-500">{{ count($recapData['items_summary'] ?? []) }} Menu Terjual</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4">Kategori</th>
                            <th class="py-3 px-4">Nama Produk</th>
                            <th class="py-3 px-4 text-center">Harga Satuan</th>
                            <th class="py-3 px-4 text-center">Terjual (Qty)</th>
                            <th class="py-3 px-4 text-right">Subtotal Penjualan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($recapData['items_summary'] ?? [] as $it)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-3 px-4 text-gray-500">{{ $it['category_name'] }}</td>
                                <td class="py-3 px-4 font-bold text-gray-900 dark:text-white">{{ $it['product_name'] }}</td>
                                <td class="py-3 px-4 text-center">Rp {{ number_format($it['unit_price'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-center font-bold text-primary-600">{{ $it['quantity'] }}x</td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900 dark:text-white">Rp {{ number_format($it['total_sales'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-400">Belum ada transaksi menu pada tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
