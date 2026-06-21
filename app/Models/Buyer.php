<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Buyer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'name',
        'id_number',
        'phone',
        'email',
        'address',
        'contract_date',
        'handover_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'contract_date'  => 'date',
        'handover_date'  => 'date',
        'is_active'      => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('installment_number');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /**
     * Total actual amount paid by this buyer.
     */
    public function getTotalPaidAttribute(): int
    {
        return (int) $this->payments()->sum('actual_amount');
    }

    /**
     * Count of payments made.
     */
    public function getPaymentCountAttribute(): int
    {
        return $this->payments()->count();
    }
}
