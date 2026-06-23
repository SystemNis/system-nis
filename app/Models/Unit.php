<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    // ── Constants ──────────────────────────────────────────────────────────

    /** Price per m² of land — determines the Kavling cap */
    public const LAND_PRICE_PER_SQM = 4_000_000;

    /** NIS revenue share fraction */
    public const NIS_SHARE_RATE = 0.30;

    /** Fixed set of valid stages. Used for dropdowns and validation. */
    public const TAHAP_OPTIONS = ['Tahap 1', 'Tahap 2', 'Tahap 3'];

    // ── Mass Assignment ────────────────────────────────────────────────────

    protected $fillable = [
        'tahap',
        'cluster_id',
        'block',
        'unit_number',
        'house_type',
        'luas_bangunan',
        'luas_tanah',
        'payment_type',
        'harga_penjualan',
        'down_payment',
        'angsuran_per_bulan',
        'max_installments',
        'status',
        'notes',
    ];

    /**
     * Hard identity fields — these four are the ONLY fields required to
     * create a unit. Everything else may be null and filled in later
     * (e.g. via the upcoming bulk-import feature).
     *   - tahap is intentionally NOT in this list: it's a new permanent
     *     attribute but is not yet mandatory at creation time per the
     *     current requirement.
     */
    public const REQUIRED_FIELDS = ['cluster_id', 'block', 'unit_number', 'luas_tanah'];

    protected $casts = [
        'luas_bangunan'      => 'integer',
        'luas_tanah'         => 'integer',
        'harga_penjualan'    => 'integer',
        'down_payment'       => 'integer',
        'angsuran_per_bulan' => 'integer',
        'max_installments'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(Buyer::class);
    }

    /** The currently active buyer for this unit */
    public function activeBuyer(): HasOne
    {
        return $this->hasOne(Buyer::class)->where('is_active', true)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('installment_number');
    }

    // ── Computed Accessors ────────────────────────────────────────────────

    /**
     * Kavling Value = LT × Rp 4,000,000
     * This is the PERMANENT CEILING for total NIS land share.
     * Once cumulative NIS share hits this number, the land account is capped.
     *
     * luas_tanah is one of the four hard-required fields, so this is always
     * safe to compute without a null check.
     *
     * Example: LT=75m² → Kavling = 75 × 4,000,000 = Rp 300,000,000
     */
    public function getKavlingValueAttribute(): int
    {
        return (int) $this->luas_tanah * self::LAND_PRICE_PER_SQM;
    }

    /**
     * Human-readable unit label e.g. "A-01"
     */
    public function getUnitLabelAttribute(): string
    {
        return "{$this->block}-{$this->unit_number}";
    }

    /**
     * Total amount already paid (sum of all actual_amount in payments).
     * Independent of harga_penjualan/down_payment, so always safe.
     */
    public function getTotalPenerimaanAttribute(): int
    {
        return (int) $this->payments()->sum('actual_amount');
    }

    /**
     * Remaining balance = harga_penjualan - down_payment - total_penerimaan
     *
     * harga_penjualan is now OPTIONAL. If it hasn't been filled in yet
     * (e.g. a partially-imported unit), there is no contract price to
     * measure a "remaining balance" against — return 0 rather than a
     * misleading negative or garbage number.
     */
    public function getSisaPenerimaanAttribute(): int
    {
        if ($this->harga_penjualan === null) {
            return 0;
        }

        return max(0, $this->harga_penjualan - (int) $this->down_payment - $this->total_penerimaan);
    }

    /**
     * Cumulative NIS share received so far (capped values, respecting kavling ceiling).
     * Independent of optional fields, always safe.
     */
    public function getCumulativeNisShareAttribute(): int
    {
        return (int) $this->payments()->sum('capped_nis_share');
    }

    /**
     * Remaining kavling headroom before the land ceiling is hit.
     * kavling_value is always safe (depends only on luas_tanah).
     */
    public function getRemainingKavlingHeadroomAttribute(): int
    {
        return max(0, $this->kavling_value - $this->cumulative_nis_share);
    }

    /**
     * Has this unit's land account been fully settled?
     */
    public function getIsLandClearedAttribute(): bool
    {
        return $this->cumulative_nis_share >= $this->kavling_value;
    }

    /**
     * Penerimaan Per Bulan = angsuran_per_bulan × NIS_SHARE_RATE
     * i.e., the NIS share of a perfectly on-target monthly payment.
     *
     * angsuran_per_bulan is now OPTIONAL (derived from harga_penjualan,
     * down_payment, max_installments — all optional). If unset, there is
     * no target yet, so the monthly NIS share is undefined → 0.
     */
    public function getPenerimaanPerBulanAttribute(): int
    {
        if ($this->angsuran_per_bulan === null) {
            return 0;
        }

        return (int) ($this->angsuran_per_bulan * self::NIS_SHARE_RATE);
    }

    /**
     * Count of installment slots that have been paid.
     */
    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->payments()->count();
    }

    /**
     * Count of remaining installment slots.
     *
     * max_installments is now OPTIONAL. If unset, there is no defined
     * installment plan yet, so "remaining" is meaningless → 0.
     */
    public function getRemainingInstallmentsAttribute(): int
    {
        if ($this->max_installments === null) {
            return 0;
        }

        return max(0, $this->max_installments - $this->paid_installments_count);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Recalculate and save angsuran_per_bulan from current field values.
     * Call this whenever harga_penjualan, down_payment, or max_installments change.
     *
     * Now guards against any of the three inputs being null (since they're
     * all optional) — if the plan isn't fully defined yet, leaves
     * angsuran_per_bulan as null rather than computing garbage.
     */
    public function recalculateInstallment(): void
    {
        if ($this->harga_penjualan === null || $this->max_installments === null) {
            $this->angsuran_per_bulan = null;
            return;
        }

        if ($this->max_installments > 0) {
            $this->angsuran_per_bulan = (int) round(
                ($this->harga_penjualan - (int) $this->down_payment) / $this->max_installments
            );
        }
    }
}