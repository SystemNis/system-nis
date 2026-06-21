<?php

namespace App\Livewire;

use App\Models\Cluster;
use App\Models\Unit;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentAuditSummary;
use Livewire\Component;

/**
 * AuditDashboard — Page 1 Livewire Component
 * ════════════════════════════════════════════
 * Owns all interactivity for the Dashboard page:
 *   - KPI widget row (recomputed on every render)
 *   - Cluster → Unit search (unit field is a searchable Alpine combobox
 *     that matches on unit label "A-0" OR buyer name, not just alphabetic
 *     prefix matching like a native <select> would)
 *   - Deep-dive detail card + read-only installment ledger
 *   - Embedded ManagePayments sub-component for payment CRUD
 *   - Quick "Add Cluster" modal so the cluster list can grow without
 *     leaving the dashboard
 *
 * NOTE: PaymentAuditSummary is NOT stored as a Livewire property because
 * it contains Eloquent models and Collections — Livewire cannot safely
 * hydrate/dehydrate these. Instead, $unitId is stored and the summary is
 * rebuilt fresh in render() on every request, which also guarantees the
 * display is always in sync with the DB after payments are mutated.
 */
class AuditDashboard extends Component
{
    // ── Search state ───────────────────────────────────────────────────────
    public string $selectedClusterId = '';
    public string $selectedUnitId    = '';
    public string $unitSearchQuery   = ''; // bound via Alpine @entangle, not persisted to URL
    public bool   $searched          = false;

    // ── Internal: ID of the currently displayed unit ───────────────────────
    public ?int $activeUnitId = null;

    // ── Add Cluster modal ──────────────────────────────────────────────────
    public string $newClusterName     = '';
    public string $newClusterLocation = '';

    // ── URL query string persistence ───────────────────────────────────────
    protected $queryString = [
        'selectedClusterId' => ['except' => ''],
        'selectedUnitId'    => ['except' => ''],
        'searched'          => ['except' => false],
    ];

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function mount(): void
    {
        if ($this->searched && $this->selectedUnitId) {
            $this->activeUnitId = (int) $this->selectedUnitId;
        }
    }

    /**
     * When cluster changes — reset unit selection and clear result.
     */
    public function updatedSelectedClusterId(): void
    {
        $this->selectedUnitId  = '';
        $this->unitSearchQuery = '';
        $this->searched        = false;
        $this->activeUnitId    = null;
    }

    /**
     * When unit changes (set programmatically by the Alpine combobox via
     * $wire.set) — clear result so user must re-hit Search.
     */
    public function updatedSelectedUnitId(): void
    {
        $this->searched     = false;
        $this->activeUnitId = null;
    }

    // ── Search ─────────────────────────────────────────────────────────────

    public function search(): void
    {
        $this->validate([
            'selectedClusterId' => 'required|exists:clusters,id',
            'selectedUnitId'    => 'required|exists:units,id',
        ], [
            'selectedClusterId.required' => 'Please select a Cluster.',
            'selectedUnitId.required'    => 'Please select a Unit.',
            'selectedUnitId.exists'      => 'Selected unit not found.',
        ]);

        $this->activeUnitId = (int) $this->selectedUnitId;
        $this->searched     = true;
    }

    /**
     * Called by ManagePayments sub-component via Livewire browser events.
     */
    public function refreshSummary(): void
    {
        // No-op: render() always rebuilds fresh from DB.
    }

    // ── Add Cluster ────────────────────────────────────────────────────────

    public function saveNewCluster(): void
    {
        $validated = $this->validate([
            'newClusterName'     => 'required|string|max:100|unique:clusters,name',
            'newClusterLocation' => 'nullable|string|max:255',
        ], [
            'newClusterName.required' => 'Cluster name is required.',
            'newClusterName.unique'   => 'A cluster with this name already exists.',
        ]);

        $cluster = Cluster::create([
            'name'     => strtoupper(trim($validated['newClusterName'])),
            'location' => $validated['newClusterLocation'] ?: null,
        ]);

        $this->newClusterName     = '';
        $this->newClusterLocation = '';

        // Auto-select the freshly created cluster
        $this->selectedClusterId = (string) $cluster->id;
        $this->selectedUnitId    = '';
        $this->unitSearchQuery   = '';
        $this->searched          = false;
        $this->activeUnitId      = null;

        session()->flash('success', "Cluster {$cluster->name} created.");
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render(PaymentService $service)
    {
        // KPI aggregates — always fresh
        $kpis = $service->dashboardKpis();

        // Cluster list for dropdown 1
        $clusters = Cluster::orderBy('name')->get();

        // Unit list for the searchable combobox — only after cluster is chosen.
        // Sent to Alpine as a flat JS-friendly array via unitOptions().
        $units = $this->selectedClusterId
            ? Unit::with('activeBuyer')
                  ->where('cluster_id', $this->selectedClusterId)
                  ->orderBy('block')
                  ->orderBy('unit_number')
                  ->get()
            : collect();

        // Build audit summary if a unit has been searched
        $summary = null;
        if ($this->searched && $this->activeUnitId) {
            $unit = Unit::with(['cluster', 'activeBuyer', 'payments'])
                ->find($this->activeUnitId);

            if ($unit) {
                $summary = $service->auditSummary($unit);
            } else {
                $this->searched     = false;
                $this->activeUnitId = null;
            }
        }

        return view('livewire.audit-dashboard', [
            'kpis'     => $kpis,
            'clusters' => $clusters,
            'units'    => $units,
            'summary'  => $summary,
        ]);
    }

    /**
     * Flat array for the Alpine.js searchable combobox:
     *   [{ id: 5, label: "A-0", buyer: "HALBERG NG" }, ...]
     *
     * Exposed as a Livewire "computed"-style public method called via
     * x-init="units = $wire.unitOptions" in the Blade view.
     */
    public function unitOptions(): array
    {
        if (!$this->selectedClusterId) {
            return [];
        }

        return Unit::with('activeBuyer')
            ->where('cluster_id', $this->selectedClusterId)
            ->orderBy('block')
            ->orderBy('unit_number')
            ->get()
            ->map(fn (Unit $u) => [
                'id'    => $u->id,
                'label' => $u->unit_label,
                'buyer' => $u->activeBuyer?->name ?? '',
            ])
            ->values()
            ->toArray();
    }
}