<?php

declare(strict_types=1);

namespace App\Services;

/** 仅统计原始传输字节数，不在此报表中应用计费倍率。 */
final class NodeTraffic
{
    public static function buckets(int $userId, array $window): array
    {
        $db = DB::connection();
        $start = $window['start']->getTimestamp();
        $end = $window['end']->getTimestamp();
        $rows = [];
        if ($db->getSchemaBuilder()->hasTable('ef_usage')) {
            // 与 EfSync 使用相同的发生时间规则，结束时间不包含在区间内。
            $at = 'GREATEST(e.started_at, e.ended_at - 1)';
            $rows = $db->table('ef_usage as e')
                ->leftJoin('ef_bindings as b', 'b.id', '=', 'e.binding_id')
                ->leftJoin('node as n', 'n.id', '=', 'b.node_id')
                ->where('e.user_id', $userId)->where('e.charged', 1)
                ->where('e.ended_at', '>=', $start)->where('e.ended_at', '<=', $end + 1)
                ->whereRaw("$at >= ? AND $at < ?", [$start, $end])
                ->selectRaw("CONCAT('ef:', e.binding_id) as identity, b.node_id, n.name, FLOOR($at / 3600) * 3600 as hour_at, SUM(e.raw_u + e.raw_d) as bytes")
                ->groupBy('e.binding_id', 'b.node_id', 'n.name', 'hour_at')->get()->all();
        }
        if ($db->getSchemaBuilder()->hasTable('node_hourly_usage')) {
            $legacy = $db->table('node_hourly_usage as u')->leftJoin('node as n', 'n.id', '=', 'u.node_id')
                ->where('u.user_id', $userId)->where('u.hour_at', '>=', intdiv($start, 3600) * 3600)
                ->where('u.hour_at', '<', $end)
                ->selectRaw("CONCAT('node:', u.node_id) as identity, u.node_id, n.name, u.hour_at, u.bytes")->get()->all();
            $rows = array_merge($rows, $legacy);
        }
        return array_map(static fn ($row) => (array) $row, $rows);
    }

    /** 通过原子递增避免并发 WebAPI 上报相互覆盖。 */
    public static function record(int $userId, int $nodeId, int $bytes): void
    {
        if ($userId <= 0 || $bytes < 0) {
            return;
        }
        DB::connection()->statement('INSERT INTO node_hourly_usage (user_id, node_id, hour_at, bytes) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE bytes = bytes + VALUES(bytes)', [$userId, $nodeId, intdiv(time(), 3600) * 3600, $bytes]);
    }

    public static function report(array $buckets, array $window): array
    {
        $hourly = $window['hourly'];
        $start = $window['start'];
        $cursor = $hourly ? $start->setTime((int) $start->format('H'), 0) : $start->setTime(0, 0);
        $points = [];
        do {
            $key = $cursor->format($hourly ? 'Y-m-d H' : 'Y-m-d');
            $points[$key] = ['label' => $cursor->format($hourly ? 'm/d H:00' : 'm/d'), 'bytes' => null];
            $cursor = $cursor->modify($hourly ? '+1 hour' : '+1 day');
        } while ($cursor < $window['end']);
        $nodes = [];
        foreach ($buckets as $row) {
            $at = $start->setTimestamp((int) $row['hour_at']);
            $key = $at->format($hourly ? 'Y-m-d H' : 'Y-m-d');
            if (! isset($points[$key]) || ! is_numeric($row['bytes']) || $row['bytes'] < 0) {
                continue;
            }
            $bytes = (int) $row['bytes'];
            $points[$key]['bytes'] = ($points[$key]['bytes'] ?? 0) + $bytes;
            $id = $row['node_id'] !== null ? 'node:' . $row['node_id'] : $row['identity'];
            $nodes[$id] ??= ['name' => $row['name'] ?? ('已删除节点（' . $id . '）'), 'bytes' => 0];
            $nodes[$id]['bytes'] += $bytes;
        }
        $total = array_sum(array_column($nodes, 'bytes'));
        usort($nodes, static fn ($a, $b) => ($b['bytes'] <=> $a['bytes']) ?: strcmp($a['name'], $b['name']));
        foreach ($nodes as &$node) {
            $node['percent'] = $total > 0 ? round($node['bytes'] / $total * 100, 1) : 0;
        }
        unset($node);
        return ['points' => array_values($points), 'nodes' => $nodes, 'total' => $total,
            'has_records' => $nodes !== [], 'range' => $window['range']];
    }
}
