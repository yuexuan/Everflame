<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MonthlyTrafficReset;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/MonthlyTrafficReset.php';

final class MonthlyTrafficResetTest extends TestCase
{
    public function testMonthlyBoundaryUsesConfiguredLocalDate(): void
    {
        $zone = new DateTimeZone('Asia/Shanghai');
        self::assertSame('2026-11', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-11-30 23:59:59', $zone), 1));
        self::assertSame('2026-12', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-12-01 00:00:00', $zone), 1));
    }

    public function testAnniversaryDoesNotResetEarlyAndRecoversAfterDowntime(): void
    {
        self::assertSame('2026-10', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-11-07'), 8));
        self::assertSame('2026-11', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-11-08'), 8));
        self::assertSame('2026-11', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-11-15'), 8));
    }

    public function testShortMonthsClampResetDate(): void
    {
        self::assertSame('2026-01', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-02-27'), 31));
        self::assertSame('2026-02', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2026-02-28'), 31));
        self::assertSame('2028-01', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2028-02-28'), 31));
        self::assertSame('2028-02', MonthlyTrafficReset::periodAt(new DateTimeImmutable('2028-02-29'), 31));
    }
}
