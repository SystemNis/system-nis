<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cluster extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'location',
        'notes',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /**
     * Total units registered under this cluster.
     */
    public function getTotalUnitsAttribute(): int
    {
        return $this->units()->count();
    }

    /**
     * Units with no payments recorded yet.
     */
    public function getUnpaidUnitsAttribute(): int
    {
        return $this->units()->where('status', 'unpaid')->count();
    }
}
