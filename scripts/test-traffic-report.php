<?php

declare(strict_types=1);

use App\Services\NodeTraffic;
use App\Services\TrafficWindow;

require dirname(__DIR__) . '/src/Services/TrafficWindow.php';
require dirname(__DIR__) . '/src/Services/NodeTraffic.php';
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function rejects(string $start, string $end, DateTimeImmutable $now): void
{
    try {
        TrafficWindow::resolve('custom', $now, $start, $end);
    } catch (InvalidArgumentException) {
        check(true, 'Invalid custom window rejected');
        return;
    }
    check(false, "Invalid custom window accepted: $start $end");
}
$now = new DateTimeImmutable('2026-11-09 01:35:00', new DateTimeZone('Asia/Shanghai'));
$today = TrafficWindow::resolve('today', $now);
$yesterday = TrafficWindow::resolve('yesterday', $now);
$rolling = TrafficWindow::resolve('24h', $now);
check($today['start']->format('Y-m-d H:i') === '2026-11-09 00:00', 'Today starts at midnight');
check($today['end'] == $now && $today['hourly'], 'Today ends now, hourly');
check($yesterday['start']->format('Y-m-d H:i') === '2026-11-08 00:00', 'Yesterday starts at its midnight');
check($yesterday['end']->format('Y-m-d H:i') === '2026-11-09 00:00' && $yesterday['hourly'], 'Yesterday ends at next midnight, hourly');
check($rolling['end']->getTimestamp() - $rolling['start']->getTimestamp() === 86400, 'Rolling 24h exact duration');
check($rolling['start'] < $today['start'] && $rolling['start'] > $yesterday['start'], 'Rolling differs from today and yesterday');
check(count(NodeTraffic::report([], $today)['points']) === 2, 'Today never includes future hours');
check(count(NodeTraffic::report([], $yesterday)['points']) === 24, 'Yesterday contains all 24 hours');
check(count(NodeTraffic::report([], TrafficWindow::resolve('today', $now->setTime(0, 0)))['points']) === 1, 'Midnight empty window safe');
foreach (['7d' => 7, '30d' => 30] as $range => $count) {
    check(count(NodeTraffic::report([], TrafficWindow::resolve($range, $now))['points']) === $count, 'Daily range count');
}
check(TrafficWindow::months(new DateTimeImmutable('2026-08-31'), 6)->format('Y-m-d') === '2027-02-28', 'Month end clamps');
check(TrafficWindow::months(new DateTimeImmutable('2024-08-31'), -6)->format('Y-m-d') === '2024-02-29', 'Leap year clamps');
check(TrafficWindow::resolve('custom', $now, '2026-05-01', '2026-10-31')['end_date'] === '2026-10-31', 'Full six calendar months accepted');
rejects('2026-05-01', '2026-11-01', $now);
rejects('2026-02-30', '2026-03-01', $now);
rejects('2026-11-10', '2026-11-11', $now);
rejects('2026-11-08', '2026-11-07', $now);
rejects('garbage', '2026-11-08', $now);
check(TrafficWindow::resolve('custom', $now, '2026-11-08', '2026-11-08')['hourly'], 'Custom single day hourly');
check(TrafficWindow::resolve('invalid', $now)['range'] === '7d', 'Invalid range falls back safely');
foreach (['3m', '6m'] as $range) {
    $window = TrafficWindow::resolve($range, $now);
    check($window['end'] <= TrafficWindow::months($window['start'], 6), 'Preset respects six-month maximum');
    check(! $window['hourly'], 'Long range daily');
}
$hour = $today['start']->getTimestamp();
$rows = [
    ['identity' => 'ef:1', 'node_id' => 1, 'name' => '节点 A', 'hour_at' => $hour, 'bytes' => 100],
    ['identity' => 'node:1', 'node_id' => 1, 'name' => '节点 A', 'hour_at' => $hour + 3600, 'bytes' => 200],
    ['identity' => 'ef:2', 'node_id' => 2, 'name' => '节点 B', 'hour_at' => $hour, 'bytes' => 100],
    ['identity' => 'ef:3', 'node_id' => 3, 'name' => '节点 C', 'hour_at' => $hour, 'bytes' => 0],
    ['identity' => 'ef:1', 'node_id' => 1, 'name' => '节点 A', 'hour_at' => $hour - 3600, 'bytes' => 999],
];
$report = NodeTraffic::report($rows, $today);
check($report['total'] === 400 && array_sum(array_column($report['points'], 'bytes')) === 400, 'Trend sum equals node total');
check($report['nodes'][0]['name'] === '节点 A' && $report['nodes'][0]['bytes'] === 300, 'Same node merged and sorted');
check($report['nodes'][0]['percent'] === 75.0, 'Percentage uses period total');
check($report['nodes'][2]['bytes'] === 0 && $report['has_records'], 'True zero retained');
check(! NodeTraffic::report([], $today)['has_records'], 'Empty data explicit');
check(NodeTraffic::report([], $today)['points'][0]['bytes'] === null, 'Missing data not fabricated as zero');
$rows[0]['node_id'] = null; $rows[0]['name'] = null;
check(str_contains(NodeTraffic::report([$rows[0]], $today)['nodes'][0]['name'], '已删除节点'), 'Deleted node retains usage');
echo "$checks traffic window and aggregation checks passed.\n";
