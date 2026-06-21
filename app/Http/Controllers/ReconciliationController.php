<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Unit;
use App\Models\Buyer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    /**
     * PAGE 2 — Master Reconciliation Sheet
     * Renders the Livewire-powered table page.
     */
    public function index(): View
    {
        return view('reconciliation.index');
    }

    /**
     * Store a brand-new unit (called from the "Add New House" modal via Livewire).
     * Livewire handles this directly, but this is the fallback HTTP route.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cluster_id'         => 'required|exists:clusters,id',
            'block'              => 'required|string|max:10',
            'unit_number'        => 'required|string|max:10',
            'house_type'         => 'required|string|max:50',
            'luas_bangunan'      => 'required|integer|min:1',
            'luas_tanah'         => 'required|integer|min:1',
            'payment_type'       => 'required|in:Cash Keras,KPR,Cash Bertahap',
            'harga_penjualan'    => 'required|integer|min:1',
            'down_payment'       => 'required|integer|min:0',
            'max_installments'   => 'required|integer|min:1|max:360',
            'status'             => 'sometimes|in:active,settled,land_cleared,unpaid,cancelled',
            // Buyer fields
            'buyer_name'         => 'nullable|string|max:255',
            'buyer_contract_date'=> 'nullable|date',
        ]);

        $unit = Unit::create([
            ...$validated,
            'angsuran_per_bulan' => $this->calcInstallment(
                $validated['harga_penjualan'],
                $validated['down_payment'],
                $validated['max_installments']
            ),
            'status' => $validated['status'] ?? 'unpaid',
        ]);

        if (!empty($validated['buyer_name'])) {
            Buyer::create([
                'unit_id'       => $unit->id,
                'name'          => $validated['buyer_name'],
                'contract_date' => $validated['buyer_contract_date'] ?? null,
                'is_active'     => true,
            ]);
            $unit->update(['status' => 'active']);
        }

        return redirect()->route('reconciliation.index')
            ->with('success', "Unit {$unit->unit_label} added successfully.");
    }

    /**
     * Update an existing unit.
     */
    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'cluster_id'         => 'sometimes|exists:clusters,id',
            'block'              => 'sometimes|string|max:10',
            'unit_number'        => 'sometimes|string|max:10',
            'house_type'         => 'sometimes|string|max:50',
            'luas_bangunan'      => 'sometimes|integer|min:1',
            'luas_tanah'         => 'sometimes|integer|min:1',
            'payment_type'       => 'sometimes|in:Cash Keras,KPR,Cash Bertahap',
            'harga_penjualan'    => 'sometimes|integer|min:1',
            'down_payment'       => 'sometimes|integer|min:0',
            'max_installments'   => 'sometimes|integer|min:1|max:360',
            'status'             => 'sometimes|in:active,settled,land_cleared,unpaid,cancelled',
            'notes'              => 'nullable|string',
        ]);

        // Recalculate installment if financial fields changed
        if (isset($validated['harga_penjualan']) || isset($validated['down_payment']) || isset($validated['max_installments'])) {
            $validated['angsuran_per_bulan'] = $this->calcInstallment(
                $validated['harga_penjualan']  ?? $unit->harga_penjualan,
                $validated['down_payment']     ?? $unit->down_payment,
                $validated['max_installments'] ?? $unit->max_installments,
            );
        }

        $unit->update($validated);

        return redirect()->route('reconciliation.index')
            ->with('success', "Unit {$unit->unit_label} updated.");
    }

    /**
     * Soft-delete a unit.
     */
    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->route('reconciliation.index')
            ->with('success', "Unit {$unit->unit_label} removed.");
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function calcInstallment(int $harga, int $dp, int $count): int
    {
        return $count > 0 ? (int) round(($harga - $dp) / $count) : 0;
    }
}
