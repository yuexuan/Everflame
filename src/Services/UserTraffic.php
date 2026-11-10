<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class UserTraffic
{
    /** 缺失记录保持为 null，避免把留存数据缺口误显示为零用量。 */
    public static function series(array $records, string $range, DateTimeImmutable $now): array
    {
        $range = in_array($range, ['24h', '7d', '30d'], true) ? $range : '7d';
        $points = [];
        $rows = [];
        $days = $range === '30d' ? 30 : 7;
        $start = $range === '24h'
            ? $now->setTime((int) $now->format('H'), 0)->modify('-23 hours')
            : $now->setTime(0, 0)->modify('-' . ($days - 1) . ' days');
        $count = $range === '24h' ? 24 : $days;

        for ($i = 0; $i < $count; $i++) {
            $at = $start->modify('+' . $i . ($range === '24h' ? ' hours' : ' days'));
            $date = $at->format('Y-m-d');
            $usage = $records[$date] ?? null;
            $bytes = null;
            if (is_array($usage)) {
                if ($range === '24h') {
                    $value = $usage[(int) $at->format('H')] ?? null;
                    $bytes = is_numeric($value) ? max(0, (float) $value) : null;
                } else {
                    $values = array_filter($usage, 'is_numeric');
                    if ($values !== []) {
                        $bytes = array_sum(array_map(static fn ($value) => max(0, (float) $value), $values));
                    }
                }
            }
            $label = $at->format($range === '24h' ? 'm/d H:00' : 'm/d');
            $points[] = ['label' => $label, 'bytes' => $bytes];
            $rows[] = ['label' => $at->format($range === '24h' ? 'Y-m-d H:00' : 'Y-m-d'), 'bytes' => $bytes];
        }

        $total = array_sum(array_column($points, 'bytes'));
        foreach ($rows as &$row) {
            $row['percent'] = $row['bytes'] !== null && $total > 0 ? round($row['bytes'] / $total * 100, 1) : 0;
        }
        unset($row);

        return [
            'range' => $range, 'points' => $points, 'rows' => array_reverse($rows), 'total' => $total,
            'has_records' => count(array_filter($points, static fn ($point) => $point['bytes'] !== null)) > 0,
        ];
    }
}
