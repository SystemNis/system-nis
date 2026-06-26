<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Unit;
use App\Services\Payment\PaymentService;
use Livewire\Component;

class ManagePayments extends Component
{
    public Unit $unit;

    // ── Add payment form ───────────────────────────────────────────────────
    public bool   $showAddPaymentModal    = false;
    public string $add_installment_number = '';
    public string $add_slot_type          = 'regular'; // 'regular' | 'kpr'
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

    public function mount(Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function nextEmptySlot(): ?int
    {
        if ($this->unit->max_installments === null) {
            return null;
        }

        $paidSlots = Payment::where('unit_id', $this->unit->id)
            ->whereNull('deleted_at')
            ->pluck('installment_number')
            ->toArray();

        $totalSlots = $this->unit->total_slots;
        for ($i = 1; $i <= $totalSlots; $i++) {
            if (!in_array($i, $paidSlots, true)) {
                return $i;
            }
        }

        return null;
    }

    public function hasInstallmentPlan(): bool
    {
        return $this->unit->angsuran_per_bulan !== null
            && $this->unit->max_installments !== null;
    }

    // ── ADD ────────────────────────────────────────────────────────────────

    public function openAddPaymentModal(?int $slotNumber = null, string $slotType = 'regular'): void
    {
        if (!$this->hasInstallmentPlan()) {
            session()->flash('error',
                "Unit {$this->unit->unit_label} has no installment plan yet. "
                . "Set Angsuran Per Bulan and Max Installments in Reconciliation first."
            );
            return;
        }

        $this->resetAddPaymentForm();

        if ($slotNumber) {
            $this->add_installment_number = (string) $slotNumber;
            $this->add_slot_type          = $slotType;
        } else {
            $next = $this->nextEmptySlot();
            if ($next === null) {
                session()->flash('success', "All installments for {$this->unit->unit_label} are already recorded.");
                return;
            }
            $this->add_installment_number = (string) $next;
            $this->add_slot_type          = 'regular';
        }

        $this->showAddPaymentModal = true;
    }

    public function openKprSlot(int $slotNumber): void
    {
        $this->openAddPaymentModal($slotNumber, 'kpr');
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

        $totalSlots = $this->unit->total_slots;

        $validated = $this->validate([
            'add_installment_number' => 'required|integer|min:1|max:' . $totalSlots,
            'add_actual_amount'      => 'required|integer|min:1',
            'add_slot_type'          => 'required|in:regular,kpr',
            'add_payment_date'       => 'nullable|date',
            'add_due_date'           => 'nullable|date',
            'add_notes'              => 'nullable|string|max:500',
        ], [], [
            'add_installment_number' => 'Installment Number',
            'add_actual_amount'      => 'Amount Paid',
        ]);

        $buyer = $this->unit->activeBuyer;

        if (!$buyer) {
            $this->addError('add_actual_amount', 'This unit has no active buyer. Add a buyer first.');
            return;
        }

        try {
            $result = $service->record($this->unit, $buyer, [
                'installment_number' => (int) $validated['add_installment_number'],
                'slot_type'          => $validated['add_slot_type'],
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
        $this->edit_notes = $payment->notes ?? '';

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

        $this->unit->refresh();
        $this->closeEditPaymentModal();
        $this->dispatch('payment-updated');
        session()->flash('success', "Installment #{$payment->installment_number} updated and recomputed.");
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

    private function resetAddPaymentForm(): void
    {
        $this->add_installment_number = '';
        $this->add_slot_type          = 'regular';
        $this->add_actual_amount      = '';
        $this->add_payment_date       = '';
        $this->add_due_date           = '';
        $this->add_notes              = '';
    }
}