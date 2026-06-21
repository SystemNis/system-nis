<?php

namespace App\Services\Payment;

use App\Models\Payment;

/**
 * PaymentResult — Immutable Value Object
 * ═══════════════════════════════════════
 * Returned by PaymentService::record() after processing a single payment.
 * Contains the saved Payment model plus all derived audit values so the
 * caller (Livewire component, controller, job) never has to re-derive math.
 *
 * All properties are readonly — this object is a sealed audit receipt.
 */
final class PaymentResult
{
    public function __construct(
        // The persisted Eloquent model
        public readonly Payment $payment,

        // ── Core amounts ──────────────────────────────────────────────────
        public readonly int    $targetAmount,
        public readonly int    $actualAmount,
        public readonly int    $variance,

        // ── Status ────────────────────────────────────────────────────────
        public readonly string $paymentStatus,   // 'correct' | 'underpay' | 'overpay'
        public readonly string $statusLabel,     // "✅ Correct" etc.
        public readonly string $varianceNote,    // "(Short: -Rp 10.000.000)" etc.

        // ── NIS Share ─────────────────────────────────────────────────────
        public readonly int    $supposedNisShare,    // target × 0.30
        public readonly int    $actualNisShare,      // actual × 0.30
        public readonly int    $cappedNisShare,      // after kavling ceiling
        public readonly int    $cumulativeNisShare,  // running total for this unit after this payment

        // ── Kavling (Land Ceiling) ─────────────────────────────────────────
        public readonly int    $kavlingValue,         // permanent cap for this unit
        public readonly int    $kavlingHeadroomBefore,// headroom BEFORE this payment
        public readonly int    $kavlingHeadroomAfter, // headroom AFTER this payment
        public readonly bool   $landCeilingOverflow,  // did this payment breach the cap?
        public readonly bool   $landClearedOnThis,    // did THIS payment hit the ceiling exactly?

        // ── Ceiling Overflow Error flag ───────────────────────────────────
        // True when APP tried to report a payment on a unit whose kavling is
        // already fully settled — the capped_nis_share is 0, money was wasted.
        public readonly bool   $isCeilingOverflowError,
    ) {}

    // ── Convenience helpers for views ─────────────────────────────────────

    public function isCorrect(): bool  { return $this->paymentStatus === 'correct'; }
    public function isUnderpay(): bool { return $this->paymentStatus === 'underpay'; }
    public function isOverpay(): bool  { return $this->paymentStatus === 'overpay'; }

    /**
     * The full NIS audit row string for the installment ledger.
     * "NIS Share → Supposed to receive: Rp 30.000.000 | Actually received: Rp 27.000.000"
     */
    public function nisAuditRow(): string
    {
        return sprintf(
            'NIS Share → Supposed to receive: Rp %s | Actually received: Rp %s',
            number_format($this->supposedNisShare, 0, ',', '.'),
            number_format($this->actualNisShare,   0, ',', '.'),
        );
    }

    /**
     * Full line for a ceiling overflow error — shown in red in the audit view.
     */
    public function ceilingOverflowMessage(): string
    {
        if (!$this->isCeilingOverflowError) {
            return '';
        }
        return sprintf(
            '🚨 Ceiling Overflow Error — Kavling already cleared. '
            . 'Rp %s of NIS share was reported but NOT routed to land account.',
            number_format($this->actualNisShare - $this->cappedNisShare, 0, ',', '.'),
        );
    }
}
