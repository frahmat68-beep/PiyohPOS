<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem; font-family: 'Plus Jakarta Sans', sans-serif;">
        {{-- Filter & Actions Section --}}
        <x-filament::section>
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #889180; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.375rem;">Pilih Tanggal</label>
                        <input type="date" wire:model.live="selectedDate" style="padding: 0.5rem 0.875rem; border-radius: 0.75rem; border: 1px solid #3f3f46; background-color: #18181b; color: #fafafa; font-size: 0.875rem; font-weight: 500; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #889180; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.375rem;">Pilih Outlet</label>
                        <select wire:model.live="selectedOutletId" style="padding: 0.5rem 0.875rem; border-radius: 0.75rem; border: 1px solid #3f3f46; background-color: #18181b; color: #fafafa; font-size: 0.875rem; font-weight: 500; outline: none;">
                            @foreach(\App\Models\Outlet::all() as $out)
                                <option value="{{ $out->id }}">{{ $out->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                    <button type="button" wire:click="downloadAccurateCsv" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; border-radius: 0.75rem; background-color: #C4823F; color: #ffffff; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; transition: background-color 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                        <svg width="16" height="16" style="width: 1rem; height: 1rem; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download CSV (Accurate)</span>
                    </button>

                    <button type="button" wire:click="closeStore" wire:confirm="Apakah Anda yakin ingin menutup kasir hari ini dan membekukan rekap?" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; border-radius: 0.75rem; background-color: #475638; color: #ffffff; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; transition: background-color 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                        <svg width="16" height="16" style="width: 1rem; height: 1rem; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Tutup Toko &amp; Rekap</span>
                    </button>
                </div>
            </div>
        </x-filament::section>

        {{-- Summary Stats Grid --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div style="padding: 1.25rem; border-radius: 1rem; background-color: #18181b; border: 1px solid #27272a; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <span style="font-size: 0.75rem; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Total Pendapatan Bersih</span>
                <span style="font-size: 1.5rem; font-weight: 700; font-family: 'Playfair Display', serif; color: #34d399; margin-top: 0.375rem; display: block;">
                    Rp {{ number_format($recapData['total_revenue'] ?? 0, 0, ',', '.') }}
                </span>
                <span style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem; display: block;">{{ $recapData['total_orders'] ?? 0 }} Transaksi Lunas</span>
            </div>

            <div style="padding: 1.25rem; border-radius: 1rem; background-color: #18181b; border: 1px solid #27272a; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <span style="font-size: 0.75rem; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Midtrans Online (QRIS)</span>
                <span style="font-size: 1.5rem; font-weight: 700; font-family: 'Playfair Display', serif; color: #60a5fa; margin-top: 0.375rem; display: block;">
                    Rp {{ number_format($recapData['midtrans_revenue'] ?? 0, 0, ',', '.') }}
                </span>
                <span style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem; display: block;">Termasuk QRIS &amp; E-Wallet</span>
            </div>

            <div style="padding: 1.25rem; border-radius: 1rem; background-color: #18181b; border: 1px solid #27272a; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <span style="font-size: 0.75rem; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Tunai (Cash Kasir)</span>
                <span style="font-size: 1.5rem; font-weight: 700; font-family: 'Playfair Display', serif; color: #fbbf24; margin-top: 0.375rem; display: block;">
                    Rp {{ number_format($recapData['cash_revenue'] ?? 0, 0, ',', '.') }}
                </span>
                <span style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem; display: block;">Penerimaan fisik kasir</span>
            </div>

            <div style="padding: 1.25rem; border-radius: 1rem; background-color: #18181b; border: 1px solid #27272a; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <span style="font-size: 0.75rem; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Pajak PB1 + Service</span>
                <span style="font-size: 1.5rem; font-weight: 700; font-family: 'Playfair Display', serif; color: #e4e4e7; margin-top: 0.375rem; display: block;">
                    Rp {{ number_format(($recapData['tax_total'] ?? 0) + ($recapData['service_charge_total'] ?? 0), 0, ',', '.') }}
                </span>
                <span style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem; display: block;">PB1: Rp {{ number_format($recapData['tax_total'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- Product Sales Breakdown Table --}}
        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <span style="font-size: 1rem; font-weight: 700; font-family: 'Playfair Display', serif; color: #fafafa;">Rincian Penjualan Menu (Kuantitas &amp; Nilai)</span>
                    <span style="font-size: 0.75rem; font-weight: 600; color: #a1a1aa; background-color: #27272a; padding: 0.25rem 0.625rem; border-radius: 9999px;">{{ count($recapData['items_summary'] ?? []) }} Menu Terjual</span>
                </div>
            </x-slot>

            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; font-size: 0.875rem; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #27272a; color: #71717a; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 0.75rem 1rem;">Kategori</th>
                            <th style="padding: 0.75rem 1rem;">Nama Produk</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Harga Satuan</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Terjual (Qty)</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Subtotal Penjualan</th>
                        </tr>
                    </thead>
                    <tbody style="border-top: 1px solid #27272a;">
                        @forelse($recapData['items_summary'] ?? [] as $it)
                            <tr style="border-bottom: 1px solid #27272a; transition: background-color 0.15s;">
                                <td style="padding: 0.75rem 1rem; color: #a1a1aa;">{{ $it['category_name'] }}</td>
                                <td style="padding: 0.75rem 1rem; font-weight: 700; color: #fafafa;">{{ $it['product_name'] }}</td>
                                <td style="padding: 0.75rem 1rem; text-align: center; color: #d4d4d8;">Rp {{ number_format($it['unit_price'], 0, ',', '.') }}</td>
                                <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700; color: #34d399;">{{ $it['quantity'] }}x</td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #fafafa;">Rp {{ number_format($it['total_sales'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding: 2.5rem 1rem; text-align: center; color: #71717a; font-size: 0.875rem;">Belum ada transaksi menu pada tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
