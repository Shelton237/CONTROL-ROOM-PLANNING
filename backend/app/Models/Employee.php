<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'name',
        'email',
        'type',
        'offset',
        'binome',
        'day_spec',
        'alt_parity',
    ];

    protected $casts = [
        'day_spec' => 'array',
        'offset' => 'integer',
        'binome' => 'integer',
        'alt_parity' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function isRotation(): bool
    {
        return $this->type === 'rotation';
    }

    public function isFixedDay(): bool
    {
        return $this->type === 'fixed_day';
    }
}
