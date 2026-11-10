<?php

declare(strict_types=1);

use App\Models\Node;
use App\Services\DB;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/.config.php';
date_default_timezone_set($_ENV['timeZone']);
DB::init();

// 显式幂等绑定：php scripts/ef-provision.php <agent> <node:inbound,...>
if ($argc !== 3 || ! preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $argv[1])) {
    throw new RuntimeException('Usage: ef-provision.php <agent-id> <node:inbound,...>');
}
$db = DB::connection();
foreach (explode(';', file_get_contents(dirname(__DIR__) . '/db/ef-agent.sql')) as $sql) {
    if (trim($sql) !== '') {
        $db->statement($sql);
    }
}
$agentId = $argv[1];
// 多个逻辑线路可以共用一个 SS 监听端口，并分别进行身份验证。
foreach ($db->select('SHOW INDEX FROM ef_bindings') as $index) {
    if ($index->Key_name === 'agent_inbound' && (int) $index->Non_unique === 0) {
        $db->statement('ALTER TABLE ef_bindings DROP INDEX agent_inbound, ADD INDEX agent_inbound (agent_id,inbound_id)');
        break;
    }
}
$secretPath = dirname(__DIR__) . '/storage/ef-' . $agentId . '.json';
if (! is_file($secretPath)) {
    $secrets = ['agent_id' => $agentId, 'url' => $_ENV['baseUrl'],
        'token' => bin2hex(random_bytes(32)), 'observer_token' => bin2hex(random_bytes(32))];
    file_put_contents($secretPath, json_encode($secrets, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($secretPath, 0600);
}
$secrets = json_decode(file_get_contents($secretPath), true, 512, JSON_THROW_ON_ERROR);
$db->transaction(static function () use ($db, $secrets, $agentId, $argv): void {
    if ($db->table('ef_agents')->where('id', '!=', $agentId)->exists()) {
        throw new RuntimeException('Shared budgets currently require a single agent');
    }
    $db->table('ef_agents')->insertOrIgnore(['id' => $agentId, 'token_hash' => hash('sha256', $secrets['token']),
        'observer_hash' => hash('sha256', $secrets['observer_token']), 'error' => '等待首次同步']);
    foreach (explode(',', $argv[2]) as $mapping) {
        if (! preg_match('/^(\d+):(\d+)$/D', $mapping, $m)) {
            throw new RuntimeException('Invalid explicit mapping');
        }
        $node = Node::findOrFail((int) $m[1]);
        if ($node->is_dynamic_rate) {
            throw new RuntimeException('Configure a fixed tariff before migration');
        }
        $config = json_decode($node->custom_config, true, 512, JSON_THROW_ON_ERROR);
        $source = $config['external_shadowsocks'] ?? $config['managed_shadowsocks'] ?? null;
        if (! $source) {
            throw new RuntimeException('Expected imported or managed SS node');
        }
        $db->table('ef_bindings')->insertOrIgnore(['agent_id' => $agentId, 'inbound_id' => (int) $m[2],
            'node_id' => $node->id, 'probe_password' => bin2hex(random_bytes(24))]);
        $b = $db->table('ef_bindings')->where('node_id', $node->id)->first();
        if ($b->agent_id !== $agentId) {
            throw new RuntimeException('Node is already bound to a different agent');
        }
        $db->table('ef_bindings')->where('id', $b->id)->update(['inbound_id' => (int) $m[2]]);
        if ((int) $b->tariff_id === 0) {
            $rate = (int) round($node->traffic_rate * 1000000);
            if ($rate < 0 || $rate > 100000000) {
                throw new RuntimeException('Tariff outside supported range');
            }
            $tariff = $db->table('ef_tariffs')->insertGetId(['binding_id' => $b->id, 'rate_ppm' => $rate, 'effective_at' => time()]);
            $db->table('ef_bindings')->where('id', $b->id)->update(['tariff_id' => $tariff]);
        }
        // 仅停用同一导入源的重复展示记录。
        if (isset($source['source_key'])) {
            foreach (Node::where('id', '!=', $node->id)->get() as $duplicate) {
                $ext = json_decode($duplicate->custom_config, true)['external_shadowsocks'] ?? [];
                if (($ext['source_key'] ?? '') === $source['source_key']) {
                    $duplicate->type = 0;
                    $duplicate->save();
                }
            }
        }
        $config['managed_shadowsocks'] = ['binding_id' => (int) $b->id,
            'port' => (int) $source['port'], 'method' => $source['method'], 'outbound' => $source['outbound'] ?? ''];
        unset($config['external_shadowsocks']);
        $node->custom_config = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $node->save();
    }
});
echo json_encode(['agent_id' => $agentId, 'bindings' => $db->table('ef_bindings')->where('agent_id', $agentId)->pluck('id'),
    'secrets_file' => $secretPath], JSON_THROW_ON_ERROR), PHP_EOL;
