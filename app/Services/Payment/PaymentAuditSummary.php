<?php

namespace App\Services\Payment;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * PaymentAuditSummary — Unit-Level Audit Summary DTO
 * ════════════════════════════════════════════════════
 * Returned by PaymentService::auditSummary($unit).
 * Aggregates all payment data for one unit into a single, ready-to-display object.
 * Used by the Dashboard deep-dive view (Step 4) and can be used for exports.
 *
 * Built from already-loaded data — no additional DB queries inside this class.
 */
final class PaymentAuditSummary
{
    /**
     * @param Unit       $unit             The unit being summarised
     * @param Collection $payments         All Payment models for this unit, ordered by installment_number
     * @param array      $ledgerSlots      Full slot list 1..max_installments, each entry is either
     *                                     a Payment model or null (unpaid slot)
     *
     * @param int        $kavlingValue     LT × 4,000,000
     * @param int        $totalPenerimaan  Sum of actual_amount across all payments
     * @param int        $sisaPenerimaan   Contract balance remaining
     * @param int        $cumulativeNis    Sum of capped_nis_share (respects ceiling)
     * @param int        $remainingHeadroom kavlingValue - cumulativeNis (0 if cleared)
     * @param bool       $isLandCleared    true if cumulative NIS >= kavling cap
     *
     * @param int        $paidCount        Number of installments recorded
     * @param int        $remainingCount   max_installments - paidCount
     * @param int        $correctCount     Payments with status = 'correct'
     * @param int        $underpayCount    Payments with status = 'underpay'
     * @param int        $overpayCount     Payments with status = 'overpay'
     * @param bool       $hasOverflowError Any payment flagged as ceiling overflow error
     *
     * @param int        $totalUnderpayAmount  Sum of negative variances (absolute value)
     * @param int        $totalOverpayAmount   Sum of positive variances
     */
    public function __construct(
        public readonly Unit       $unit,
        public readonly Collection $payments,
        public readonly array      $ledgerSlots,

        // Financial totals
        public readonly int  $kavlingValue,
        public readonly int  $totalPenerimaan,
        public readonly int  $sisaPenerimaan,
        public readonly int  $cumulativeNis,
        public readonly int  $remainingHeadroom,
        public readonly bool $isLandCleared,

        // Installment counters
        public readonly int  $paidCount,
        public readonly int  $remainingCount,
        public readonly int  $correctCount,
        public readonly int  $underpayCount,
        public readonly int  $overpayCount,
        public readonly bool $hasOverflowError,

        // Variance aggregates
        public readonly int  $totalUnderpayAmount,
        public readonly int  $totalOverpayAmount,
    ) {}

    // ── Derived helpers used directly in Blade views ───────────────────────

    /** Kavling fill percentage (0–100), capped at 100 */
    public function kavlingFillPct(): int
    {
        if ($this->kavlingValue <= 0) return 0;
        return (int) min(100, round(($this->cumulativeNis / $this->kavlingValue) * 100));
    }

    /** Net variance across all payments (overpay - underpay) */
    public function netVariance(): int
    {
        return $this->totalOverpayAmount - $this->totalUnderpayAmount;
    }

    /** True if any installment has been recorded */
    public function hasPayments(): bool
    {
        return $this->paidCount > 0;
    }

    /** True if there are any problematic payments (under, over, or overflow) */
    public function hasIssues(): bool
    {
        return $this->underpayCount > 0
            || $this->overpayCount > 0
            || $this->hasOverflowError;
    }

    /**
     * Overall audit health badge string.
     *   "✅ Clean"   — all paid installments are correct, no overflow
     *   "⚠️ Issues"  — at least one under/overpay or overflow error
     *   "— No data" — no payments recorded yet
     */
    public function healthBadge(): string
    {
        if (!$this->hasPayments()) return '— No data';
        return $this->hasIssues() ? '⚠️ Issues' : '✅ Clean';
    }
}
