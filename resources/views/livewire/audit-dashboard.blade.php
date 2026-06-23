{{-- audit-dashboard.blade.php — v2 CLEAN — NO ledger here, manage-payments owns it --}}
<div x-data="{
        onKeydown(e) {
            // Ignore shortcuts while typing in any input/textarea/select.
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (e.target.isContentEditable) return;

            // Ignore shortcuts while any modal is actually VISIBLE.
            // NOTE: must check computed visibility, not mere DOM presence —
            // Alpine x-show keeps elements in the DOM (display:none) even
            // when hidden, so a plain querySelector('.modal-backdrop') would
            // permanently block every shortcut the instant the page loads.
            const anyModalVisible = [...document.querySelectorAll('.modal-backdrop')]
                .some(el => el.offsetParent !== null);
            if (anyModalVisible) return;

            // Q — open the Cluster selector
            if (e.key === 'q' || e.key === 'Q') {
                e.preventDefault();
                const sel = document.getElementById('cluster-select');
                if (sel) {
                    sel.focus();
                    if (typeof sel.showPicker === 'function') {
                        try { sel.showPicker(); } catch (err) { /* unsupported context, focus is enough */ }
                    }
                }
            }

            // W — jump straight to the next empty installment slot
            if (e.key === 'w' || e.key === 'W') {
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('shortcut-next-slot'));
            }
        }
     }"
     x-on:keydown.window="onKeydown($event)"
     x-on:payment-recorded.window="$wire.refreshSummary()"
     x-on:payment-updated.window="$wire.refreshSummary()"
     x-on:payment-deleted.window="$wire.refreshSummary()">

{{-- Keyboard shortcut hint strip --}}
<div class="mb-4 flex items-center gap-3 text-[11px] text-slate-400">
    <span class="inline-flex items-center gap-1">
        <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-bold text-slate-500 shadow-sm">Q</kbd>
        Open cluster
    </span>
    <span class="inline-flex items-center gap-1">
        <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-bold text-slate-500 shadow-sm">W</kbd>
        Record next installment
    </span>
</div>

