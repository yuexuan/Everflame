<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/** 所有时间窗口均采用应用时区，并遵循 [开始时间, 结束时间) 左闭右开规则。 */
final class TrafficWindow
{
    public static function months(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $first = $date->modify('first day of this month')->modify($months . ' months');
        return $first->setDate((int) $first->format('Y'), (int) $first->format('m'),
            min((int) $date->format('d'), (int) $first->format('t')));
    }

    public static function resolve(string $range, DateTimeImmutable $now, ?string $from = null, ?string $to = null): array
    {
        $today = $now->setTime(0, 0);
        $end = $now;
        $hourly = in_array($range, ['24h', 'today', 'yesterday'], true);
        switch ($range) {
            case '24h': $start = $now->modify('-24 hours'); $label = '近24小时'; break;
            case 'today': $start = $today; $label = '今天'; break;
            case 'yesterday': $start = $today->modify('-1 day'); $end = $today; $label = '昨天'; break;
            case '3m':
            case '6m':
                $start = self::months($today->modify('+1 day'), $range === '3m' ? -3 : -6);
                $label = $range === '3m' ? '近3个月' : '近半年';
                break;
            case 'custom':
                $parse = static function (?string $value) use ($now): DateTimeImmutable {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value ?? '', $now->getTimezone());
                    if (! $date || $date->format('Y-m-d') !== $value) {
                        throw new InvalidArgumentException('请选择有效的开始和结束日期。');
                    }
                    return $date;
                };
                $start = $parse($from);
                $last = $parse($to);
                if ($last < $start || $last > $today) {
                    throw new InvalidArgumentException('结束日期不能早于开始日期，也不能晚于今天。');
                }
                $end = $last->modify('+1 day');
                if ($end > self::months($start, 6)) {
                    throw new InvalidArgumentException('单次查询最多6个自然月，请缩短日期范围。');
                }
                $end = min($end, $now);
                $hourly = $from === $to;
                $label = '自定义';
                break;
            case '30d': $start = $today->modify('-29 days'); $label = '近30天'; break;
            default: $range = '7d'; $start = $today->modify('-6 days'); $label = '近7天';
        }
        $last = $end > $start ? $end->modify('-1 second') : $start;
        return ['range' => $range, 'label' => $label, 'start' => $start, 'end' => $end,
            'start_date' => $start->format('Y-m-d'), 'end_date' => $last->format('Y-m-d'),
            'hourly' => $hourly, 'description' => $start->format('Y-m-d H:i') . ' 至 ' . $end->format('Y-m-d H:i')];
    }
}
