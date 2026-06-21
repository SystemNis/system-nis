<?php

namespace App\Services\Payment;

use App\Models\Buyer;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PaymentService — Core Audit Engine
 * ════════════════════════════════════
 * The single source of truth for all payment processing in the NIS system.
 * Every payment recorded through this service is guaranteed to have:
 *   - Correct variance and status classification
 *   - Correct NIS 30% share calculation
 *   - Correct kavling ceiling enforcement (cap + overflow detection)
 *   - Correct running cumulative totals
 *
 * This service uses the NisMath trait for all pure calculations and wraps
 * every DB write in a transaction so no partial state can be committed.
 *
 * USAGE:
 *   $service = app(PaymentService::class);
 *
 *   // Record a new payment:
 *   $result = $service->record($unit, $buyer, [
 *       'installment_number' => 3,
 *       'actual_amount'      => 90_000_000,
 *       'payment_date'       => '2024-03-01',
 *   ]);
 *
 *   // Get the full audit summary for a unit:
 *   $summary = $service->auditSummary($unit);
 *
 *   // Recompute all derived fields after a payment is edited:
 *   $service->recomputeUnit($unit);
 */
class PaymentService
{
    use NisMath;

    // ══════════════════════════════════════════════════════════════════════
    // RECORD A PAYMENT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Record a single installment payment for a unit.
     *
     * Performs full math derivation, ceiling check, and persistence in a DB
     * transaction. Returns a PaymentResult DTO — never returns a raw model.
     *
     * @param Unit  $unit     The unit receiving the payment
     * @param Buyer $buyer    The active buyer making the payment
     * @param array $data     Required keys: installment_number, actual_amount
     *                        Optional keys: payment_date, due_date, reported_by, notes
     *
     * @throws \InvalidArgumentException  If installment_number is out of range or already exists
     * @throws \RuntimeException          If unit is land-cleared and overflow policy blocks recording
     */
    public function record(Unit $unit, Buyer $buyer, array $data): PaymentResult
    {
        // ── 1. Validate slot ──────────────────────────────────────────────
        $slot = (int) $data['installment_number'];

        if ($slot < 1 || $slot > $unit->max_installments) {
            throw new \InvalidArgumentException(
                "Installment #{$slot} is out of range for unit {$unit->unit_label} "
                . "(max: {$unit->max_installments})."
            );
        }

        $existing = Payment::where('unit_id', $unit->id)
            ->where('installment_number', $slot)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException(
                "Installment #{$slot} for unit {$unit->unit_label} is already recorded. "
                . "Use updatePayment() to modify it."
            );
        }

        // ── 2. Derive all math ────────────────────────────────────────────
        $actualAmount  = (int) $data['actual_amount'];
        $targetAmount  = $unit->angsuran_per_bulan;   // snapshot from unit
        $kavlingValue  = $this->kavlingValue($unit->luas_tanah);

        // Previous cumulative NIS (sum of all capped_nis_share already recorded)
        $previousCumNis = (int) Payment::where('unit_id', $unit->id)
            ->whereNull('deleted_at')
            ->sum('capped_nis_share');

        $headroomBefore  = $this->remainingKavlingHeadroom($kavlingValue, $previousCumNis);
        $variance        = $this->variance($actualAmount, $targetAmount);
        $paymentStatus   = $this->paymentStatus($variance);
        $supposedNis     = $this->supposedNisShare($targetAmount);
        $actualNis       = $this->actualNisShare($actualAmount);
        $cappedNis       = $this->cappedNisShare($actualNis, $headroomBefore);
        $newCumNis       = $previousCumNis + $cappedNis;
        $headroomAfter   = $this->remainingKavlingHeadroom($kavlingValue, $newCumNis);

        // Overflow: the actual NIS share exceeds the remaining kavling headroom
        $overflow        = $actualNis > $headroomBefore;
        // Cleared HERE: this payment consumed the last of the headroom
        $clearedHere     = $overflow && $headroomBefore > 0;
        // Error: kavling was ALREADY at 0 before this payment arrived
        $overflowError   = $overflow && $headroomBefore === 0;

        // ── 3. Persist inside a transaction ───────────────────────────────
        $payment = DB::transaction(function () use (
            $unit, $buyer, $data, $slot,
            $targetAmount, $actualAmount, $variance, $paymentStatus,
            $supposedNis, $actualNis, $cappedNis, $newCumNis,
            $overflow, $clearedHere
        ) {
            $payment = Payment::create([
                'unit_id'                      => $unit->id,
                'buyer_id'                     => $buyer->id,
                'installment_number'           => $slot,
                'payment_date'                 => $data['payment_date'],
                'due_date'                     => $data['due_date'] ?? null,
                'target_amount'                => $targetAmount,
                'actual_amount'                => $actualAmount,
                'variance'                     => $variance,
                'payment_status'               => $paymentStatus,
                'supposed_nis_share'           => $supposedNis,
                'actual_nis_share'             => $actualNis,
                'cumulative_nis_share'         => $newCumNis,
                'capped_nis_share'             => $cappedNis,
                'land_ceiling_overflow'        => $overflow,
                'land_cleared_on_this_payment' => $clearedHere,
                'reported_by'                  => $data['reported_by'] ?? null,
                'notes'                        => $data['notes'] ?? null,
            ]);

            // Auto-update unit status if land just cleared
            if ($clearedHere) {
                $unit->update(['status' => 'land_cleared']);
            } elseif ($unit->status === 'unpaid') {
                $unit->update(['status' => 'active']);
            }

            return $payment;
        });