{{-- ════════════════════════════════════════════════════════════
     KPI WIDGETS
════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-[#0F1F3D] flex items-center justify-center flex-shrink-0">
            <span class="text-xl">🏠</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Houses</p>
            <p class="font-bold text-2xl text-[#0F1F3D] font-num leading-tight mt-0.5">{{ $kpis['total_houses'] }}</p>
            <p class="text-[10px] text-slate-400 mt-0.5">Registered units</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl {{ $kpis['total_unpaid'] > 0 ? 'bg-rose-100' : 'bg-emerald-100' }} flex items-center justify-center flex-shrink-0">
            <span class="text-xl">{{ $kpis['total_unpaid'] > 0 ? '⏳' : '✅' }}</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Unpaid Houses</p>
            <p class="font-bold text-2xl font-num leading-tight mt-0.5 {{ $kpis['total_unpaid'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ $kpis['total_unpaid'] }}
            </p>
            <p class="text-[10px] text-slate-400 mt-0.5">No payments recorded</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl {{ $kpis['total_underpaid_installments'] > 0 ? 'bg-amber-100' : 'bg-emerald-100' }} flex items-center justify-center flex-shrink-0">
            <span class="text-xl">❌</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Underpaid</p>
            <p class="font-bold text-2xl font-num leading-tight mt-0.5 {{ $kpis['total_underpaid_installments'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                {{ $kpis['total_underpaid_installments'] }}
            </p>
            <p class="text-[10px] text-slate-400 mt-0.5">Installment shortfalls</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">NIS Revenue vs Land Cap</p>
        @php
            $revPct = $kpis['total_land_valuation_cap'] > 0
                ? min(100, round(($kpis['total_revenue_collected'] / $kpis['total_land_valuation_cap']) * 100))
                : 0;
        @endphp
        <div class="flex items-end justify-between gap-2 mb-1.5">
            <span class="font-num font-bold text-sm text-[#0F1F3D]">
                Rp {{ number_format($kpis['total_revenue_collected'], 0, ',', '.') }}
            </span>
            <span class="font-num text-[10px] text-slate-400">
                / Rp {{ number_format($kpis['total_land_valuation_cap'], 0, ',', '.') }}
            </span>
        </div>
        <div class="kavling-bar-track">
            <div class="kavling-bar-fill bg-amber-400" style="width: {{ $revPct }}%"></div>
        </div>
        <p class="text-[10px] text-slate-400 font-num mt-1">{{ $revPct }}% of total land valuation collected</p>
    </div>

</div>

{{-- ════════════════════════════════════════════════════════════
     SEARCH PANEL
════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-5">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-1 h-5 rounded-full bg-amber-400"></div>
        <h2 class="font-bold text-[#0F1F3D] text-sm tracking-wide" style="font-family:'Plus Jakarta Sans',sans-serif">
            Unit Deep-Dive Search
        </h2>
        <span class="text-slate-400 text-xs hidden sm:inline">— select a cluster then a unit to audit</span>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">

        {{-- ① Cluster --}}
        <div class="flex-1">
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">① Cluster</label>
                <button type="button"
                        onclick="Livewire.dispatch('open-add-cluster')"
                        class="text-[10px] font-semibold text-[#3B4FC8] hover:underline">
                    + Add Cluster
                </button>
            </div>
            <div class="relative">
                <select id="cluster-select"
                        wire:model.live="selectedClusterId"
                        x-on:change="$dispatch('cluster-changed')"
                        class="w-full appearance-none py-3 pl-4 pr-10 text-sm font-semibold border-2 rounded-xl
                               bg-white transition-colors cursor-pointer focus:outline-none focus:border-[#0F1F3D]
                               {{ $selectedClusterId ? 'border-[#0F1F3D] text-[#0F1F3D]' : 'border-slate-200 text-slate-400' }}">
                    <option value="">Cluster…</option>
                    @foreach ($clusters as $cluster)
                        <option value="{{ $cluster->id }}">{{ $cluster->name }}</option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="w-5 h-5 text-[#3B4FC8]" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- ② Unit — searchable combobox (type "A-0", "Halberg", etc.) --}}
        <div class="flex-1"
             x-data="{
                open: false,
                query: @entangle('unitSearchQuery'),
                units: [],
                get filtered() {
                    if (!this.query) return this.units;
                    const q = this.query.toLowerCase();
                    return this.units.filter(u => u.label.toLowerCase().includes(q) || u.buyer.toLowerCase().includes(q));
                },
                async loadUnits() {
                    this.units = await $wire.unitOptions();
                }
             }"
             x-init="loadUnits()"
             x-on:cluster-changed.window="loadUnits()"
             @click.outside="open = false">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">② Unit</label>
            <div class="relative">
                <input type="text"
                       x-model="query"
                       @focus="open = true; $wire.set('selectedUnitId', '')"
                       @input="open = true"
                       :disabled="!{{ $selectedClusterId ? 'true' : 'false' }}"
                       placeholder="{{ $selectedClusterId ? 'Type A-0, buyer name…' : 'Pick a cluster first' }}"
                       autocomplete="off"
                       class="w-full py-3 pl-4 pr-10 text-sm font-semibold border-2 rounded-xl
                              bg-white transition-colors focus:outline-none focus:border-[#0F1F3D]
                              {{ !$selectedClusterId
                                 ? 'opacity-40 cursor-not-allowed border-slate-200 text-slate-400'
                                 : ($selectedUnitId ? 'border-[#0F1F3D] text-[#0F1F3D]' : 'border-slate-200 text-slate-700') }}">

                <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="w-5 h-5 {{ $selectedClusterId ? 'text-[#3B4FC8]' : 'text-slate-300' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </div>

                {{-- Dropdown panel --}}
                <div x-show="open && {{ $selectedClusterId ? 'true' : 'false' }}"
                     x-transition
                     style="display: none;"
                     class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto bg-white border-2 border-slate-200
                            rounded-xl shadow-lg py-1">
                    <template x-if="filtered.length === 0">
                        <p class="px-4 py-3 text-xs text-slate-400">No matching units.</p>
                    </template>
                    <template x-for="unit in filtered" :key="unit.id">
                        <button type="button"
                                @click="
                                    query = unit.label;
                                    open = false;
                                    $wire.set('selectedUnitId', unit.id);
                                "
                                class="w-full text-left px-4 py-2.5 text-sm hover:bg-slate-50 transition-colors
                                       flex items-center justify-between gap-2"
                                :class="{ 'bg-blue-50': String(unit.id) === String($wire.selectedUnitId) }">
                            <span x-text="unit.label" class="font-semibold text-slate-800"></span>
                            <span x-text="unit.buyer" class="text-slate-400 text-xs"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Search button --}}
        <div class="flex-shrink-0">
            <button wire:click="search"
                    wire:loading.attr="disabled"
                    @disabled(!$selectedClusterId || !$selectedUnitId)
                    class="w-full sm:w-auto px-8 py-3 rounded-xl font-bold text-sm tracking-wide
                           transition-all flex items-center justify-center gap-2
                           {{ ($selectedClusterId && $selectedUnitId)
                              ? 'bg-[#3B4FC8] hover:bg-[#2d3fa8] text-white cursor-pointer shadow-md'
                              : 'bg-slate-200 text-slate-400 cursor-not-allowed' }}">
                <span wire:loading wire:target="search">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search">Searching…</span>
            </button>
        </div>
    </div>

    @error('selectedClusterId')
        <p class="mt-2 text-xs text-rose-600 font-medium">{{ $message }}</p>
    @enderror
    @error('selectedUnitId')
        <p class="mt-2 text-xs text-rose-600 font-medium">{{ $message }}</p>
    @enderror
</div>

{{-- ════════════════════════════════════════════════════════════
     ADD CLUSTER MODAL
════════════════════════════════════════════════════════════ --}}
<div x-data="{ show: false }"
     x-on:open-add-cluster.window="show = true"
     x-show="show"
     x-cloak
     class="modal-backdrop"
     @click.self="show = false">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-sm" x-show="show" x-transition>
        <div class="flex items-center justify-between px-6 py-4 bg-[#0F1F3D] rounded-t-2xl">
            <h2 class="text-white font-bold text-base" style="font-family:'Plus Jakarta Sans',sans-serif">
                + Add New Cluster
            </h2>
            <button @click="show = false" class="text-slate-400 hover:text-white p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Cluster Name</label>
                <input wire:model="newClusterName" type="text" placeholder="e.g. MERIDIAN" class="form-input uppercase">
                @error('newClusterName')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Location (opt.)</label>
                <input wire:model="newClusterLocation" type="text" placeholder="e.g. Area Timur, Blok M" class="form-input">
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            <button @click="show = false" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">Cancel</button>
            <button wire:click="saveNewCluster" @click="show = false"
                    class="px-5 py-2 bg-[#0F1F3D] hover:bg-[#1a3560] text-white text-sm font-semibold rounded-lg shadow transition-colors">
                Save Cluster
            </button>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     RESULT PANEL
════════════════════════════════════════════════════════════ --}}

