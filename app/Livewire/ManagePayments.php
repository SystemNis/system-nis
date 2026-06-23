<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Unit;
use App\Services\Payment\PaymentService;
use Livewire\Component;

/**
 * ManagePayments — Livewire Component
 * ═════════════════════════════════════
 * Handles recording, editing, and deleting individual installment payments
 * for a single unit. Designed to be embedded inside the Dashboard deep-dive
 * view but is self-contained and re-usable.
 *
 * Usage in Blade:
 *   @livewire('manage-payments', ['unit' => $unit])
 *
 * NOTE on dates: payment_date is OPTIONAL. APP frequently reports a payment
 * before the exact date is confirmed on paper — forcing a date at entry
 * time blocked legitimate audit entries, so it's now nullable end-to-end
 * (migration, model cast, validation here).
 *
 * NOTE on amounts: the "JT" (juta/million) multiplier toggle lives entirely
 * in Alpine.js on the frontend (manage-payments.blade.php). It rewrites the
 * value of the underlying wire:model-bound input before Livewire ever sees
 * it, so no server-side change was needed for that feature — what arrives
 * here is always the final Rupiah integer.
 */
class ManagePayments extends Component
{
    public Unit $unit;

    // ── Add payment form ───────────────────────────────────────────────────
    public bool   $showAddPaymentModal    = false;
    public string $add_installment_number = '';
    public string $add_actual_amount      = '';
    public string $add_payment_date       = '';
    public string $add_due_date           = '';
    public string $add_notes              = '';

    // ── Edit payment form ──────────────────────────────────────────────────
    public bool   $showEditPaymentModal  = false;
    public ?int   $editingPaymentId      = null;
    public string $edit_actual_amount    = '';
    public string $edit_payment_date     = '';
    public string $edit_notes            = '';

    // ── Delete confirm ─────────────────────────────────────────────────────
    public bool   $showDeletePaymentConfirm = false;
    public ?int   $deletingPaymentId        = null;
    public string $deletingPaymentLabel     = '';

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function mount(Unit $unit): void
    {
        $this->unit = $unit;
    }

    /**
     * Returns the next unrecorded installment slot number for this unit,
     * or null if every slot 1..max_installments is already paid, OR if
     * the unit has no installment plan defined yet (max_installments is
     * null — now possible since Financial Terms are optional at creation).
     *
     * Exposed publicly so the "W" keyboard shortcut (handled in Alpine on
     * the dashboard) can call it via $wire.nextEmptySlot() and immediately
     * open the add-payment modal for that exact slot — zero clicks needed.
     */
    public function nextEmptySlot(): ?int
    {
        if ($this->unit->max_installments === null) {
            return null;
        }

        $paidSlots = Payment::where('unit_id', $this->unit->id)
            ->whereNull('deleted_at')
            ->pluck('installment_number')
            ->toArray();

        for ($i = 1; $i <= $this->unit->max_installments; $i++) {
            if (!in_array($i, $paidSlots, true)) {
                return $i;
            }
        }

        return null; // fully paid
    }

    /**
     * Whether this unit has a complete enough financial plan to record
     * payments against. Required: angsuran_per_bulan (the per-installment
     * target) and max_installments (the slot count ceiling).
     */
    public function hasInstallmentPlan(): bool
    {
        return $this->unit->angsuran_per_bulan !== null
            && $this->unit->max_installments !== null;
    }

    // ── ADD ────────────────────────────────────────────────────────────────

    public function openAddPaymentModal(?int $slotNumber = null): void
    {
        if (!$this->hasInstallmentPlan()) {
            session()->flash('error',
                "Unit {$this->unit->unit_label} has no installment plan yet. "
                . "Set Harga Penjualan and Max Installments in Reconciliation first."
            );
            return;
        }

        $this->resetAddPaymentForm();

        if ($slotNumber) {
            // Explicit slot clicked (row click, or "W" shortcut) — lock it in
            $this->add_installment_number = (string) $slotNumber;
        } else {
            // "Record Payment" header button, or "W" shortcut with no args —
            // auto-pick the next empty slot.
            $next = $this->nextEmptySlot();

            if ($next === null) {
                // All installments already recorded — nothing to open.
                session()->flash('success', "All {$this->unit->max_installments} installments for {$this->unit->unit_label} are already recorded.");
                return;
            }

            $this->add_installment_number = (string) $next;
        }

        $this->showAddPaymentModal = true;
    }

    public function closeAddPaymentModal(): void
    {
        $this->showAddPaymentModal = false;
        $this->resetAddPaymentForm();
        $this->resetErrorBag();
    }

