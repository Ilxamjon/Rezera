<?php

namespace Tests\Unit\Support\Time;

use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TimeIntervalTest extends TestCase
{
    private const TZ = 'Asia/Tashkent';

    #[DataProvider('overlapProvider')]
    public function test_overlap_detection(string $existingStart, string $existingEnd, string $requestStart, string $requestEnd, bool $expected): void
    {
        $date = CarbonImmutable::parse('2026-09-05', self::TZ);

        $existing = TimeInterval::fromLocalTimes($date, $existingStart, $existingEnd, self::TZ);
        $requested = TimeInterval::fromLocalTimes($date, $requestStart, $requestEnd, self::TZ);

        $this->assertSame($expected, $existing->overlaps($requested));
    }

    /**
     * @return array<string, array{string, string, string, string, bool}>
     */
    public static function overlapProvider(): array
    {
        return [
            'adjacent after' => ['18:00', '20:00', '20:00', '22:00', false],
            'adjacent before' => ['18:00', '20:00', '17:00', '18:00', false],
            'partial overlap' => ['18:00', '20:00', '19:00', '21:00', true],
            'exact match' => ['18:00', '20:00', '18:00', '20:00', true],
            'contains existing' => ['18:00', '20:00', '17:00', '21:00', true],
            'contained by existing' => ['18:00', '20:00', '18:30', '19:30', true],
        ];
    }

    public function test_overnight_request_interval(): void
    {
        $date = CarbonImmutable::parse('2026-09-05', self::TZ);
        $interval = TimeInterval::fromLocalTimes($date, '23:00', '01:00', self::TZ);

        $this->assertSame('2026-09-05', $interval->start->format('Y-m-d'));
        $this->assertSame('23:00', $interval->start->format('H:i'));
        $this->assertSame('2026-09-06', $interval->end->format('Y-m-d'));
        $this->assertSame('01:00', $interval->end->format('H:i'));
    }
}