        // ── 4. Return the sealed result DTO ───────────────────────────────
        return new PaymentResult(
            payment:               $payment,
            targetAmount:          $targetAmount,
            actualAmount:          $actualAmount,
            variance:              $variance,
            paymentStatus:         $paymentStatus,
            statusLabel:           $this->statusLabel($paymentStatus),
            varianceNote:          $this->varianceNote($variance),
            supposedNisShare:      $supposedNis,
            actualNisShare:        $actualNis,
            cappedNisShare:        $cappedNis,
            cumulativeNisShare:    $newCumNis,
            kavlingValue:          $kavlingValue,
            kavlingHeadroomBefore: $headroomBefore,
            kavlingHeadroomAfter:  $headroomAfter,
            landCeilingOverflow:   $overflow,
            landClearedOnThis:     $clearedHere,
            isCeilingOverflowError:$overflowError,
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // UPDATE A PAYMENT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Update an existing payment's actual_amount and/or payment_date,
     * then recompute all derived fields for this payment AND all subsequent
     * payments for the same unit (because cumulative_nis_share is a running total).
     *
     * @param Payment $payment   The payment to update
     * @param array   $data      Keys: actual_amount, payment_date (both optional)
     */
    public function updatePayment(Payment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            // Apply raw field changes first
            $payment->update(array_filter([
                'actual_amount' => isset($data['actual_amount']) ? (int) $data['actual_amount'] : null,
                'payment_date'  => $data['payment_date'] ?? null,
                'notes'         => $data['notes'] ?? null,
            ], fn($v) => $v !== null));

            // Recompute everything for this unit from slot 1
            $this->recomputeUnit($payment->unit);
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    // RECOMPUTE UNIT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Recompute all derived fields for ALL payments of a unit, in installment order.
     *
     * Call this after:
     *   - Editing any payment amount (which shifts the running cumulative_nis_share)
     *   - Deleting a payment (which closes a gap)
     *   - Editing the unit's angsuran_per_bulan (which changes target_amount baseline)
     *   - Editing the unit's luas_tanah (which changes the kavling ceiling)
     *
     * This is an O(n) pass over the unit's payments — safe for up to ~360 payments.
     */
    public function recomputeUnit(Unit $unit): void
    {
        // Always reload fresh from DB
        $unit->refresh();

        $kavlingValue   = $this->kavlingValue($unit->luas_tanah);
        $targetAmount   = $unit->angsuran_per_bulan;
        $runningCumNis  = 0;

        $payments = Payment::where('unit_id', $unit->id)
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        DB::transaction(function () use ($payments, $kavlingValue, $targetAmount, &$runningCumNis, $unit) {
            foreach ($payments as $payment) {
                $actualAmount  = $payment->actual_amount;
                $headroom      = $this->remainingKavlingHeadroom($kavlingValue, $runningCumNis);

                $variance      = $this->variance($actualAmount, $targetAmount);
                $status        = $this->paymentStatus($variance);
                $supposedNis   = $this->supposedNisShare($targetAmount);
                $actualNis     = $this->actualNisShare($actualAmount);
                $cappedNis     = $this->cappedNisShare($actualNis, $headroom);
                $runningCumNis += $cappedNis;

                $overflow      = $actualNis > $headroom;
                $clearedHere   = $overflow && $headroom > 0;

                $payment->update([
                    'target_amount'                => $targetAmount,
                    'variance'                     => $variance,
                    'payment_status'               => $status,
                    'supposed_nis_share'           => $supposedNis,
                    'actual_nis_share'             => $actualNis,
                    'capped_nis_share'             => $cappedNis,
                    'cumulative_nis_share'         => $runningCumNis,
                    'land_ceiling_overflow'        => $overflow,
                    'land_cleared_on_this_payment' => $clearedHere,
                ]);
            }

            // Sync unit status based on final state
            $isCleared  = $this->isLandCleared($kavlingValue, $runningCumNis);
            $allPaid    = $payments->count() >= $unit->max_installments;

            $newStatus = match(true) {
                $isCleared && $allPaid  => 'settled',
                $isCleared              => 'land_cleared',
                $allPaid                => 'settled',
                $payments->count() > 0  => 'active',
                default                 => 'unpaid',
            };

            // Only auto-update if not manually set to cancelled
            if ($unit->status !== 'cancelled') {
                $unit->update(['status' => $newStatus]);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    // AUDIT SUMMARY
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Build the full PaymentAuditSummary for one unit.
     * This is what the Dashboard deep-dive (Step 4) calls to render the
     * detail card + installment ledger.
     *
     * All data is read from already-computed fields — no re-math, just aggregation.
     *
     * @param Unit $unit   Must be loaded with payments relation OR will be freshly queried
     */
    public function auditSummary(Unit $unit): PaymentAuditSummary
    {
        // Load all payments ordered by slot
        $payments = Payment::where('unit_id', $unit->id)
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        // Index payments by installment_number for O(1) slot lookup
        $paymentsBySlot = $payments->keyBy('installment_number');

        // Build the full ledger: slots 1 .. max_installments
        // Each entry: Payment model (if paid) or null (if not yet paid)
        $ledgerSlots = [];
        for ($i = 1; $i <= $unit->max_installments; $i++) {
            $ledgerSlots[$i] = $paymentsBySlot->get($i); // null if not paid
        }

        // Aggregate counters
        $kavlingValue    = $this->kavlingValue($unit->luas_tanah);
        $cumulativeNis   = (int) $payments->sum('capped_nis_share');
        $totalPenerimaan = (int) $payments->sum('actual_amount');
        $sisaPenerimaan  = max(0, $unit->harga_penjualan - $unit->down_payment - $totalPenerimaan);
        $remainingHeadroom = $this->remainingKavlingHeadroom($kavlingValue, $cumulativeNis);
        $isLandCleared   = $this->isLandCleared($kavlingValue, $cumulativeNis);

        $paidCount    = $payments->count();
        $correctCount = $payments->where('payment_status', 'correct')->count();
        $underpayCount= $payments->where('payment_status', 'underpay')->count();
        $overpayCount = $payments->where('payment_status', 'overpay')->count();
        $hasOverflow  = $payments->where('land_ceiling_overflow', true)
                                  ->where('land_cleared_on_this_payment', false)
                                  ->isNotEmpty();

        // Absolute sum of all underpay variances (negative variance → absolute value)
        $totalUnderpay = (int) $payments
            ->where('payment_status', 'underpay')
            ->sum(fn($p) => abs($p->variance));

        // Sum of all overpay variances
        $totalOverpay = (int) $payments
            ->where('payment_status', 'overpay')
            ->sum('variance');

        return new PaymentAuditSummary(
            unit:                 $unit,
            payments:             $payments,
            ledgerSlots:          $ledgerSlots,
            kavlingValue:         $kavlingValue,
            totalPenerimaan:      $totalPenerimaan,
            sisaPenerimaan:       $sisaPenerimaan,
            cumulativeNis:        $cumulativeNis,
            remainingHeadroom:    $remainingHeadroom,
            isLandCleared:        $isLandCleared,
            paidCount:            $paidCount,
            remainingCount:       max(0, $unit->max_installments - $paidCount),
            correctCount:         $correctCount,
            underpayCount:        $underpayCount,
            overpayCount:         $overpayCount,
            hasOverflowError:     $hasOverflow,
            totalUnderpayAmount:  $totalUnderpay,
            totalOverpayAmount:   $totalOverpay,
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // DASHBOARD KPI AGGREGATES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Returns the four KPI values for the top Dashboard widget row.
     * All counts/sums are computed in a single DB pass for performance.
     *
     * @return array{
     *   total_houses:         int,
     *   total_unpaid:         int,
     *   total_underpaid_installments: int,
     *   total_revenue_collected: int,
     *   total_land_valuation_cap: int,
     * }
     */
    public function dashboardKpis(): array
    {
        $totalHouses = \App\Models\Unit::whereNull('deleted_at')->count();
        $totalUnpaid = \App\Models\Unit::whereNull('deleted_at')
            ->where('status', 'unpaid')->count();
        $totalUnderpaid = Payment::whereNull('deleted_at')
            ->where('payment_status', 'underpay')->count();
        $totalRevenue = (int) Payment::whereNull('deleted_at')
            ->sum('capped_nis_share');

        // Total kavling cap across all non-deleted units
        // = SUM(luas_tanah) × 4,000,000
        $totalLandCap = (int) (\App\Models\Unit::whereNull('deleted_at')
            ->sum('luas_tanah') * self::LAND_PRICE_PER_SQM);

        return [
            'total_houses'                  => $totalHouses,
            'total_unpaid'                  => $totalUnpaid,
            'total_underpaid_installments'  => $totalUnderpaid,
            'total_revenue_collected'       => $totalRevenue,
            'total_land_valuation_cap'      => $totalLandCap,
        ];
    }
}