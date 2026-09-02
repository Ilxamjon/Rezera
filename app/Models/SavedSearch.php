<?php

namespace App\Models;

use App\Domain\SavedSearches\Enums\SavedSearchAlertChannel;
use App\Domain\SavedSearches\Enums\SavedSearchAlertFrequency;
use App\Domain\SavedSearches\Enums\SavedSearchDateMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedSearch extends Model
{
    /** @use HasFactory<\Database\Factories\SavedSearchFactory> */
    use HasFactory, HasUuids;

    protected $table = 'user_saved_searches';

    protected $fillable = [
        'user_id',
        'name',
        'search_query',
        'category_id',
        'city',
        'district',
        'region',
        'country_code',
        'latitude',
        'longitude',
        'radius_km',
        'business_id',
        'resource_category_id',
        'resource_id',
        'resource_type',
        'min_price',
        'max_price',
        'currency',
        'date_mode',
        'specific_date',
        'date_from',
        'date_to',
        'days_of_week',
        'start_time',
        'end_time',
        'availability_required',
        'alert_enabled',
        'alert_channel',
        'alert_frequency',
        'last_checked_at',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'date_mode' => SavedSearchDateMode::class,
            'alert_channel' => SavedSearchAlertChannel::class,
            'alert_frequency' => SavedSearchAlertFrequency::class,
            'specific_date' => 'date',
            'date_from' => 'date',
            'date_to' => 'date',
            'days_of_week' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_km' => 'float',
            'availability_required' => 'boolean',
            'alert_enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resourceCategory(): BelongsTo
    {
        return $this->belongsTo(ResourceGroup::class, 'resource_category_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class, 'category_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(SavedSearchAlert::class);
    }
}
