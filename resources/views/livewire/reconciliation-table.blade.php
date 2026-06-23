<div>

{{-- ════════════════════════════════════════════════════════════════════════
     FLASH MESSAGE (Livewire-triggered)
════════════════════════════════════════════════════════════════════════ --}}
@if (session()->has('success'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed top-16 right-4 z-50 bg-emerald-600 text-white text-sm font-semibold
                px-4 py-3 rounded-lg shadow-xl flex items-center gap-2 max-w-sm">
        <span>✅</span> {{ session('success') }}
    </div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════
     FILTER BAR + ADD BUTTON
════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-4">
    <div class="flex flex-wrap items-end gap-3">

        {{-- Tahap filter — first in the hierarchy: Tahap → Cluster → Unit --}}
        <div class="min-w-[130px]">
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Tahap</label>
            <select wire:model.live="filterTahap"
                    class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-[#0F1F3D]/20 focus:border-[#0F1F3D]
                           bg-white transition-colors">
                <option value="">All Tahap</option>
                @foreach ($tahapOptions as $tahap)
                    <option value="{{ $tahap }}">{{ $tahap }}</option>
                @endforeach
            </select>
        </div>

        {{-- Search --}}
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Search</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Buyer name, block, cluster…"
                    class="w-full pl-8 pr-3 py-2 text-sm border border-slate-200 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-[#0F1F3D]/20 focus:border-[#0F1F3D]
                           placeholder-slate-300 transition-colors"
                >
            </div>
        </div>

        {{-- Cluster filter --}}
        <div class="min-w-[150px]">
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Cluster</label>
            <select wire:model.live="filterCluster"
                    class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-[#0F1F3D]/20 focus:border-[#0F1F3D]
                           bg-white transition-colors">
                <option value="">All Clusters</option>
                @foreach ($clusters as $cluster)
                    <option value="{{ $cluster->id }}">{{ $cluster->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status filter --}}
        <div class="min-w-[140px]">
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Status</label>
            <select wire:model.live="filterStatus"
                    class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-[#0F1F3D]/20 focus:border-[#0F1F3D]
                           bg-white transition-colors">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="unpaid">Unpaid</option>
                <option value="settled">Settled</option>
                <option value="land_cleared">Land Cleared</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        {{-- Payment type filter --}}
        <div class="min-w-[140px]">
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Payment Type</label>
            <select wire:model.live="filterPayType"
                    class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-[#0F1F3D]/20 focus:border-[#0F1F3D]
                           bg-white transition-colors">
                <option value="">All Types</option>
                <option value="Cash Keras">Cash Keras</option>
                <option value="Cash Bertahap">Cash Bertahap</option>
                <option value="KPR">KPR</option>
            </select>
        </div>

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Import from Excel --}}
        <div x-data="{
                loading: false,
                pick() { $refs.importFile.click() },
                selected(e) {
                    const f = e.target.files[0];
                    if (f) { this.loading = true; this.$refs.importForm.submit(); }
                }
             }">
            <form x-ref="importForm"
                  action="{{ route('reconciliation.import') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="hidden">
                @csrf
                <input type="file" name="import_file" accept=".xlsx,.xls"
                       x-ref="importFile" @change="selected($event)">
            </form>
            <button @click="pick()" :disabled="loading"
                    class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-emerald-600
                           text-emerald-700 hover:bg-emerald-600 hover:text-white
                           text-sm font-semibold rounded-lg transition-colors whitespace-nowrap
                           disabled:opacity-60 disabled:cursor-wait">
                <span x-show="!loading">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </span>
                <span x-show="loading" x-cloak>
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span x-text="loading ? 'Importing…' : 'Import Excel'"></span>
            </button>
        </div>

        {{-- Export to Excel — plain link (file download), carries current filters --}}
        <a href="{{ route('reconciliation.export', [
                'search'       => $search ?: null,
                'tahap'        => $filterTahap ?: null,
                'cluster'      => $filterCluster ?: null,
                'status'       => $filterStatus ?: null,
                'payment_type' => $filterPayType ?: null,
            ]) }}"
           class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-[#0F1F3D] text-[#0F1F3D]
                  hover:bg-[#0F1F3D] hover:text-white text-sm font-semibold rounded-lg transition-colors
                  whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export to Excel
        </a>

        {{-- Add New House --}}
        <button wire:click="openAddModal"
                class="flex items-center gap-2 px-4 py-2 bg-[#0F1F3D] hover:bg-[#1a3560] text-white
                       text-sm font-semibold rounded-lg shadow transition-colors whitespace-nowrap">
            <span class="text-amber-400 text-base leading-none">＋</span>
            Add New House
        </button>
    </div>

    {{-- Result count --}}
    <div class="mt-3 text-xs text-slate-400">
        Showing <span class="font-semibold text-slate-600">{{ $units->total() }}</span> unit(s)
        @if ($search || $filterTahap || $filterCluster || $filterStatus || $filterPayType)
            — <button wire:click="$set('search',''); $set('filterTahap',''); $set('filterCluster',''); $set('filterStatus',''); $set('filterPayType','')"
                      class="text-[#0F1F3D] underline hover:no-underline">Clear filters</button>
        @endif
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     MAIN TABLE
════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

    {{-- Loading overlay --}}
    <div wire:loading.delay class="relative">
        <div class="absolute inset-0 bg-white/70 z-20 flex items-center justify-center rounded-2xl">
            <div class="flex items-center gap-2 text-sm text-slate-500 bg-white shadow rounded-full px-4 py-2">
                <svg class="animate-spin h-4 w-4 text-[#0F1F3D]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Loading…
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm table-header-sticky">
            <thead>
                <tr class="bg-[#0F1F3D] text-left">
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">#</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Tahap</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Cluster</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Unit</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Buyer</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Type</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">LB/LT (m²)</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Harga Penjualan</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Down Payment</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Kavling Cap</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Angsuran/Bln</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Payment</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Inst.</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Land Progress</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest text-center whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($units as $unit)
                    @php
                        $kavling    = $unit->kavling_value;
                        $cumNis     = $unit->cumulative_nis_share;
                        $landPct    = $kavling > 0 ? min(100, round(($cumNis / $kavling) * 100)) : 0;
                        $paidCount  = $unit->payments->count();
                        $buyer      = $unit->activeBuyer;
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors group">

                        {{-- Row number --}}
                        <td class="px-4 py-3 text-slate-400 font-num text-xs">
                            {{ ($units->currentPage() - 1) * $units->perPage() + $loop->iteration }}
                        </td>

                        {{-- Tahap --}}
                        <td class="px-4 py-3">
                            @if ($unit->tahap)
                                <span class="badge bg-indigo-100 text-indigo-800">{{ $unit->tahap }}</span>
                            @else
                                <span class="text-slate-300 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Status (duplicate — visible without scrolling) --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            @php
                                $statusConfig = [
                                    'active'       => ['bg-emerald-100 text-emerald-800', '● Active'],
                                    'unpaid'       => ['bg-rose-100 text-rose-700',       '○ Unpaid'],
                                    'settled'      => ['bg-sky-100 text-sky-700',          '✓ Settled'],
                                    'land_cleared' => ['bg-amber-100 text-amber-800',      '✦ Land Cleared'],
                                    'cancelled'    => ['bg-slate-100 text-slate-500',      '✕ Cancelled'],
                                ][$unit->status] ?? ['bg-slate-100 text-slate-600', $unit->status];
                            @endphp
                            <span class="badge {{ $statusConfig[0] }}">{{ $statusConfig[1] }}</span>
                        </td>

                        {{-- Cluster --}}
                        <td class="px-4 py-3">
                            <span class="font-semibold text-[#0F1F3D] text-xs tracking-wide">
                                {{ $unit->cluster->name }}
                            </span>
                        </td>

                        {{-- Unit label --}}
                        <td class="px-4 py-3">
                            <span class="font-num font-semibold text-slate-800">{{ $unit->unit_label }}</span>
                        </td>

                        {{-- Buyer --}}
                        <td class="px-4 py-3">
                            @if ($buyer)
                                <div class="font-semibold text-slate-800 text-xs">{{ $buyer->name }}</div>
                                @if ($buyer->contract_date)
                                    <div class="text-slate-400 text-[10px]">{{ $buyer->contract_date->format('d M Y') }}</div>
                                @endif
                            @else
                                <span class="text-slate-300 italic text-xs">No buyer</span>
                            @endif
                        </td>

                        {{-- House type --}}
                        <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                            {{ $unit->house_type ?? '—' }}
                        </td>

                        {{-- LB / LT --}}
                        <td class="px-4 py-3 font-num text-xs text-slate-700 whitespace-nowrap">
                            {{ $unit->luas_bangunan ?? '—' }}<span class="text-slate-400">/</span>{{ $unit->luas_tanah }}
                        </td>

                        {{-- Harga Penjualan --}}
                        <td class="px-4 py-3 font-num text-xs text-slate-700 whitespace-nowrap">
                            @if ($unit->harga_penjualan !== null)
                                Rp {{ number_format($unit->harga_penjualan, 0, ',', '.') }}
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>

                        {{-- Down Payment --}}
                        <td class="px-4 py-3 font-num text-xs text-slate-700 whitespace-nowrap">
                            @if ($unit->down_payment !== null)
                                Rp {{ number_format($unit->down_payment, 0, ',', '.') }}
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>

                        {{-- Kavling Cap --}}
                        <td class="px-4 py-3 font-num text-xs whitespace-nowrap">
                            <span class="text-amber-700 font-semibold">
                                Rp {{ number_format($kavling, 0, ',', '.') }}
                            </span>
                        </td>

                        {{-- Angsuran / Bulan --}}
                        <td class="px-4 py-3 font-num text-xs text-slate-700 whitespace-nowrap">
                            @if ($unit->angsuran_per_bulan !== null)
                                Rp {{ number_format($unit->angsuran_per_bulan, 0, ',', '.') }}
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>

                        {{-- Payment Type --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($unit->payment_type)
                                @php
                                    $ptColor = match($unit->payment_type) {
                                        'Cash Keras'    => 'bg-emerald-100 text-emerald-800',
                                        'Cash Bertahap' => 'bg-blue-100 text-blue-800',
                                        'KPR'           => 'bg-purple-100 text-purple-800',
                                        default         => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="badge {{ $ptColor }}">
                                    {{ $unit->payment_type }}
                                    @if($unit->payment_type !== 'Cash Keras' && $unit->max_installments !== null)
                                    ({{ $unit->max_installments }}x)
                                @endif
                            </span>
                            @else
                                <span class="text-slate-300 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Installments paid --}}
                        <td class="px-4 py-3 font-num text-xs text-center whitespace-nowrap">
                            <span class="{{ $paidCount > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">
                                {{ $paidCount }}
                            </span>
                            <span class="text-slate-300">/{{ $unit->max_installments ?? '—' }}</span>
                        </td>

                        {{-- Land (Kavling) Progress --}}
                        <td class="px-4 py-3 min-w-[120px]">
                            <div class="kavling-bar-track">
                                <div class="kavling-bar-fill
                                    {{ $landPct >= 100 ? 'bg-amber-500' : ($landPct > 60 ? 'bg-amber-400' : 'bg-[#0F1F3D]') }}"
                                     style="width: {{ $landPct }}%"></div>
                            </div>
                            <div class="text-[10px] font-num mt-0.5 text-slate-500">
                                {{ $landPct }}%
                                @if ($unit->is_land_cleared)
                                    <span class="text-amber-600 font-semibold ml-1">✦ Cleared</span>
                                @endif
                            </div>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            @php
                                $statusConfig = [
                                    'active'       => ['bg-emerald-100 text-emerald-800', '● Active'],
                                    'unpaid'       => ['bg-rose-100 text-rose-700',       '○ Unpaid'],
                                    'settled'      => ['bg-sky-100 text-sky-700',          '✓ Settled'],
                                    'land_cleared' => ['bg-amber-100 text-amber-800',      '✦ Land Cleared'],
                                    'cancelled'    => ['bg-slate-100 text-slate-500',      '✕ Cancelled'],
                                ][$unit->status] ?? ['bg-slate-100 text-slate-600', $unit->status];
                            @endphp
                            <span class="badge {{ $statusConfig[0] }}">{{ $statusConfig[1] }}</span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1 opacity-70 group-hover:opacity-100 transition-opacity">
                                <button wire:click="openEditModal({{ $unit->id }})"
                                        title="Edit unit"
                                        class="p-1.5 rounded hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button wire:click="confirmDelete({{ $unit->id }})"
                                        title="Delete unit"
                                        class="p-1.5 rounded hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="17" class="px-4 py-16 text-center">
                            <div class="text-slate-300 text-4xl mb-3">🏠</div>
                            <p class="text-slate-500 font-medium">No units found</p>
                            <p class="text-slate-400 text-sm mt-1">
                                @if ($search || $filterTahap || $filterCluster || $filterStatus || $filterPayType)
                                    Try adjusting your filters, or
                                    <button wire:click="$set('search',''); $set('filterTahap',''); $set('filterCluster',''); $set('filterStatus',''); $set('filterPayType','')"
                                            class="text-[#0F1F3D] underline">clear all filters</button>
                                @else
                                    Click <strong>Add New House</strong> to register the first unit.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($units->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            {{ $units->links() }}
        </div>
    @endif
</div>


{{-- ════════════════════════════════════════════════════════════════════════
     ADD NEW HOUSE MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showAddModal)
<div class="modal-backdrop" wire:click.self="closeAddModal">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-[#0F1F3D] rounded-t-2xl">
            <div>
                <h2 class="text-white font-bold text-base" style="font-family:'Plus Jakarta Sans',sans-serif">
                    ＋ Add New House
                </h2>
                <p class="text-slate-400 text-xs mt-0.5">Register a new unit to the NIS portfolio</p>
            </div>
            <button wire:click="closeAddModal" class="text-slate-400 hover:text-white transition-colors p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-5">

            {{-- Section: Unit Identity --}}
            <div>
                <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Unit Identity</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @include('livewire.partials.form-field', [
                        'label'       => 'Tahap (opt.)',
                        'errorKey'    => 'add_tahap',
                        'inputHtml'   => '<select wire:model="add_tahap" class="form-input">
                            <option value="">No tahap…</option>
                            ' . collect($tahapOptions)->map(fn($t) => '<option value="'.$t.'">'.$t.'</option>')->implode('') . '
                        </select>',
                    ])
                    @include('livewire.partials.form-field', [
                        'label'       => 'Cluster',
                        'errorKey'    => 'add_cluster_id',
                        'inputHtml'   => '<select wire:model="add_cluster_id" class="form-input">
                            <option value="">Select cluster…</option>
                            ' . $clusters->map(fn($c) => '<option value="'.$c->id.'">'.$c->name.'</option>')->implode('') . '
                        </select>',
                    ])
                    @include('livewire.partials.form-field', ['label'=>'Block',           'errorKey'=>'add_block',        'inputHtml'=>'<input wire:model="add_block" type="text" placeholder="A" class="form-input">'])
                    @include('livewire.partials.form-field', ['label'=>'Unit No.',        'errorKey'=>'add_unit_number',  'inputHtml'=>'<input wire:model="add_unit_number" type="text" placeholder="01" class="form-input">'])
                    @include('livewire.partials.form-field', ['label'=>'House Type (opt.)','errorKey'=>'add_house_type',  'inputHtml'=>'<input wire:model="add_house_type" type="text" placeholder="STANDARD" class="form-input">'])
                    @include('livewire.partials.form-field', ['label'=>'LB (opt., m²)',   'errorKey'=>'add_luas_bangunan','inputHtml'=>'<input wire:model="add_luas_bangunan" type="number" min="1" placeholder="20" class="form-input font-num">'])
                    @include('livewire.partials.form-field', ['label'=>'LT (m²)',         'errorKey'=>'add_luas_tanah',   'inputHtml'=>'<input wire:model="add_luas_tanah" type="number" min="1" placeholder="75" class="form-input font-num">'])
                </div>
                <p class="text-[10px] text-slate-400 mt-2">
                    Only <strong>Cluster, Block, Unit No., and LT</strong> are required. Everything else can be filled in later.
                </p>
            </div>

            {{-- Kavling preview --}}
            @if ($add_luas_tanah && is_numeric($add_luas_tanah) && (int)$add_luas_tanah > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 flex items-center gap-3">
                <span class="text-amber-500 text-lg">✦</span>
                <div>
                    <div class="text-[11px] text-amber-700 font-semibold uppercase tracking-wider">Kavling Cap Preview</div>
                    <div class="font-num font-bold text-amber-800 text-sm">
                        Rp {{ number_format((int)$add_luas_tanah * 4000000, 0, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-amber-600">{{ $add_luas_tanah }} m² × Rp 4,000,000/m²</div>
                </div>
            </div>
            @endif

            {{-- Section: Financial --}}
            <div>
                <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Financial Terms (all optional)</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        @include('livewire.partials.form-field', ['label'=>'Payment Type (opt.)', 'errorKey'=>'add_payment_type', 'inputHtml'=>'
                            <select wire:model="add_payment_type" class="form-input">
                                <option value="">Not set yet…</option>
                                <option value="Cash Keras">Cash Keras</option>
                                <option value="Cash Bertahap">Cash Bertahap</option>
                                <option value="KPR">KPR</option>
                            </select>'])
                    </div>
                    <div>
                        @include('livewire.partials.money-input', [
                            'wireModel'   => 'add_harga_penjualan',
                            'label'       => 'Harga Penjualan (opt., Rp)',
                            'placeholder' => '1000000000',
                            'live'        => true,
                        ])
                    </div>
                    <div>
                        @include('livewire.partials.money-input', [
                            'wireModel'   => 'add_down_payment',
                            'label'       => 'Down Payment (opt., Rp)',
                            'placeholder' => '50000000',
                            'live'        => true,
                        ])
                    </div>
                    @include('livewire.partials.form-field', ['label'=>'Max Installments (opt.)', 'errorKey'=>'add_max_installments','inputHtml'=>'<input wire:model="add_max_installments" type="number" min="1" max="360" placeholder="10" class="form-input font-num">'])
                    @include('livewire.partials.form-field', ['label'=>'Initial Status',       'errorKey'=>'add_status',          'inputHtml'=>'
                        <select wire:model="add_status" class="form-input">
                            <option value="unpaid">Unpaid</option>
                            <option value="active">Active</option>
                        </select>'])
                </div>

                {{-- Angsuran preview --}}
                @if ($add_harga_penjualan && $add_max_installments && (int)$add_max_installments > 0)
                <div class="mt-3 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
                    <div class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Angsuran Per Bulan Preview</div>
                    <div class="font-num font-bold text-[#0F1F3D] text-sm mt-0.5">
                        Rp {{ number_format((int)round(((int)$add_harga_penjualan - (int)$add_down_payment) / (int)$add_max_installments), 0, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-slate-400">NIS 30% = Rp {{ number_format((int)round((((int)$add_harga_penjualan - (int)$add_down_payment) / (int)$add_max_installments) * 0.3), 0, ',', '.') }}/bln</div>
                </div>
                @endif
            </div>

            {{-- Section: Buyer --}}
            <div>
                <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Buyer (optional)</h3>
                <div class="grid grid-cols-2 gap-3">
                    @include('livewire.partials.form-field', ['label'=>'Buyer Name',     'errorKey'=>'add_buyer_name',    'inputHtml'=>'<input wire:model="add_buyer_name" type="text" placeholder="FULL NAME" class="form-input uppercase">'])
                    @include('livewire.partials.form-field', ['label'=>'Contract Date',  'errorKey'=>'add_contract_date', 'inputHtml'=>'<input wire:model="add_contract_date" type="date" class="form-input">'])
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Notes (optional)</label>
                <textarea wire:model="add_notes" rows="2"
                          class="form-input w-full resize-none"
                          placeholder="Any additional notes…"></textarea>
            </div>
        </div>

        {{-- Modal Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            <button wire:click="closeAddModal"
                    class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">
                Cancel
            </button>
            <button wire:click="saveNewUnit" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-[#0F1F3D] hover:bg-[#1a3560] text-white text-sm font-semibold
                           rounded-lg shadow transition-colors disabled:opacity-60 flex items-center gap-2">
                <span wire:loading wire:target="saveNewUnit">
                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                Save Unit
            </button>
        </div>
    </div>
</div>
@endif


{{-- ════════════════════════════════════════════════════════════════════════
     EDIT UNIT MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showEditModal)
<div class="modal-backdrop" wire:click.self="closeEditModal">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-blue-900 rounded-t-2xl">
            <div>
                <h2 class="text-white font-bold text-base" style="font-family:'Plus Jakarta Sans',sans-serif">
                    ✏️ Edit Unit
                </h2>
                <p class="text-blue-300 text-xs mt-0.5">All fields are mutable — changes saved immediately</p>
            </div>
            <button wire:click="closeEditModal" class="text-blue-300 hover:text-white transition-colors p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-5">

            {{-- Unit Identity --}}
            <div>
                <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Unit Identity</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @include('livewire.partials.form-field', [
                        'label'     => 'Tahap (opt.)',
                        'errorKey'  => 'edit_tahap',
                        'inputHtml' => '<select wire:model="edit_tahap" class="form-input">
                            <option value="">No tahap…</option>
                            ' . collect($tahapOptions)->map(fn($t) => '<option value="'.$t.'">'.$t.'</option>')->implode('') . '
                        </select>',
                    ])
                    @include('livewire.partials.form-field', [
                        'label'     => 'Cluster',
                        'errorKey'  => 'edit_cluster_id',
                        'inputHtml' => '<select wire:model="edit_cluster_id" class="form-input">
                            ' . $clusters->map(fn($c) => '<option value="'.$c->id.'">'.$c->name.'</option>')->implode('') . '
                        </select>',
                    ])
                    @include('livewire.partials.form-field', ['label'=>'Block',            'errorKey'=>'edit_block',        'inputHtml'=>'<input wire:model="edit_block" type="text" class="form-input">'])
                    @include('livewire.partials.form-field', ['label'=>'Unit No.',         'errorKey'=>'edit_unit_number',  'inputHtml'=>'<input wire:model="edit_unit_number" type="text" class="form-input">'])
                    @include('livewire.partials.form-field', ['label'=>'House Type (opt.)','errorKey'=>'edit_house_type',   'inputHtml'=>'<input wire:model="edit_house_type" type="text" class="form-input">'])
                    @include('livewire.partials.form-field', ['label'=>'LB (opt., m²)',    'errorKey'=>'edit_luas_bangunan','inputHtml'=>'<input wire:model="edit_luas_bangunan" type="number" min="1" class="form-input font-num">'])
                    @include('livewire.partials.form-field', ['label'=>'LT (m²)',          'errorKey'=>'edit_luas_tanah',   'inputHtml'=>'<input wire:model="edit_luas_tanah" type="number" min="1" class="form-input font-num">'])
                </div>
            </div>

            {{-- Financial --}}
            <div>
                <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Financial Terms (all optional)</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        @include('livewire.partials.form-field', ['label'=>'Payment Type (opt.)', 'errorKey'=>'edit_payment_type', 'inputHtml'=>'
                            <select wire:model="edit_payment_type" class="form-input">
                                <option value="">Not set yet…</option>
                                <option value="Cash Keras">Cash Keras</option>
                                <option value="Cash Bertahap">Cash Bertahap</option>
                                <option value="KPR">KPR</option>
                            </select>'])
                    </div>
                    <div>
                        @include('livewire.partials.money-input', [
                            'wireModel' => 'edit_harga_penjualan',
                            'label'     => 'Harga Penjualan (opt., Rp)',
                        ])
                    </div>
                    <div>
                        @include('livewire.partials.money-input', [
                            'wireModel' => 'edit_down_payment',
                            'label'     => 'Down Payment (opt., Rp)',
                        ])
                    </div>
                    @include('livewire.partials.form-field', ['label'=>'Max Installments (opt.)', 'errorKey'=>'edit_max_installments','inputHtml'=>'<input wire:model="edit_max_installments" type="number" min="1" max="360" class="form-input font-num">'])
                    @include('livewire.partials.form-field', ['label'=>'Status',               'errorKey'=>'edit_status',          'inputHtml'=>'
                        <select wire:model="edit_status" class="form-input">
                            <option value="active">Active</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="settled">Settled</option>
                            <option value="land_cleared">Land Cleared</option>
                            <option value="cancelled">Cancelled</option>
                        </select>'])
                </div>
            </div>

            {{-- Buyer --}}
            <div>
                <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Buyer</h3>
                <div class="grid grid-cols-2 gap-3">
                    @include('livewire.partials.form-field', ['label'=>'Buyer Name',    'errorKey'=>'edit_buyer_name',    'inputHtml'=>'<input wire:model="edit_buyer_name" type="text" class="form-input uppercase" placeholder="FULL NAME">'])
                    @include('livewire.partials.form-field', ['label'=>'Contract Date', 'errorKey'=>'edit_contract_date', 'inputHtml'=>'<input wire:model="edit_contract_date" type="date" class="form-input">'])
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Notes</label>
                <textarea wire:model="edit_notes" rows="2" class="form-input w-full resize-none"></textarea>
                @error('edit_notes') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            <button wire:click="closeEditModal" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">
                Cancel
            </button>
            <button wire:click="saveEdit" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold
                           rounded-lg shadow transition-colors disabled:opacity-60 flex items-center gap-2">
                <span wire:loading wire:target="saveEdit">
                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                Save Changes
            </button>
        </div>
    </div>
</div>
@endif


{{-- ════════════════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showDeleteConfirm)
<div class="modal-backdrop">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-rose-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="font-bold text-slate-800 text-lg mb-1">Delete Unit {{ $deletingLabel }}?</h3>
            <p class="text-slate-500 text-sm">
                This performs a soft delete — the unit and all its payment history will be hidden but preserved in the database.
            </p>
        </div>
        <div class="flex gap-3 px-6 pb-6">
            <button wire:click="cancelDelete"
                    class="flex-1 px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                Keep Unit
            </button>
            <button wire:click="deleteUnit"
                    class="flex-1 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-lg shadow transition-colors">
                Yes, Delete
            </button>
        </div>
    </div>
</div>
@endif

</div>