    public function savePayment(PaymentService $service): void
    {
        if (!$this->hasInstallmentPlan()) {
            $this->addError('add_actual_amount', 'This unit has no installment plan defined yet.');
            return;
        }

        $validated = $this->validate([
            'add_installment_number' => 'required|integer|min:1|max:' . $this->unit->max_installments,
            'add_actual_amount'      => 'required|integer|min:1',
            'add_payment_date'       => 'nullable|date',
            'add_due_date'           => 'nullable|date',
            'add_notes'              => 'nullable|string|max:500',
        ], [], [
            'add_installment_number' => 'Installment Number',
            'add_actual_amount'      => 'Amount Paid',
            'add_payment_date'       => 'Payment Date',
        ]);

        $buyer = $this->unit->activeBuyer;

        if (!$buyer) {
            $this->addError('add_actual_amount', 'This unit has no active buyer. Add a buyer first.');
            return;
        }

        try {
            $result = $service->record($this->unit, $buyer, [
                'installment_number' => (int) $validated['add_installment_number'],
                'actual_amount'      => (int) $validated['add_actual_amount'],
                'payment_date'       => $validated['add_payment_date'] ?: null,
                'due_date'           => $validated['add_due_date'] ?: null,
                'notes'              => $validated['add_notes'] ?: null,
                'reported_by'        => 'NIS-SYSTEM',
            ]);

            $this->closeAddPaymentModal();
            $this->dispatch('payment-recorded', paymentId: $result->payment->id);
            session()->flash('success',
                "Installment #{$result->payment->installment_number} recorded — {$result->statusLabel}"
            );

        } catch (\InvalidArgumentException $e) {
            $this->addError('add_installment_number', $e->getMessage());
        }
    }

    // ── EDIT ───────────────────────────────────────────────────────────────

    public function openEditPaymentModal(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        $this->editingPaymentId   = $payment->id;
        $this->edit_actual_amount = (string) $payment->actual_amount;
        $this->edit_payment_date  = $payment->payment_date
            ? $payment->payment_date->format('Y-m-d')
            : '';
        $this->edit_notes         = $payment->notes ?? '';

        $this->showEditPaymentModal = true;
    }

    public function closeEditPaymentModal(): void
    {
        $this->showEditPaymentModal = false;
        $this->editingPaymentId    = null;
        $this->resetErrorBag();
    }

    public function savePaymentEdit(PaymentService $service): void
    {
        $validated = $this->validate([
            'edit_actual_amount' => 'required|integer|min:1',
            'edit_payment_date'  => 'nullable|date',
            'edit_notes'         => 'nullable|string|max:500',
        ], [], [
            'edit_actual_amount' => 'Amount Paid',
            'edit_payment_date'  => 'Payment Date',
        ]);

        $payment = Payment::findOrFail($this->editingPaymentId);

        $service->updatePayment($payment, [
            'actual_amount' => (int) $validated['edit_actual_amount'],
            'payment_date'  => $validated['edit_payment_date'] ?: null,
            'notes'         => $validated['edit_notes'] ?? null,
        ]);

        // Reload unit after recompute
        $this->unit->refresh();

        $this->closeEditPaymentModal();
        $this->dispatch('payment-updated');
        session()->flash('success', "Installment #{$payment->installment_number} updated and all derived values recomputed.");
    }

    // ── DELETE ─────────────────────────────────────────────────────────────

    public function confirmDeletePayment(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);
        $this->deletingPaymentId    = $paymentId;
        $this->deletingPaymentLabel = "Installment #{$payment->installment_number}";
        $this->showDeletePaymentConfirm = true;
    }

    public function cancelDeletePayment(): void
    {
        $this->showDeletePaymentConfirm = false;
        $this->deletingPaymentId    = null;
        $this->deletingPaymentLabel = '';
    }

    public function deletePayment(PaymentService $service): void
    {
        $payment = Payment::findOrFail($this->deletingPaymentId);
        $label   = "Installment #{$payment->installment_number}";

        $payment->delete();

        // Recompute the whole unit after deleting a slot
        $service->recomputeUnit($this->unit);
        $this->unit->refresh();

        $this->cancelDeletePayment();
        $this->dispatch('payment-deleted');
        session()->flash('success', "{$label} deleted and unit recomputed.");
    }

    // ── RENDER ─────────────────────────────────────────────────────────────

    public function render(PaymentService $service)
    {
        $summary = $service->auditSummary($this->unit);

        return view('livewire.manage-payments', [
            'summary' => $summary,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function resetAddPaymentForm(): void
    {
        $this->add_installment_number = '';
        $this->add_actual_amount      = '';
        $this->add_payment_date       = '';
        $this->add_due_date           = '';
        $this->add_notes              = '';
    }
}