<?php

declare(strict_types=1);

use App\Models\Node;
use App\Models\User;
use App\Services\DB;
use App\Services\Subscribe\MihomoProfile;
use App\Services\Subscribe\ShadowsocksCredentials;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/.config.php';

if ($argc < 3 || $argc > 4 || ($argc === 4 && $argv[3] !== '--apply')) {
    fwrite(STDERR, "Usage: php scripts/import-slinx.php <slinx-clash.yaml> <owner-user-id> [--apply]\n");
    exit(1);
}

try {
    $input = yaml_parse_file($argv[1]);
    if (! is_array($input) || ! is_array($input['proxies'] ?? null) || $input['proxies'] === []
        || ($input['proxy-providers'] ?? []) !== []) {
        throw new RuntimeException('Expected concrete proxies from a SLINX Clash export.');
    }
    $ownerId = filter_var($argv[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($ownerId === false) {
        throw new RuntimeException('A positive owner user ID is required.');
    }
    // 写入数据库前校验全部字段；不得静默丢弃传输配置。
    $options = require dirname(__DIR__) . '/config/profiles/mihomo.php';
    MihomoProfile::build($input['proxies'], $options);
    $prepared = [];
    foreach ($input['proxies'] as $priority => $proxy) {
        if (($proxy['type'] ?? '') !== 'ss' || array_diff(array_keys($proxy), [
            'name', 'type', 'server', 'port', 'cipher', 'password', 'udp', 'plugin', 'plugin-opts',
        ]) !== [] || ! is_string($proxy['server'] ?? null) || trim($proxy['server']) === ''
            || ! is_int($proxy['port'] ?? null) || ! is_string($proxy['name']) || strlen($proxy['name']) > 255) {
            throw new RuntimeException('This importer accepts native AEAD Shadowsocks nodes only; unsupported node or fields.');
        }
        $identity = hash('sha256', 'slinx-ef|' . $proxy['server'] . '|' . $proxy['port']);
        $config = [
            'external_shadowsocks' => [
                'source' => 'slinx-ef', 'source_key' => $identity, 'owner_user_id' => $ownerId,
                'source_order' => (int) $priority,
                'port' => $proxy['port'], 'method' => $proxy['cipher'] ?? '', 'password' => $proxy['password'] ?? '',
            ],
            'udp' => (bool) ($proxy['udp'] ?? true),
            'plugin' => $proxy['plugin'] ?? '', 'plugin_option' => $proxy['plugin-opts'] ?? null,
        ];
        ShadowsocksCredentials::resolve($config, (object) ['id' => $ownerId]);
        if (isset($prepared[$identity])) {
            throw new RuntimeException('Duplicate source address and port in import.');
        }
        $prepared[$identity] = ['name' => $proxy['name'], 'server' => $proxy['server'], 'config' => $config];
    }

    DB::init();
    $owner = User::find($ownerId);
    if ($owner === null || (int) $owner->is_banned !== 0) {
        throw new RuntimeException('The owner user is missing or banned.');
    }
    $results = DB::connection()->transaction(static function () use ($prepared, $owner, $argc): array {
        $existing = Node::query()->lockForUpdate()->get();
        $results = [];
        foreach ($prepared as $key => $item) {
            $target = null;
            foreach ($existing as $candidate) {
                $metadata = (json_decode($candidate->custom_config, true) ?? [])['external_shadowsocks'] ?? [];
                if (($metadata['source'] ?? '') === 'slinx-ef' && ($metadata['source_key'] ?? '') === $key
                    && (int) ($metadata['owner_user_id'] ?? 0) === (int) $owner->id) {
                    $target = $candidate;
                }
            }
            foreach ($existing as $candidate) {
                if ($candidate->name === $item['name'] && $candidate->id !== $target?->id
                    && ShadowsocksCredentials::visibleTo(json_decode($candidate->custom_config, true) ?? [], (int) $owner->id)) {
                    throw new RuntimeException('A different node already uses the imported name: ' . $item['name']);
                }
            }
            $created = $target === null;
            $target ??= new Node();
            if ($argc === 4) {
                $target->name = $item['name'];
                $target->server = $item['server'];
                $target->sort = 0;
                $target->type = 1;
                $target->node_group = (int) $owner->node_group;
                $target->node_class = (int) $owner->class;
                $target->custom_config = json_encode($item['config'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if ($created) {
                    $target->password = bin2hex(random_bytes(16));
                }
                $target->save();
            }
            $results[] = ['id' => $target->id, 'name' => $item['name'], 'action' => $created ? 'create' : 'update'];
        }

        return $results;
    });
    echo json_encode(['applied' => $argc === 4, 'owner_user_id' => $ownerId, 'nodes' => $results], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    // PDO 错误可能包含绑定参数和凭据，不要将这些内容写入控制台日志。
    fwrite(STDERR, $error instanceof RuntimeException || $error instanceof InvalidArgumentException
        ? 'Import failed: ' . ($error instanceof PDOException ? 'database operation failed' : $error->getMessage()) . "\n"
        : 'Import failed (' . $error::class . "). No credentials were printed.\n");
    exit(1);
}
