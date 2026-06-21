<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Unit;
use App\Services\Payment\PaymentService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

/**
 * HistoryLog — Livewire Component
 * ═════════════════════════════════
 * Displays all soft-deleted units and payments.
 * Supports:
 *   - Restore (undo the soft delete, brings record back to active view)
 *   - Force Delete (permanent, irreversible DB removal)
 */
class HistoryLog extends Component
{
    use WithPagination;

    // ── Tab state ──────────────────────────────────────────────────────────
    public string $activeTab = 'units'; // 'units' | 'payments'

    // ── Confirm modals ─────────────────────────────────────────────────────
    public bool   $showRestoreConfirm      = false;
    public bool   $showForceDeleteConfirm  = false;
    public ?int   $actionId                = null;
    public string $actionLabel             = '';
    public string $actionType              = ''; // 'unit' | 'payment'

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function updatingActiveTab(): void
    {
        $this->resetPage();
        $this->cancelAction();
    }

    // ── Tab switch ─────────────────────────────────────────────────────────

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->cancelAction();
    }

    // ── RESTORE ────────────────────────────────────────────────────────────

    public function confirmRestore(int $id, string $type): void
    {
        $this->actionId   = $id;
        $this->actionType = $type;

        if ($type === 'unit') {
            $unit = Unit::onlyTrashed()->findOrFail($id);
            $this->actionLabel = "Unit {$unit->block}-{$unit->unit_number}";
        } else {
            $payment = Payment::onlyTrashed()->findOrFail($id);
            $this->actionLabel = "Installment #{$payment->installment_number} (Unit ID {$payment->unit_id})";
        }

        $this->showRestoreConfirm = true;
    }

    public function restore(PaymentService $service): void
    {
        if ($this->actionType === 'unit') {
            $unit = Unit::onlyTrashed()->findOrFail($this->actionId);
            $unit->restore();

            // Also restore all soft-deleted payments belonging to this unit
            Payment::onlyTrashed()->where('unit_id', $unit->id)->restore();

            // Recompute to sync status fields
            $service->recomputeUnit($unit);

            session()->flash('success', "Unit {$unit->block}-{$unit->unit_number} restored with all its payments.");

        } else {
            $payment = Payment::onlyTrashed()->findOrFail($this->actionId);
            $payment->restore();

            // Recompute the owning unit so cumulative NIS is correct again
            $unit = Unit::find($payment->unit_id);
            if ($unit) {
                $service->recomputeUnit($unit);
            }

            session()->flash('success', "Installment #{$payment->installment_number} restored and unit recomputed.");
        }

        $this->cancelAction();
    }

    // ── FORCE DELETE ───────────────────────────────────────────────────────

    public function confirmForceDelete(int $id, string $type): void
    {
        $this->actionId   = $id;
        $this->actionType = $type;

        if ($type === 'unit') {
            $unit = Unit::onlyTrashed()->findOrFail($id);
            $this->actionLabel = "Unit {$unit->block}-{$unit->unit_number}";
        } else {
            $payment = Payment::onlyTrashed()->findOrFail($id);
            $this->actionLabel = "Installment #{$payment->installment_number} (Unit ID {$payment->unit_id})";
        }

        $this->showForceDeleteConfirm = true;
    }

    public function forceDelete(): void
    {
        DB::transaction(function () {
            if ($this->actionType === 'unit') {
                $unit = Unit::onlyTrashed()->findOrFail($this->actionId);

                // Permanently delete all payments for this unit first (FK safety)
                Payment::withTrashed()->where('unit_id', $unit->id)->forceDelete();

                // Permanently delete all buyers
                \App\Models\Buyer::withTrashed()->where('unit_id', $unit->id)->forceDelete();

                $label = "Unit {$unit->block}-{$unit->unit_number}";
                $unit->forceDelete();

                session()->flash('success', "{$label} permanently deleted from the database.");

            } else {
                $payment = Payment::onlyTrashed()->findOrFail($this->actionId);
                $label   = "Installment #{$payment->installment_number}";
                $payment->forceDelete();

                session()->flash('success', "{$label} permanently deleted.");
            }
        });

        $this->cancelAction();
    }

    // ── Cancel ─────────────────────────────────────────────────────────────

    public function cancelAction(): void
    {
        $this->showRestoreConfirm     = false;
        $this->showForceDeleteConfirm = false;
        $this->actionId               = null;
        $this->actionLabel            = '';
        $this->actionType             = '';
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render()
    {
        $deletedUnits = Unit::onlyTrashed()
            ->with(['cluster'])
            ->orderByDesc('deleted_at')
            ->paginate(20, ['*'], 'units_page');

        $deletedPayments = Payment::onlyTrashed()
            ->with(['unit.cluster', 'buyer'])
            ->orderByDesc('deleted_at')
            ->paginate(20, ['*'], 'payments_page');

        return view('livewire.history-log', [
            'deletedUnits'    => $deletedUnits,
            'deletedPayments' => $deletedPayments,
        ]);
    }
}
