<?php

declare(strict_types=1);

use App\Services\DB;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/.config.php';
date_default_timezone_set($_ENV['timeZone']);
DB::init();
if ($argc !== 3 || ! in_array($argv[2], ['status', 'billing-on', 'freeze', 'resume'], true)) {
    throw new RuntimeException('Usage: ef-control.php <agent-id> status|billing-on|freeze|resume');
}
$db = DB::connection();
$db->transaction(static function () use ($db, $argv): void {
    $q = $db->table('ef_agents')->where('id', $argv[1]);
    $agent = $q->lockForUpdate()->first();
    if (! $agent) {
        throw new RuntimeException('Unknown agent');
    }
    if ($argv[2] === 'billing-on' && (int) $agent->billing_start === 0) {
        if ($agent->core_status !== 'running' || $agent->last_seen < time() - 90 || $agent->error !== '') {
            throw new RuntimeException('Agent must be healthy before billing activation');
        }
        if (! $db->table('ef_usage')->where('agent_id', $agent->id)->exists()) {
            throw new RuntimeException('Verify shadow usage before activating billing');
        }
        // 生效前预留时间，让 Agent 拉取新的启用时间边界。
        $q->update(['billing_start' => time() + 30]);
    }
    if ($argv[2] === 'freeze' || $argv[2] === 'resume') {
        $q->update(['enabled' => $argv[2] === 'resume' ? 1 : 0]);
    }
    $agent = $q->first();
    echo json_encode(['agent_id' => $agent->id, 'enabled' => (bool) $agent->enabled,
        'billing_start' => (int) $agent->billing_start,
        'billing_time' => $agent->billing_start ? date(DATE_ATOM, $agent->billing_start) : null,
        'last_seen' => (int) $agent->last_seen, 'core_status' => $agent->core_status, 'error' => $agent->error], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
});
