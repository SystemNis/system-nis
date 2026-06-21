<div x-data="{}" x-on:shortcut-next-slot.window="$wire.openAddPaymentModal()">
{{-- manage-payments.blade.php — v3 CLEAN — single ANGSURAN ledger only --}}
{{-- "W" keyboard shortcut (handled globally in audit-dashboard.blade.php) dispatches
     'shortcut-next-slot' on window; we catch it here and call openAddPaymentModal()
     with no args, which auto-picks the next unrecorded installment slot. --}}
{{-- Flash --}}
@if (session()->has('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0"
         class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium
                px-4 py-3 rounded-lg flex items-center gap-2">
        ✅ {{ session('success') }}
    </div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════
     ANGSURAN LEDGER — owned here so wire:click works natively
════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-4">

    {{-- Ledger header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50 rounded-t-2xl">
        <div>
            <h3 class="font-bold text-[#0F1F3D]" style="font-family:'Plus Jakarta Sans',sans-serif">ANGSURAN</h3>
            <p class="text-slate-400 text-xs">
                {{ $summary->paidCount }} of {{ $summary->unit->max_installments }} installments recorded
            </p>
        </div>
        <button wire:click="openAddPaymentModal()"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-[#0F1F3D] hover:bg-[#1a3560]
                       text-white text-xs font-semibold rounded-lg shadow transition-colors">
            <span class="text-amber-400">＋</span> Record Payment
        </button>
    </div>

    {{-- Ledger rows --}}
    <div class="divide-y divide-slate-50">
        @foreach ($summary->ledgerSlots as $slot => $payment)
        <div class="px-6 py-3.5 transition-colors {{ $payment ? 'hover:bg-slate-50' : '' }} group">

            @if ($payment)
                {{-- ── PAID SLOT ─────────────────────────────────────────── --}}
                {{-- 3-column: [badge] [content] [action buttons]             --}}
                {{-- Buttons are NOT inside flex-wrap so ml-auto works.       --}}
                <div class="flex items-start gap-3">

                    {{-- Col 1: Slot badge --}}
                    @php
                        $slotRing = match($payment->payment_status) {
                            'correct'  => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                            'underpay' => 'bg-rose-100 text-rose-700 ring-rose-200',
                            'overpay'  => 'bg-amber-100 text-amber-700 ring-amber-200',
                            default    => 'bg-slate-100 text-slate-500 ring-slate-200',
                        };
                    @endphp
                    <div class="w-8 h-8 rounded-full ring-2 flex items-center justify-center
                                flex-shrink-0 text-[11px] font-bold mt-0.5 {{ $slotRing }}">
                        {{ $slot }}
                    </div>

                    {{-- Col 2: All text content --}}
                    <div class="flex-1 min-w-0">

                        {{-- Line 1: amount — date — status — variance --}}
                        {{-- NOTE: no buttons here, so flex-wrap is safe --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-num font-bold text-slate-900 text-sm">
                                Rp {{ number_format($payment->actual_amount, 0, ',', '.') }}
                            </span>
                            <span class="text-slate-300 text-xs">—</span>
                            <span class="text-slate-600 text-xs font-medium">{{ $payment->formatted_date }}</span>

                            @php
                                $ic = match($payment->payment_status) {
                                    'correct'  => 'text-emerald-700',
                                    'underpay' => 'text-rose-700',
                                    'overpay'  => 'text-amber-700',
                                    default    => 'text-slate-500',
                                };
                            @endphp
                            <span class="text-xs font-bold {{ $ic }}">{{ $payment->status_label }}</span>

                            @if ($payment->variance_note)
                                <span class="text-xs font-medium {{ $payment->payment_status === 'underpay' ? 'text-rose-600' : 'text-amber-600' }}">
                                    {{ $payment->variance_note }}
                                </span>
                            @endif
                        </div>

                        {{-- Line 2: NIS Audit Row --}}
                        <div class="mt-1.5 inline-flex items-center flex-wrap gap-1
                                    text-[11px] font-num bg-slate-50 border border-slate-100
                                    rounded-lg px-3 py-1.5">
                            <span class="text-slate-400 font-semibold">NIS Share</span>
                            <span class="text-slate-300">→</span>
                            <span class="text-slate-500">
                                Supposed to receive:
                                <span class="font-semibold text-slate-700">
                                    Rp {{ number_format($payment->supposed_nis_share, 0, ',', '.') }}
                                </span>
                            </span>
                            <span class="text-slate-200 px-1">|</span>
                            <span class="text-slate-500">
                                Actually received:
                                <span class="font-semibold {{ $payment->actual_nis_share >= $payment->supposed_nis_share ? 'text-emerald-700' : 'text-rose-700' }}">
                                    Rp {{ number_format($payment->actual_nis_share, 0, ',', '.') }}
                                </span>
                            </span>
                            @if ($payment->capped_nis_share < $payment->actual_nis_share)
                                <span class="text-slate-200 px-1">|</span>
                                <span class="text-amber-700 font-semibold">
                                    ✦ Capped: Rp {{ number_format($payment->capped_nis_share, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>

                        {{-- Line 3: Special flags --}}
                        @if ($payment->land_cleared_on_this_payment)
                            <div class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold
                                        text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1">
                                ✦ Land account fully cleared on this payment
                            </div>
                        @endif
                        @if ($payment->is_ceiling_overflow_error)
                            <div class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold
                                        text-red-800 bg-red-50 border border-red-200 rounded-lg px-3 py-1">
                                🚨 Ceiling Overflow Error — Kavling already cleared, NIS share Rp 0
                            </div>
                        @endif
                    </div>{{-- end Col 2 --}}

                    {{-- Col 3: Action buttons — fixed column, NOT inside flex-wrap --}}
                    {{-- opacity-0 / group-hover:opacity-100 works because this div  --}}
                    {{-- is a direct child of the group container row.               --}}
                    <div class="flex-shrink-0 flex items-center gap-1 pt-0.5
                                opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                        <button wire:click="openEditPaymentModal({{ $payment->id }})"
                                title="Edit installment #{{ $slot }}"
                                class="p-1.5 rounded-lg hover:bg-blue-50 text-slate-300
                                       hover:text-blue-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button wire:click="confirmDeletePayment({{ $payment->id }})"
                                title="Delete installment #{{ $slot }}"
                                class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-300
                                       hover:text-rose-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>{{-- end Col 3 --}}

                </div>{{-- end 3-col row --}}

            @else
                {{-- ── EMPTY SLOT — fully clickable, always visible "+ Record" ── --}}
                <button wire:click="openAddPaymentModal({{ $slot }})"
                        class="w-full flex items-center gap-4 text-left hover:bg-slate-50 rounded-lg
                               -mx-2 px-2 py-0.5 transition-colors cursor-pointer">
                    <div class="w-8 h-8 rounded-full ring-2 ring-slate-100 bg-slate-50
                                flex items-center justify-center flex-shrink-0
                                text-[11px] font-bold text-slate-300">
                        {{ $slot }}
                    </div>
                    <span class="text-slate-300 text-sm font-medium">Not yet recorded</span>
                    <span class="ml-auto flex items-center gap-1 text-[11px] font-semibold
                                 text-slate-400 bg-white border border-slate-200 rounded-lg
                                 px-2.5 py-1 hover:border-[#0F1F3D] hover:text-[#0F1F3D] transition-colors">
                        <span class="text-amber-500 text-sm leading-none">＋</span> Record
                    </span>
                </button>
            @endif

        </div>
        @endforeach
    </div>

    {{-- Ledger footer --}}
    @if ($summary->hasPayments())
    <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs font-num text-slate-500">
            <span>
                Cumulative NIS land share:
                <span class="font-semibold text-[#0F1F3D]">Rp {{ number_format($summary->cumulativeNis, 0, ',', '.') }}</span>
            </span>
            <span>
                Kavling cap:
                <span class="font-semibold text-amber-700">Rp {{ number_format($summary->kavlingValue, 0, ',', '.') }}</span>
            </span>
            @if (!$summary->isLandCleared)
                <span>
                    Remaining headroom:
                    <span class="font-semibold text-slate-700">Rp {{ number_format($summary->remainingHeadroom, 0, ',', '.') }}</span>
                </span>
            @else
                <span class="font-semibold text-amber-700">✦ Land account fully settled</span>
            @endif
        </div>
    </div>
    @endif
</div>


{{-- ════════════════════════════════════════════════════════════════════════
     ADD PAYMENT MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showAddPaymentModal)
<div class="modal-backdrop"
     wire:click.self="closeAddPaymentModal"
     x-data
     x-init="$nextTick(() => { })"
     @keydown.escape.window="$wire.closeAddPaymentModal()"
     @keydown.enter.window="if (!$event.target.closest('textarea')) { $event.preventDefault(); $wire.savePayment(); }">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-md">

        <div class="flex items-center justify-between px-6 py-4 bg-[#0F1F3D] rounded-t-2xl">
            <div>
                <h2 class="text-white font-bold text-base" style="font-family:'Plus Jakarta Sans',sans-serif">
                    ＋ Record Installment Payment
                </h2>
                <p class="text-slate-400 text-xs mt-0.5">
                    Unit {{ $summary->unit->unit_label }} — {{ $summary->unit->activeBuyer?->name ?? 'No buyer' }}
                </p>
            </div>
            <button wire:click="closeAddPaymentModal" class="text-slate-400 hover:text-white p-1 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">

            {{-- Reference strip --}}
            <div class="bg-slate-50 rounded-lg px-4 py-3 text-xs font-num text-slate-600 flex flex-wrap gap-x-4 gap-y-1">
                <span>
                    <span class="font-semibold text-slate-700">Target/slot:</span>
                    Rp {{ number_format($summary->unit->angsuran_per_bulan, 0, ',', '.') }}
                </span>
                <span>
                    <span class="font-semibold text-slate-700">NIS 30%:</span>
                    Rp {{ number_format($summary->unit->penerimaan_per_bulan, 0, ',', '.') }}
                </span>
                <span>
                    <span class="font-semibold text-slate-700">Kavling left:</span>
                    @if ($summary->isLandCleared)
                        <span class="text-amber-700 font-bold">✦ Cleared</span>
                    @else
                        Rp {{ number_format($summary->remainingHeadroom, 0, ',', '.') }}
                    @endif
                </span>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">
                    Installment Number
                    @if ($add_installment_number)
                        <span class="text-emerald-600 font-normal normal-case tracking-normal">— slot selected</span>
                    @endif
                </label>
                <input wire:model="add_installment_number"
                       type="number" min="1" max="{{ $summary->unit->max_installments }}"
                       placeholder="e.g. 5"
                       @if($add_installment_number) readonly @endif
                       class="form-input font-num {{ $add_installment_number ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : '' }}">
                @error('add_installment_number')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            @include('livewire.partials.money-input', [
                'wireModel'   => 'add_actual_amount',
                'label'       => 'Actual Amount Paid (Rp)',
                'placeholder' => (string) $summary->unit->angsuran_per_bulan,
                'live'        => true,
                'inputId'     => 'add-amount-input',
                'autofocus'   => true,
            ])

            <div>
                {{-- Live preview --}}
                @if ($add_actual_amount && is_numeric($add_actual_amount) && (int)$add_actual_amount > 0)
                    @php
                        $pa  = (int)$add_actual_amount;
                        $pt  = $summary->unit->angsuran_per_bulan;
                        $pv  = $pa - $pt;
                        $pn  = (int)round($pa * 0.30);
                        $ph  = $summary->remainingHeadroom;
                        $pcp = min($pn, $ph);
                        [$plabel, $pstyle] = match(true) {
                            $pv > 0  => ['⚠️ Overpay',  'text-amber-700 bg-amber-50 border-amber-200'],
                            $pv < 0  => ['❌ Underpay', 'text-rose-700 bg-rose-50 border-rose-200'],
                            default  => ['✅ Correct',   'text-emerald-700 bg-emerald-50 border-emerald-200'],
                        };
                    @endphp
                    <div class="mt-2 border rounded-xl px-4 py-3 text-xs font-num space-y-1 {{ $pstyle }}">
                        <div class="font-bold text-sm">{{ $plabel }}
                            @if ($pv !== 0)
                                <span class="font-normal">
                                    {{ $pv > 0 ? '+' : '' }}Rp {{ number_format($pv, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>
                        <div>Supposed NIS: <span class="font-semibold">Rp {{ number_format((int)round($pt * 0.30), 0, ',', '.') }}</span></div>
                        <div>Actual NIS: <span class="font-semibold">Rp {{ number_format($pn, 0, ',', '.') }}</span></div>
                        @if ($pcp < $pn)
                            <div class="text-amber-800 font-semibold">
                                ✦ Will be capped at: Rp {{ number_format($pcp, 0, ',', '.') }} (kavling ceiling)
                            </div>
                        @endif
                        @if ($ph === 0)
                            <div class="text-red-800 font-bold">
                                🚨 Kavling already cleared — this payment generates Rp 0 NIS land share
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">
                        Payment Date (opt.)
                    </label>
                    <input wire:model="add_payment_date" type="date" class="form-input">
                    @error('add_payment_date')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">
                        Due Date (opt.)
                    </label>
                    <input wire:model="add_due_date" type="date" class="form-input">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">
                    Notes (opt.)
                </label>
                <textarea wire:model="add_notes" rows="2"
                          class="form-input w-full resize-none"
                          placeholder="Any notes from APP…"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            <button wire:click="closeAddPaymentModal"
                    class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">
                Cancel
            </button>
            <button wire:click="savePayment" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-[#0F1F3D] hover:bg-[#1a3560] text-white text-sm font-semibold
                           rounded-lg shadow transition-colors disabled:opacity-60 flex items-center gap-2">
                <span wire:loading wire:target="savePayment">
                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                Record Payment
            </button>
        </div>
    </div>
</div>
@endif


{{-- ════════════════════════════════════════════════════════════════════════
     EDIT PAYMENT MODAL
════════════════════════════════════════════════════════════════════════ --}}
@if ($showEditPaymentModal)
<div class="modal-backdrop"
     wire:click.self="closeEditPaymentModal"
     @keydown.escape.window="$wire.closeEditPaymentModal()"
     @keydown.enter.window="if (!$event.target.closest('textarea')) { $event.preventDefault(); $wire.savePaymentEdit(); }">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-md">

        <div class="flex items-center justify-between px-6 py-4 bg-blue-900 rounded-t-2xl">
            <div>
                <h2 class="text-white font-bold text-base" style="font-family:'Plus Jakarta Sans',sans-serif">
                    ✏️ Edit Payment
                </h2>
                <p class="text-blue-300 text-xs mt-0.5">All derived audit values recompute on save</p>
            </div>
            <button wire:click="closeEditPaymentModal" class="text-blue-300 hover:text-white p-1 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800 font-semibold">
                ⚠️ Changing the amount cascades — all subsequent installments are recomputed to maintain correct cumulative NIS totals.
            </div>
            @include('livewire.partials.money-input', [
                'wireModel' => 'edit_actual_amount',
                'label'     => 'Actual Amount Paid (Rp)',
                'inputId'   => 'edit-amount-input',
                'autofocus' => true,
            ])
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">
                    Payment Date (opt.)
                </label>
                <input wire:model="edit_payment_date" type="date" class="form-input">
                @error('edit_payment_date')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Notes</label>
                <textarea wire:model="edit_notes" rows="2" class="form-input w-full resize-none"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            <button wire:click="closeEditPaymentModal"
                    class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">Cancel</button>
            <button wire:click="savePaymentEdit" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold
                           rounded-lg shadow transition-colors disabled:opacity-60 flex items-center gap-2">
                <span wire:loading wire:target="savePaymentEdit">
                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                Save & Recompute
            </button>
        </div>
    </div>
</div>
@endif


{{-- ════════════════════════════════════════════════════════════════════════
     DELETE PAYMENT CONFIRM
════════════════════════════════════════════════════════════════════════ --}}
@if ($showDeletePaymentConfirm)
<div class="modal-backdrop">
    <div class="modal-card bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-rose-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="font-bold text-slate-800 text-lg mb-1">Delete {{ $deletingPaymentLabel }}?</h3>
            <p class="text-slate-500 text-sm">
                The payment will be soft-deleted and all subsequent installments recomputed.
                It can be restored from the History Log page.
            </p>
        </div>
        <div class="flex gap-3 px-6 pb-6">
            <button wire:click="cancelDeletePayment"
                    class="flex-1 px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium
                           rounded-lg hover:bg-slate-50 transition-colors">
                Keep It
            </button>
            <button wire:click="deletePayment"
                    class="flex-1 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm
                           font-semibold rounded-lg shadow transition-colors">
                Delete & Recompute
            </button>
        </div>
    </div>
</div>
@endif

</div>