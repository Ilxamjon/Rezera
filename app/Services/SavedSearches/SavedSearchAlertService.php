<?php

namespace App\Services\SavedSearches;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\SavedSearches\Enums\SavedSearchAlertChannel;
use App\Models\SavedSearch;
use App\Models\SavedSearchAlert;
use App\Services\Notifications\NotificationService;
use App\Support\SavedSearches\SavedSearchMatch;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SavedSearchAlertService
{
    public function __construct(
        private readonly SavedSearchMatcher $matcher,
        private readonly NotificationService $notifications,
    ) {}

    public function evaluate(SavedSearch $savedSearch, bool $ignoreFrequency = false): int
    {
        if (! $savedSearch->alert_enabled || ! $savedSearch->availability_required) {
            return 0;
        }

        if (! $ignoreFrequency && ! $this->matcher->isDueForScheduledProcessing($savedSearch)) {
            return 0;
        }

        $savedSearch->forceFill(['last_checked_at' => now()])->save();

        $notified = 0;

        foreach ($this->matcher->findMatches($savedSearch) as $match) {
            if ($this->notifyMatch($savedSearch, $match)) {
                $notified++;
            }
        }

        return $notified;
    }

    public function notifyMatch(SavedSearch $savedSearch, SavedSearchMatch $match): bool
    {
        $user = $savedSearch->user;

        if ($user === null) {
            return false;
        }

        $matchHash = $match->hash();

        try {
            return (bool) DB::transaction(function () use ($savedSearch, $match, $matchHash, $user): bool {
                $alert = SavedSearchAlert::query()->create([
                    'saved_search_id' => $savedSearch->id,
                    'user_id' => $user->id,
                    'business_id' => $match->businessId,
                    'resource_id' => $match->resourceId,
                    'start_at' => $match->startAt,
                    'end_at' => $match->endAt,
                    'match_hash' => $matchHash,
                    'status' => 'sent',
                    'notified_at' => now(),
                    'created_at' => now(),
                ]);

                $timezone = $savedSearch->business?->timezone
                    ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

                $notification = $this->notifications->notifyUser(
                    user: $user,
                    type: NotificationType::AvailabilityAlert,
                    entityKey: $savedSearch->id.'|'.$matchHash,
                    placeholders: [
                        'business_name' => $match->businessName,
                        'resource_name' => $match->resourceName,
                        'date' => $match->startAt->timezone($timezone)->toDateString(),
                        'start_time' => $match->startAt->timezone($timezone)->format('H:i'),
                        'end_time' => $match->endAt->timezone($timezone)->format('H:i'),
                    ],
                    data: [
                        'saved_search_id' => $savedSearch->id,
                        'business_id' => $match->businessId,
                        'business_name' => $match->businessName,
                        'resource_id' => $match->resourceId,
                        'resource_name' => $match->resourceName,
                        'start_at' => $match->startAt->toIso8601String(),
                        'end_at' => $match->endAt->toIso8601String(),
                        'deep_link' => $this->buildDeepLink($match),
                    ],
                    channels: $this->resolveChannels($savedSearch),
                );

                if ($notification !== null) {
                    $alert->update(['notification_id' => $notification->id]);
                }

                $savedSearch->forceFill(['last_notified_at' => now()])->save();

                return true;
            });
        } catch (UniqueConstraintViolationException|QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                return false;
            }

            Log::warning('saved_search.alert_failed', [
                'saved_search_id' => $savedSearch->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function invalidateMatchState(SavedSearch $savedSearch): void
    {
        SavedSearchAlert::query()
            ->where('saved_search_id', $savedSearch->id)
            ->delete();

        $savedSearch->forceFill([
            'last_checked_at' => null,
            'last_notified_at' => null,
        ])->save();
    }

  /**
     * @return list<\App\Domain\Notifications\Enums\NotificationChannel>|null
     */
    private function resolveChannels(SavedSearch $savedSearch): ?array
    {
        $channel = $savedSearch->alert_channel ?? SavedSearchAlertChannel::InApp;

        return [$channel->toNotificationChannel()];
    }

    private function buildDeepLink(SavedSearchMatch $match): string
    {
        $date = $match->startAt->toDateString();
        $start = $match->startAt->format('H:i');

        return sprintf(
            'rezera://business/%s/availability?resource=%s&date=%s&start=%s',
            $match->businessId,
            $match->resourceId,
            $date,
            $start,
        );
    }
}
