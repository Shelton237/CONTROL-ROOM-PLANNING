<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomWeekLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'week_start',
        'employee_id',
    ];

    protected $casts = [
        'week_start' => 'date:Y-m-d',
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
