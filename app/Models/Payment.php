<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    // ── Mass Assignment ────────────────────────────────────────────────────

    protected $fillable = [
        'unit_id',
        'buyer_id',
        'installment_number',
        'payment_date',
        'due_date',
        'target_amount',
        'actual_amount',
        'variance',
        'payment_status',
        'supposed_nis_share',
        'actual_nis_share',
        'cumulative_nis_share',
        'capped_nis_share',
        'land_ceiling_overflow',
        'land_cleared_on_this_payment',
        'reported_by',
        'notes',
    ];

    protected $casts = [
        'payment_date'                  => 'date',
        'due_date'                      => 'date',
        'target_amount'                 => 'integer',
        'actual_amount'                 => 'integer',
        'variance'                      => 'integer',
        'supposed_nis_share'            => 'integer',
        'actual_nis_share'              => 'integer',
        'cumulative_nis_share'          => 'integer',
        'capped_nis_share'              => 'integer',
        'land_ceiling_overflow'         => 'boolean',
        'land_cleared_on_this_payment'  => 'boolean',
    ];

    public function getIsKprSlotAttribute(): bool
    {
        return ($this->slot_type ?? 'regular') === 'kpr';
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    // ── Computed Accessors ────────────────────────────────────────────────

    /**
     * Human-readable status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'correct'  => '✅ Correct',
            'underpay' => '❌ Underpay',
            'overpay'  => '⚠️ Overpay / Accelerated',
            default    => '— Unknown',
        };
    }

    /**
     * Returns the variance note shown beside the status indicator.
     * e.g., "(Short: -Rp 10,000,000)" or "(Excess: +Rp 5,000,000)"
     */
    public function getVarianceNoteAttribute(): string
    {
        return match ($this->payment_status) {
            'underpay' => sprintf('(Short: -Rp %s)', number_format(abs($this->variance), 0, ',', '.')),
            'overpay'  => sprintf('(Excess: +Rp %s)', number_format($this->variance, 0, ',', '.')),
            default    => '',
        };
    }

    /**
     * The full audit row text for NIS share comparison.
     * "NIS Share → Supposed: Rp 30,000,000 | Actually: Rp 27,000,000"
     */
    public function getNisAuditRowAttribute(): string
    {
        return sprintf(
            'NIS Share → Supposed to receive: Rp %s | Actually received: Rp %s',
            number_format($this->supposed_nis_share, 0, ',', '.'),
            number_format($this->actual_nis_share, 0, ',', '.')
        );
    }

    /**
     * Formatted payment date in Indonesian locale style.
     * e.g., "1 Januari 2024" — or "— No date" if not yet recorded.
     */
    public function getFormattedDateAttribute(): string
    {
        if (!$this->payment_date) {
            return '— No date';
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April',   5 => 'Mei',       6 => 'Juni',
            7 => 'Juli',    8 => 'Agustus',   9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return sprintf(
            '%d %s %d',
            $this->payment_date->day,
            $months[$this->payment_date->month],
            $this->payment_date->year
        );
    }

    /**
     * Whether this payment is a kavling overflow that should be flagged
     * as a "Ceiling Overflow Error" in the audit view.
     */
    public function getIsCeilingOverflowErrorAttribute(): bool
    {
        return $this->land_ceiling_overflow && !$this->land_cleared_on_this_payment;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeUnderpaid($query)
    {
        return $query->where('payment_status', 'underpay');
    }

    public function scopeOverpaid($query)
    {
        return $query->where('payment_status', 'overpay');
    }

    public function scopeCorrect($query)
    {
        return $query->where('payment_status', 'correct');
    }

    public function scopeWithCeilingOverflow($query)
    {
        return $query->where('land_ceiling_overflow', true);
    }

    public function scopeForUnit($query, int $unitId)
    {
        return $query->where('unit_id', $unitId)->orderBy('installment_number');
    }
}