<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHour extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessHourFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'weekday',
        'is_closed',
        'is_open_24h',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_closed' => 'boolean',
            'is_open_24h' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
