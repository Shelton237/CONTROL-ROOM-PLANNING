<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'type',
        'reason',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isEnregistree(): bool
    {
        return $this->status === 'enregistree';
    }

    /**
     * Vrai si cette absence/permission enregistrée couvre la date ISO donnée.
     */
    public function coversDate(string $iso): bool
    {
        return $this->isEnregistree()
            && $iso >= $this->start_date->format('Y-m-d')
            && $iso <= $this->end_date->format('Y-m-d');
    }
}
