<?php

namespace App\Livewire;

use App\Models\Cluster;
use App\Models\Unit;
use App\Models\Buyer;
use Livewire\Component;
use Livewire\WithPagination;

class ReconciliationTable extends Component
{
    use WithPagination;

    // ── Filter State ───────────────────────────────────────────────────────
    // Tahap sits first in the filter hierarchy: Tahap → Cluster → Unit.
    public string $filterTahap   = '';
    public string $search        = '';
    public string $filterCluster = '';
    public string $filterStatus  = '';
    public string $filterPayType = '';

    // ── Add Modal ──────────────────────────────────────────────────────────
    public bool $showAddModal = false;

    // Add form fields
    // Only cluster, block, unit_number, luas_tanah are HARD REQUIRED below.
    // Everything else (tahap included) is optional and stored as null/empty
    // if left blank — this matches Unit::REQUIRED_FIELDS and prepares the
    // schema for the upcoming bulk-import feature.
    public string $add_tahap            = '';
    public string $add_cluster_id       = '';
    public string $add_block            = '';
    public string $add_unit_number      = '';
    public string $add_house_type       = '';
    public string $add_luas_bangunan    = '';
    public string $add_luas_tanah       = '';
    public string $add_payment_type     = '';
    public string $add_harga_penjualan  = '';
    public string $add_down_payment     = '';
    public string $add_max_installments = '';
    public string $add_buyer_name       = '';
    public string $add_contract_date    = '';
    public string $add_status           = 'unpaid';
    public string $add_notes            = '';

    // ── Edit Modal ─────────────────────────────────────────────────────────
    public bool  $showEditModal = false;
    public ?int  $editingUnitId = null;

    // Edit form mirrors add form fields
    public string $edit_tahap            = '';
    public string $edit_cluster_id       = '';
    public string $edit_block            = '';
    public string $edit_unit_number      = '';
    public string $edit_house_type       = '';
    public string $edit_luas_bangunan    = '';
    public string $edit_luas_tanah       = '';
    public string $edit_payment_type     = '';
    public string $edit_harga_penjualan  = '';
    public string $edit_down_payment     = '';
    public string $edit_max_installments = '';
    public string $edit_status           = '';
    public string $edit_notes            = '';
    // Buyer edit
    public ?int   $edit_buyer_id        = null;
    public string $edit_buyer_name      = '';
    public string $edit_contract_date   = '';

    // ── Delete Confirm ─────────────────────────────────────────────────────
    public bool $showDeleteConfirm = false;
    public ?int $deletingUnitId    = null;
    public string $deletingLabel   = '';

    // ── Lifecycle ──────────────────────────────────────────────────────────

