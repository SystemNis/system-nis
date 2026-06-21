<div>

{{-- Flash --}}
@if (session()->has('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)"
         x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0"
         class="fixed top-16 right-4 z-50 bg-emerald-600 text-white text-sm font-semibold
                px-4 py-3 rounded-lg shadow-xl flex items-center gap-2 max-w-sm">
        ✅ {{ session('success') }}
    </div>
@endif

{{-- ── TAB BAR ──────────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 mb-4 w-fit shadow-sm">
    <button wire:click="switchTab('units')"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors
                   {{ $activeTab === 'units'
                      ? 'bg-[#0F1F3D] text-white shadow-sm'
                      : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100' }}">
        🏠 Deleted Units
        @if ($deletedUnits->total() > 0)
            <span class="ml-1.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full
                         {{ $activeTab === 'units' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700' }}">
                {{ $deletedUnits->total() }}
            </span>
        @endif
    </button>
    <button wire:click="switchTab('payments')"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors
                   {{ $activeTab === 'payments'
                      ? 'bg-[#0F1F3D] text-white shadow-sm'
                      : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100' }}">
        💳 Deleted Payments
        @if ($deletedPayments->total() > 0)
            <span class="ml-1.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full
                         {{ $activeTab === 'payments' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700' }}">
                {{ $deletedPayments->total() }}
            </span>
        @endif
    </button>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     DELETED UNITS TAB
════════════════════════════════════════════════════════════════════════ --}}
@if ($activeTab === 'units')
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

    @if ($deletedUnits->isEmpty())
        <div class="py-20 text-center">
            <div class="text-4xl mb-3">🗂️</div>
            <p class="text-slate-500 font-semibold">No deleted units</p>
            <p class="text-slate-400 text-sm mt-1">Units you delete will appear here for recovery.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-[#0F1F3D] text-left">
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Unit</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Cluster</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Type</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">LT/LB</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Harga</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Payment</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Deleted At</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($deletedUnits as $unit)
                    <tr class="hover:bg-rose-50/40 transition-colors group">
                        <td class="px-4 py-3">
                            <span class="font-num font-bold text-slate-700 line-through decoration-rose-300">
                                {{ $unit->block }}-{{ $unit->unit_number }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $unit->cluster?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $unit->house_type }}</td>
                        <td class="px-4 py-3 font-num text-xs text-slate-600">{{ $unit->luas_tanah }}/{{ $unit->luas_bangunan }}</td>
                        <td class="px-4 py-3 font-num text-xs text-slate-600">
                            Rp {{ number_format($unit->harga_penjualan, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            {{ $unit->payment_type }}
                            @if($unit->payment_type !== 'Cash Keras')({{ $unit->max_installments }}x)@endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $unit->deleted_at->format('d M Y, H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                {{-- Restore --}}
                                <button wire:click="confirmRestore({{ $unit->id }}, 'unit')"
                                        title="Restore unit"
                                        class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold
                                               text-emerald-700 bg-emerald-50 border border-emerald-200
                                               rounded-lg hover:bg-emerald-100 transition-colors">
                                    ↩ Restore
                                </button>
                                {{-- Permanent delete --}}
                                <button wire:click="confirmForceDelete({{ $unit->id }}, 'unit')"
                                        title="Permanently delete"
                                        class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold
                                               text-red-700 bg-red-50 border border-red-200
                                               rounded-lg hover:bg-red-100 transition-colors">
                                    🗑 Delete Forever
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($deletedUnits->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
                {{ $deletedUnits->links() }}
            </div>
        @endif
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════
     DELETED PAYMENTS TAB
════════════════════════════════════════════════════════════════════════ --}}
@if ($activeTab === 'payments')
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

    @if ($deletedPayments->isEmpty())
        <div class="py-20 text-center">
            <div class="text-4xl mb-3">💳</div>
            <p class="text-slate-500 font-semibold">No deleted payments</p>
            <p class="text-slate-400 text-sm mt-1">Deleted installment payments will appear here.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-[#0F1F3D] text-left">
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Inst. #</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Unit</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Buyer</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Amount</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Status</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Payment Date</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">NIS Share</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest">Deleted At</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-slate-300 uppercase tracking-widest text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($deletedPayments as $payment)
                    <tr class="hover:bg-rose-50/40 transition-colors group">
                        <td class="px-4 py-3">
                            <span class="font-num font-bold text-slate-400 line-through decoration-rose-300">
                                #{{ $payment->installment_number }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($payment->unit)
                                <span class="font-semibold text-slate-700">{{ $payment->unit->block }}-{{ $payment->unit->unit_number }}</span>
                                <span class="text-slate-400 ml-1">{{ $payment->unit->cluster?->name }}</span>
                            @else
                                <span class="text-slate-400 italic">Unit deleted</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            {{ $payment->buyer?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 font-num text-xs text-slate-700">
                            Rp {{ number_format($payment->actual_amount, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $sc = match($payment->payment_status) {
                                    'correct'  => 'bg-emerald-100 text-emerald-700',
                                    'underpay' => 'bg-rose-100 text-rose-700',
                                    'overpay'  => 'bg-amber-100 text-amber-700',
                                    default    => 'bg-slate-100 text-slate-500',
                                };
                            @endphp
                            <span class="badge {{ $sc }} text-[10px]">{{ $payment->status_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $payment->payment_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-num text-xs text-slate-600">
                            Rp {{ number_format($payment->actual_nis_share, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $payment->deleted_at->format('d M Y, H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="confirmRestore({{ $payment->id }}, 'payment')"
                                        title="Restore payment"
                                        class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold
                                               text-emerald-700 bg-emerald-50 border border-emerald-200
                                               rounded-lg hover:bg-emerald-100 transition-colors">
                                    ↩ Restore
                                </button>
                                <button wire:click="confirmForceDelete({{ $payment->id }}, 'payment')"
                                        title="Permanently delete"
                                        class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold
                                               text-red-700 bg-red-50 border border-red-200
                                               rounded-lg hover:bg-red-100 transition-colors">
                                    🗑 Delete Forever
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($deletedPayments->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
                {{ $deletedPayments->links() }}
            </div>
        @endif
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════
     RESTORE CONFIRM MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showRestoreConfirm)
<div class="modal-backdrop">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">↩</span>
            </div>
            <h3 class="font-bold text-slate-800 text-lg mb-1">Restore {{ $actionLabel }}?</h3>
            <p class="text-slate-500 text-sm">
                This record will be brought back to the active view.
                @if ($actionType === 'unit')
                    All associated payments will also be restored and the unit will be recomputed.
                @else
                    The unit's cumulative NIS totals will be recomputed automatically.
                @endif
            </p>
        </div>
        <div class="flex gap-3 px-6 pb-6">
            <button wire:click="cancelAction"
                    class="flex-1 px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                Cancel
            </button>
            <button wire:click="restore"
                    class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg shadow transition-colors">
                Yes, Restore
            </button>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════
     PERMANENT DELETE CONFIRM MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showForceDeleteConfirm)
<div class="modal-backdrop">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="font-bold text-slate-800 text-lg mb-1">Permanently Delete?</h3>
            <p class="text-slate-500 text-sm mb-3">
                <span class="font-semibold text-slate-700">{{ $actionLabel }}</span> will be
                <span class="font-bold text-red-600">erased forever</span> from the database.
                @if ($actionType === 'unit')
                    All payments and buyer records for this unit will also be destroyed.
                @endif
                This cannot be undone.
            </p>
            <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-800 font-semibold">
                ⚠️ There is no recovery from this action.
            </div>
        </div>
        <div class="flex gap-3 px-6 pb-6">
            <button wire:click="cancelAction"
                    class="flex-1 px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                Cancel
            </button>
            <button wire:click="forceDelete"
                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow transition-colors">
                Delete Forever
            </button>
        </div>
    </div>
</div>
@endif

</div>
