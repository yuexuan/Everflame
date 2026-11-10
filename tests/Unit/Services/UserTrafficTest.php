<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UserTraffic;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/UserTraffic.php';

final class UserTrafficTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-08 01:35:00', new DateTimeZone('Asia/Shanghai'));
    }

    public function testRollingHoursCrossMidnightWithoutIncludingFutureHours(): void
    {
        $yesterday = array_fill(0, 24, 0);
        $yesterday[23] = 1024;
        $today = array_fill(0, 24, 0);
        $today[0] = 2048;
        $today[1] = 512;
        $today[2] = 99999;
        $result = UserTraffic::series(['2026-11-07' => $yesterday, '2026-11-08' => $today], '24h', $this->now());
        self::assertCount(24, $result['points']);
        self::assertSame('11/07 02:00', $result['points'][0]['label']);
        self::assertSame('11/08 01:00', $result['points'][23]['label']);
        self::assertEquals(3584, $result['total']);
        self::assertEquals(512, $result['rows'][0]['bytes']);
    }

    public function testDailyTotalsPreserveMissingRecordsAndRealZeroUsage(): void
    {
        $result = UserTraffic::series(['2026-11-07' => array_fill(0, 24, 0),
            '2026-11-08' => [512, 1024]], '7d', $this->now());
        self::assertCount(7, $result['points']);
        self::assertNull($result['points'][0]['bytes']);
        self::assertEquals(0, $result['points'][5]['bytes']);
        self::assertEquals(1536, $result['points'][6]['bytes']);
        self::assertEquals(100, $result['rows'][0]['percent']);
        self::assertTrue($result['has_records']);
    }

    public function testThirtyDayWindowCrossesMonthsAndExcludesOlderRecords(): void
    {
        $result = UserTraffic::series(['2026-10-09' => [4096], '2026-10-10' => [1024]], '30d', $this->now());
        self::assertCount(30, $result['points']);
        self::assertSame('10/10', $result['points'][0]['label']);
        self::assertEquals(1024, $result['total']);
    }

    public function testInvalidRangeAndMalformedDataReturnSafeEmptyState(): void
    {
        $result = UserTraffic::series(['2026-11-07' => null, '2026-11-08' => ['bad']], '../users', $this->now());
        self::assertSame('7d', $result['range']);
        self::assertFalse($result['has_records']);
        self::assertEquals(0, $result['total']);
        self::assertNull($result['rows'][0]['bytes']);
    }
}
