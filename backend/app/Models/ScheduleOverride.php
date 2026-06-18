<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'week_start',
        'employee_id',
        'day_index',
        'value',
    ];

    protected $casts = [
        'week_start' => 'date:Y-m-d',
        'day_index' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
