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

    public const LAND_PRICE_PER_SQM = 4_000_000;
    public const NIS_SHARE_RATE     = 0.30;
    public const TAHAP_OPTIONS      = ['Tahap 1', 'Tahap 2', 'Tahap 3'];
    public const REQUIRED_FIELDS    = ['cluster_id', 'block', 'unit_number', 'luas_tanah'];

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
        'uang_tanda_jadi',
        'angsuran_per_bulan',
        'max_installments',
        'status',
        'notes',
    ];

    protected $casts = [
        'luas_bangunan'      => 'integer',
        'luas_tanah'         => 'integer',
        'harga_penjualan'    => 'integer',
        'down_payment'       => 'integer',
        'uang_tanda_jadi'    => 'integer',
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

    public function activeBuyer(): HasOne
    {
        return $this->hasOne(Buyer::class)->where('is_active', true)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('installment_number');
    }

    // ── Computed Accessors ────────────────────────────────────────────────

    public function getKavlingValueAttribute(): int
    {
        return (int) $this->luas_tanah * self::LAND_PRICE_PER_SQM;
    }

    public function getUnitLabelAttribute(): string
    {
        return "{$this->block}-{$this->unit_number}";
    }

    public function getTotalPenerimaanAttribute(): int
    {
        return (int) $this->payments()->sum('actual_amount');
    }

    /**
     * Sisa Angsuran — the remaining contract balance.
     *
     * Cash Keras / Cash Bertahap:
     *   Base = Harga Penjualan − Uang Tanda Jadi
     *   Sisa = Base − total paid so far
     *   (UTJ is deducted from the contract price first; Down Payment is
     *   the first instalment, not a separate deduction)
     *
     * KPR:
     *   Base = Down Payment − Uang Tanda Jadi
     *   This represents the DP balance the buyer still owes to the developer.
     *   The KPR bank portion is handled separately (bank slot).
     *   Sisa = Base − total paid so far
     */
    public function getSisaPenerimaanAttribute(): int
    {
        if ($this->harga_penjualan === null) {
            return 0;
        }

        $utj = (int) $this->uang_tanda_jadi;

        if ($this->payment_type === 'KPR') {
            // For KPR: developer only collects the DP portion from the buyer
            $base = (int) $this->down_payment - $utj;
        } else {
            // Cash Keras / Cash Bertahap: UTJ cuts the contract price
            $base = $this->harga_penjualan - $utj;
        }

        return max(0, $base - $this->total_penerimaan);
    }

    public function getCumulativeNisShareAttribute(): int
    {
        return (int) $this->payments()->sum('capped_nis_share');
    }

    public function getRemainingKavlingHeadroomAttribute(): int
    {
        return max(0, $this->kavling_value - $this->cumulative_nis_share);
    }

    public function getIsLandClearedAttribute(): bool
    {
        return $this->cumulative_nis_share >= $this->kavling_value;
    }

    public function getPenerimaanPerBulanAttribute(): int
    {
        if ($this->angsuran_per_bulan === null) {
            return 0;
        }
        return (int) ($this->angsuran_per_bulan * self::NIS_SHARE_RATE);
    }

    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->payments()->count();
    }

    public function getRemainingInstallmentsAttribute(): int
    {
        if ($this->max_installments === null) {
            return 0;
        }
        return max(0, $this->max_installments - $this->paid_installments_count);
    }

    /**
     * Total installment slots available for this unit.
     * KPR gets one extra slot (the KPR bank disbursement record).
     */
    public function getTotalSlotsAttribute(): int
    {
        $base = (int) $this->max_installments;
        return $this->payment_type === 'KPR' ? $base + 1 : $base;
    }
}