@if (!$searched)
    {{-- Empty state --}}
    <div class="bg-white rounded-2xl border border-dashed border-slate-200 py-20 text-center">
        <div class="text-5xl mb-3 select-none">🔍</div>
        <p class="text-slate-500 font-semibold">Select a Cluster and Unit above, then hit Search</p>
        <p class="text-slate-400 text-sm mt-1">The full installment ledger and NIS audit rows will appear here.</p>
    </div>

@elseif ($searched && $summary)

    <div wire:key="dash-result-{{ $summary->unit->id }}">

        {{-- ── UNIT DETAIL CARD ─────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-4">

            <div class="bg-[#0F1F3D] px-6 py-5 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-amber-400 text-[10px] font-bold uppercase tracking-widest mb-1">
                        {{ $summary->unit->cluster->name }} · Unit {{ $summary->unit->unit_label }}
                    </p>
                    <h2 class="text-white font-bold text-2xl" style="font-family:'Plus Jakarta Sans',sans-serif">
                        {{ $summary->unit->activeBuyer?->name ?? 'No Buyer Assigned' }}
                    </h2>
                    @if ($summary->unit->activeBuyer?->contract_date)
                        <p class="text-slate-400 text-xs mt-0.5">
                            Contract: {{ $summary->unit->activeBuyer->contract_date->format('d M Y') }}
                        </p>
                    @endif
                </div>
                <div class="text-right space-y-1.5 flex-shrink-0">
                    @php
                        $hc = $summary->hasIssues()
                            ? 'bg-amber-400 text-[#0F1F3D]'
                            : ($summary->hasPayments() ? 'bg-emerald-400 text-white' : 'bg-slate-600 text-slate-300');
                    @endphp
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $hc }}">
                        {{ $summary->healthBadge() }}
                    </div>
                    @if ($summary->isLandCleared)
                        <div class="block text-[10px] text-amber-400 font-bold uppercase tracking-widest">✦ Land Account Cleared</div>
                    @else
                        <div class="block text-[10px] text-slate-400 font-num">
                            Kavling headroom: Rp {{ number_format($summary->remainingHeadroom, 0, ',', '.') }}
                        </div>
                    @endif
                    <div class="block">
                        <a href="{{ route('reconciliation.index') }}"
                           class="text-[10px] text-slate-400 hover:text-amber-400 underline transition-colors">
                            Edit unit data →
                        </a>
                    </div>
                </div>
            </div>

            <div class="px-6 py-5">
                {{-- Identity --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-x-8 gap-y-3 mb-5">
                    <div>
                        <p class="label-xs">TAHAP</p>
                        @if ($summary->unit->tahap)
                            <p class="font-bold text-indigo-700 text-sm mt-0.5">{{ $summary->unit->tahap }}</p>
                        @else
                            <p class="text-slate-300 text-sm mt-0.5">—</p>
                        @endif
                    </div>
                    <div><p class="label-xs">NAME</p><p class="font-bold text-slate-900 text-sm mt-0.5">{{ $summary->unit->activeBuyer?->name ?? '—' }}</p></div>
                    <div><p class="label-xs">TIPE</p><p class="font-bold text-slate-900 text-sm mt-0.5">{{ $summary->unit->house_type ?? '—' }}</p></div>
                    <div><p class="label-xs">LB</p><p class="font-bold text-slate-900 text-sm font-num mt-0.5">{{ $summary->unit->luas_bangunan !== null ? $summary->unit->luas_bangunan.' m²' : '—' }}</p></div>
                    <div><p class="label-xs">LT</p><p class="font-bold text-slate-900 text-sm font-num mt-0.5">{{ $summary->unit->luas_tanah }} m²</p></div>
                </div>

                <div class="border-t border-slate-100 mb-5"></div>

                {{-- Financials --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-4">
                    <div>
                        <p class="label-xs">Harga Penjualan</p>
                        @if ($summary->unit->harga_penjualan !== null)
                            <p class="val-num">Rp {{ number_format($summary->unit->harga_penjualan, 0, ',', '.') }}</p>
                        @else
                            <p class="text-slate-300 text-sm mt-0.5">Not set yet</p>
                        @endif
                    </div>
                    <div>
                        <p class="label-xs">Pembayaran</p>
                        @if ($summary->unit->payment_type)
                            <p class="font-semibold text-slate-800 text-sm mt-0.5">
                                {{ $summary->unit->payment_type }}
                                @if ($summary->unit->payment_type !== 'Cash Keras' && $summary->unit->max_installments !== null)({{ $summary->unit->max_installments }}x)@endif
                            </p>
                        @else
                            <p class="text-slate-300 text-sm mt-0.5">Not set yet</p>
                        @endif
                    </div>
                    <div>
                        <p class="label-xs">Down Payment</p>
                        @if ($summary->unit->down_payment !== null)
                            <p class="val-num">Rp {{ number_format($summary->unit->down_payment, 0, ',', '.') }}</p>
                        @else
                            <p class="text-slate-300 text-sm mt-0.5">—</p>
                        @endif
                    </div>
                    <div>
                        <p class="label-xs">Kavling Cap</p>
                        <p class="font-num font-semibold text-amber-700 text-sm mt-0.5">
                            Rp {{ number_format($summary->kavlingValue, 0, ',', '.') }}
                        </p>
                        <div class="mt-1.5 kavling-bar-track w-28">
                            <div class="kavling-bar-fill {{ $summary->isLandCleared ? 'bg-amber-500' : 'bg-[#0F1F3D]' }}"
                                 style="width: {{ $summary->kavlingFillPct() }}%"></div>
                        </div>
                        <p class="text-[10px] text-slate-400 font-num mt-0.5">{{ $summary->kavlingFillPct() }}% used</p>
                    </div>
                    <div>
                        <p class="label-xs">Total Kavling</p>
                        <p class="font-num font-semibold text-sm mt-0.5 {{ $summary->isLandCleared ? 'text-amber-700' : 'text-slate-800' }}">
                            Rp {{ number_format($summary->cumulativeNis, 0, ',', '.') }}
                        </p>
                        <p class="text-[10px] text-slate-400">NIS share received so far</p>
                    </div>
                    <div>
                        <p class="label-xs">Sisa Kavling</p>
                        <p class="font-num font-semibold text-sm mt-0.5 {{ $summary->remainingHeadroom === 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                            Rp {{ number_format($summary->remainingHeadroom, 0, ',', '.') }}
                        </p>
                        <p class="text-[10px] text-slate-400">
                            @if ($summary->remainingHeadroom === 0)
                                ✦ Fully cleared
                            @else
                                NIS share still owed
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="label-xs">Total Angsuran</p>
                        <p class="val-num">Rp {{ number_format($summary->totalPenerimaan, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="label-xs">Sisa Angsuran</p>
                        <p class="font-num font-semibold text-sm mt-0.5 {{ $summary->sisaPenerimaan === 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                            Rp {{ number_format($summary->sisaPenerimaan, 0, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="label-xs">Angsuran Per Bulan</p>
                        @if ($summary->unit->angsuran_per_bulan !== null)
                            <p class="val-num">Rp {{ number_format($summary->unit->angsuran_per_bulan, 0, ',', '.') }}</p>
                        @else
                            <p class="text-slate-300 text-sm mt-0.5">Not set yet</p>
                        @endif
                    </div>
                    <div>
                        <p class="label-xs">Penerimaan Per Bulan</p>
                        <p class="font-num font-semibold text-[#0F1F3D] text-sm mt-0.5">
                            Rp {{ number_format($summary->unit->penerimaan_per_bulan, 0, ',', '.') }}
                        </p>
                        <p class="text-[10px] text-slate-400">NIS 30% of target</p>
                    </div>
                </div>

                {{-- Audit score strip --}}
                @if ($summary->hasPayments())
                <div class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap gap-2">
                    <span class="badge bg-emerald-100 text-emerald-800">✅ {{ $summary->correctCount }} Correct</span>
                    @if ($summary->underpayCount > 0)
                        <span class="badge bg-rose-100 text-rose-800">
                            ❌ {{ $summary->underpayCount }} Underpay
                            <span class="font-normal opacity-75">−Rp {{ number_format($summary->totalUnderpayAmount, 0, ',', '.') }}</span>
                        </span>
                    @endif
                    @if ($summary->overpayCount > 0)
                        <span class="badge bg-amber-100 text-amber-800">
                            ⚠️ {{ $summary->overpayCount }} Overpay
                            <span class="font-normal opacity-75">+Rp {{ number_format($summary->totalOverpayAmount, 0, ',', '.') }}</span>
                        </span>
                    @endif
                    @if ($summary->hasOverflowError)
                        <span class="badge bg-red-100 text-red-800">🚨 Ceiling Overflow Error</span>
                    @endif
                    <span class="badge bg-slate-100 text-slate-600">{{ $summary->paidCount }}/{{ $summary->unit->max_installments ?? '—' }} paid</span>
                </div>
                @endif
            </div>
        </div>

        {{-- ── ANGSURAN LEDGER + MODALS                              --}}
        {{-- manage-payments owns ALL ledger rows + edit/delete btns  --}}
        {{-- + add/edit/delete modals. Nothing duplicated above.       --}}
        @livewire('manage-payments', ['unit' => $summary->unit], key('mp-'.$summary->unit->id))

    </div>
@endif

</div>

<style>
    .label-xs { font-size: 10px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.08em; }
    .val-num   { font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #1E293B; font-size: 0.875rem; margin-top: 2px; }
</style>