    protected $queryString = [
        'search'        => ['except' => ''],
        'filterTahap'   => ['except' => ''],
        'filterCluster' => ['except' => ''],
        'filterStatus'  => ['except' => ''],
        'filterPayType' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTahap(): void   { $this->resetPage(); }
    public function updatingFilterCluster(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }
    public function updatingFilterPayType(): void { $this->resetPage(); }

    // ── ADD MODAL ──────────────────────────────────────────────────────────

    public function openAddModal(): void
    {
        $this->resetAddForm();
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
        $this->resetAddForm();
        $this->resetErrorBag();
    }

    public function saveNewUnit(): void
    {
        // Only the four hard-identity fields are required. Everything else
        // is validated as `nullable` so the form can be submitted with just
        // the bare minimum — matching Unit::REQUIRED_FIELDS and preparing
        // for partial/bulk-imported records.
        $validated = $this->validate([
            'add_tahap'             => 'nullable|in:' . implode(',', Unit::TAHAP_OPTIONS),
            'add_cluster_id'        => 'required|exists:clusters,id',
            'add_block'             => 'required|string|max:10',
            'add_unit_number'       => 'required|string|max:10',
            'add_house_type'        => 'nullable|string|max:50',
            'add_luas_bangunan'     => 'nullable|integer|min:1',
            'add_luas_tanah'        => 'required|integer|min:1',
            'add_payment_type'      => 'nullable|in:Cash Keras,KPR,Cash Bertahap',
            'add_harga_penjualan'   => 'nullable|integer|min:1',
            'add_down_payment'      => 'nullable|integer|min:0',
            'add_max_installments'  => 'nullable|integer|min:1|max:360',
            'add_buyer_name'        => 'nullable|string|max:255',
            'add_contract_date'     => 'nullable|date',
            'add_status'            => 'required|in:active,settled,land_cleared,unpaid,cancelled',
            'add_notes'             => 'nullable|string',
        ], [], [
            'add_tahap'             => 'Tahap',
            'add_cluster_id'        => 'Cluster',
            'add_block'             => 'Block',
            'add_unit_number'       => 'Unit Number',
            'add_house_type'        => 'House Type',
            'add_luas_bangunan'     => 'Luas Bangunan',
            'add_luas_tanah'        => 'Luas Tanah',
            'add_payment_type'      => 'Payment Type',
            'add_harga_penjualan'   => 'Harga Penjualan',
            'add_down_payment'      => 'Down Payment',
            'add_max_installments'  => 'Max Installments',
            'add_buyer_name'        => 'Buyer Name',
            'add_contract_date'     => 'Contract Date',
        ]);

        // angsuran_per_bulan only computable if BOTH harga_penjualan and
        // max_installments were actually provided — otherwise leave null.
        $angsuran = $this->calcInstallmentOrNull(
            $validated['add_harga_penjualan']  !== null ? (int) $validated['add_harga_penjualan']  : null,
            $validated['add_down_payment']     !== null ? (int) $validated['add_down_payment']     : 0,
            $validated['add_max_installments'] !== null ? (int) $validated['add_max_installments'] : null,
        );

        try {
            $unit = Unit::create([
                'tahap'              => $validated['add_tahap'] ?: null,
                'cluster_id'         => $validated['add_cluster_id'],
                'block'              => strtoupper(trim($validated['add_block'])),
                'unit_number'        => trim($validated['add_unit_number']),
                'house_type'         => $validated['add_house_type'] ? strtoupper(trim($validated['add_house_type'])) : null,
                'luas_bangunan'      => $validated['add_luas_bangunan'] !== null ? (int) $validated['add_luas_bangunan'] : null,
                'luas_tanah'         => (int) $validated['add_luas_tanah'],
                'payment_type'       => $validated['add_payment_type'] ?: null,
                'harga_penjualan'    => $validated['add_harga_penjualan']  !== null ? (int) $validated['add_harga_penjualan']  : null,
                'down_payment'       => $validated['add_down_payment']     !== null ? (int) $validated['add_down_payment']     : null,
                'angsuran_per_bulan' => $angsuran,
                'max_installments'   => $validated['add_max_installments'] !== null ? (int) $validated['add_max_installments'] : null,
                'status'             => $validated['add_status'],
                'notes'              => $validated['add_notes'] ?: null,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $block  = strtoupper(trim($validated['add_block']));
            $unitNo = trim($validated['add_unit_number']);
            $this->addError('add_unit_number',
                "Unit {$block}-{$unitNo} already exists in this cluster. Choose a different block or unit number."
            );
            return;
        }

        if (!empty($validated['add_buyer_name'])) {
            Buyer::create([
                'unit_id'       => $unit->id,
                'name'          => strtoupper(trim($validated['add_buyer_name'])),
                'contract_date' => $validated['add_contract_date'] ?: null,
                'is_active'     => true,
            ]);
            if ($unit->status === 'unpaid') {
                $unit->update(['status' => 'active']);
            }
        }

        $this->closeAddModal();
        session()->flash('success', "Unit {$unit->unit_label} added successfully.");
    }

    // ── EDIT MODAL ─────────────────────────────────────────────────────────

    public function openEditModal(int $unitId): void
    {
        $unit = Unit::with('activeBuyer')->findOrFail($unitId);

        $this->editingUnitId         = $unit->id;
        $this->edit_tahap            = $unit->tahap ?? '';
        $this->edit_cluster_id       = (string) $unit->cluster_id;
        $this->edit_block            = $unit->block;
        $this->edit_unit_number      = $unit->unit_number;
        $this->edit_house_type       = $unit->house_type ?? '';
        $this->edit_luas_bangunan    = $unit->luas_bangunan !== null ? (string) $unit->luas_bangunan : '';
        $this->edit_luas_tanah       = (string) $unit->luas_tanah;
        $this->edit_payment_type     = $unit->payment_type ?? '';
        $this->edit_harga_penjualan  = $unit->harga_penjualan !== null ? (string) $unit->harga_penjualan : '';
        $this->edit_down_payment     = $unit->down_payment !== null ? (string) $unit->down_payment : '';
        $this->edit_max_installments = $unit->max_installments !== null ? (string) $unit->max_installments : '';
        $this->edit_status           = $unit->status;
        $this->edit_notes            = $unit->notes ?? '';

        if ($buyer = $unit->activeBuyer) {
            $this->edit_buyer_id      = $buyer->id;
            $this->edit_buyer_name    = $buyer->name;
            $this->edit_contract_date = $buyer->contract_date
                ? $buyer->contract_date->format('Y-m-d')
                : '';
        } else {
            $this->edit_buyer_id      = null;
            $this->edit_buyer_name    = '';
            $this->edit_contract_date = '';
        }

        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingUnitId = null;
        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $validated = $this->validate([
            'edit_tahap'             => 'nullable|in:' . implode(',', Unit::TAHAP_OPTIONS),
            'edit_cluster_id'        => 'required|exists:clusters,id',
            'edit_block'             => 'required|string|max:10',
            'edit_unit_number'       => 'required|string|max:10',
            'edit_house_type'        => 'nullable|string|max:50',
            'edit_luas_bangunan'     => 'nullable|integer|min:1',
            'edit_luas_tanah'        => 'required|integer|min:1',
            'edit_payment_type'      => 'nullable|in:Cash Keras,KPR,Cash Bertahap',
            'edit_harga_penjualan'   => 'nullable|integer|min:1',
            'edit_down_payment'      => 'nullable|integer|min:0',
            'edit_max_installments'  => 'nullable|integer|min:1|max:360',
            'edit_status'            => 'required|in:active,settled,land_cleared,unpaid,cancelled',
            'edit_notes'             => 'nullable|string',
            'edit_buyer_name'        => 'nullable|string|max:255',
            'edit_contract_date'     => 'nullable|date',
        ], [], [
            'edit_tahap'             => 'Tahap',
            'edit_cluster_id'        => 'Cluster',
            'edit_block'             => 'Block',
            'edit_unit_number'       => 'Unit Number',
            'edit_house_type'        => 'House Type',
            'edit_luas_bangunan'     => 'Luas Bangunan',
            'edit_luas_tanah'        => 'Luas Tanah',
            'edit_payment_type'      => 'Payment Type',
            'edit_harga_penjualan'   => 'Harga Penjualan',
            'edit_down_payment'      => 'Down Payment',
            'edit_max_installments'  => 'Max Installments',
        ]);

        $unit = Unit::findOrFail($this->editingUnitId);

        $angsuran = $this->calcInstallmentOrNull(
            $validated['edit_harga_penjualan']  !== null ? (int) $validated['edit_harga_penjualan']  : null,
            $validated['edit_down_payment']     !== null ? (int) $validated['edit_down_payment']     : 0,
            $validated['edit_max_installments'] !== null ? (int) $validated['edit_max_installments'] : null,
        );

        $unit->update([
            'tahap'              => $validated['edit_tahap'] ?: null,
            'cluster_id'         => $validated['edit_cluster_id'],
            'block'              => strtoupper(trim($validated['edit_block'])),
            'unit_number'        => trim($validated['edit_unit_number']),
            'house_type'         => $validated['edit_house_type'] ? strtoupper(trim($validated['edit_house_type'])) : null,
            'luas_bangunan'      => $validated['edit_luas_bangunan'] !== null ? (int) $validated['edit_luas_bangunan'] : null,
            'luas_tanah'         => (int) $validated['edit_luas_tanah'],
            'payment_type'       => $validated['edit_payment_type'] ?: null,
            'harga_penjualan'    => $validated['edit_harga_penjualan']  !== null ? (int) $validated['edit_harga_penjualan']  : null,
            'down_payment'       => $validated['edit_down_payment']     !== null ? (int) $validated['edit_down_payment']     : null,
            'angsuran_per_bulan' => $angsuran,
            'max_installments'   => $validated['edit_max_installments'] !== null ? (int) $validated['edit_max_installments'] : null,
            'status'             => $validated['edit_status'],
            'notes'              => $validated['edit_notes'] ?: null,
        ]);

        // Handle buyer update
        if (!empty($validated['edit_buyer_name'])) {
            if ($this->edit_buyer_id) {
                // Update existing buyer
                Buyer::find($this->edit_buyer_id)?->update([
                    'name'          => strtoupper(trim($validated['edit_buyer_name'])),
                    'contract_date' => $validated['edit_contract_date'] ?: null,
                ]);
            } else {
                // Create new buyer record
                Buyer::create([
                    'unit_id'       => $unit->id,
                    'name'          => strtoupper(trim($validated['edit_buyer_name'])),
                    'contract_date' => $validated['edit_contract_date'] ?: null,
                    'is_active'     => true,
                ]);
            }
        }

        $this->closeEditModal();
        session()->flash('success', "Unit {$unit->unit_label} updated successfully.");
    }

    // ── DELETE CONFIRM ─────────────────────────────────────────────────────

    public function confirmDelete(int $unitId): void
    {
        $unit = Unit::findOrFail($unitId);
        $this->deletingUnitId = $unitId;
        $this->deletingLabel  = $unit->unit_label;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingUnitId    = null;
        $this->deletingLabel     = '';
    }

    public function deleteUnit(): void
    {
        $unit = Unit::findOrFail($this->deletingUnitId);
        $label = $unit->unit_label;
        $unit->delete();

        $this->cancelDelete();
        session()->flash('success', "Unit {$label} has been removed.");
    }

    // ── RENDER ─────────────────────────────────────────────────────────────

    public function render()
    {
        $clusters = Cluster::orderBy('name')->get();

        $units = Unit::with(['cluster', 'activeBuyer', 'payments'])
            ->when($this->filterTahap,   fn($q) => $q->where('tahap', $this->filterTahap))
            ->when($this->filterCluster, fn($q) => $q->where('cluster_id', $this->filterCluster))
            ->when($this->filterStatus,  fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPayType, fn($q) => $q->where('payment_type', $this->filterPayType))
            ->when($this->search, function ($q) {
                $s   = '%' . $this->search . '%';
                $raw = trim($this->search);

                // Build a DB-portable concatenated label match (block + '-' + unit_number).
                // SQLite uses ||, MySQL uses CONCAT() — detect via the driver name.
                $driver      = \Illuminate\Support\Facades\DB::connection()->getDriverName();
                $concatExpr  = $driver === 'mysql'
                    ? "CONCAT(block, '-', unit_number) LIKE ?"
                    : "(block || '-' || unit_number) LIKE ?";

                $q->where(function ($inner) use ($s, $raw, $concatExpr) {
                    $inner->where('block', 'like', $s)
                          ->orWhere('unit_number', 'like', $s)
                          ->orWhere('house_type', 'like', $s)
                          ->orWhereRaw($concatExpr, ["%{$raw}%"])
                          ->orWhereHas('activeBuyer', fn($b) => $b->where('name', 'like', $s))
                          ->orWhereHas('cluster',     fn($c) => $c->where('name', 'like', $s));
                });
            })
            ->orderBy('cluster_id')
            ->orderBy('block')
            ->orderBy('unit_number')
            ->paginate(20);

        return view('livewire.reconciliation-table', [
            'clusters'     => $clusters,
            'units'        => $units,
            'tahapOptions' => Unit::TAHAP_OPTIONS,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Computes angsuran_per_bulan only when BOTH harga and maxInstallments
     * are present. Returns null otherwise — a partially-filled unit simply
     * has no installment plan yet, which is valid now that these fields
     * are optional.
     */
    private function calcInstallmentOrNull(?int $harga, ?int $dp, ?int $count): ?int
    {
        if ($harga === null || $count === null || $count <= 0) {
            return null;
        }

        return (int) round(($harga - ($dp ?? 0)) / $count);
    }

    private function resetAddForm(): void
    {
        $this->add_tahap            = '';
        $this->add_cluster_id       = '';
        $this->add_block            = '';
        $this->add_unit_number      = '';
        $this->add_house_type       = '';
        $this->add_luas_bangunan    = '';
        $this->add_luas_tanah       = '';
        $this->add_payment_type     = '';
        $this->add_harga_penjualan  = '';
        $this->add_down_payment     = '';
        $this->add_max_installments = '';
        $this->add_buyer_name       = '';
        $this->add_contract_date    = '';
        $this->add_status           = 'unpaid';
        $this->add_notes            = '';
    }
}