<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\User;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/** 单 Agent 计量：通过行锁串行处理用量、期望状态和月度重置。 */
final class EfSync
{
    public static function period(object $user, int $at): string
    {
        return (int) $user->auto_reset_day > 0
            ? MonthlyTrafficReset::periodAt((new DateTimeImmutable())->setTimestamp($at), (int) $user->auto_reset_day)
            : 'lifetime';
    }

    public static function credential(int $binding, int $user): string
    {
        $db = DB::connection();
        $db->table('ef_credentials')->insertOrIgnore([
            'binding_id' => $binding, 'user_id' => $user, 'password' => bin2hex(random_bytes(24)),
        ]);
        return $db->table('ef_credentials')->where('binding_id', $binding)->where('user_id', $user)->value('password');
    }

    public static function allowed(object $user, object $node): bool
    {
        return (int) $user->is_banned === 0 && (int) $user->is_shadow_banned === 0
            && (int) $user->class >= (int) $node->node_class
            && ((int) $node->node_group === 0 || (int) $node->node_group === (int) $user->node_group)
            && ((int) $user->class === 0 || strtotime($user->class_expire) > time())
            && (int) $user->transfer_enable > (int) $user->u + (int) $user->d;
    }

    public static function desired(string $agentId): array
    {
        MonthlyTrafficReset::run(new DateTimeImmutable());
        return DB::connection()->transaction(static function () use ($agentId): array {
            $db = DB::connection();
            $agent = $db->table('ef_agents')->where('id', $agentId)->lockForUpdate()->first();
            $bindings = [];
            // 用户可以同时使用多条线路；所有绑定均归属同一个 Agent。
            foreach ($db->table('ef_bindings')->where('agent_id', $agentId)->get() as $binding) {
                $node = Node::findOrFail($binding->node_id);
                $config = json_decode($node->custom_config, true)['managed_shadowsocks'];
                $tariff = $db->table('ef_tariffs')->find($binding->tariff_id);
                if ($node->is_dynamic_rate) {
                    throw new RuntimeException('Managed EF nodes currently require a fixed tariff');
                }
                $rate = (int) round($node->traffic_rate * 1000000);
                if ($rate < 0 || $rate > 100000000) {
                    throw new RuntimeException('Unsupported tariff');
                }
                if ((int) $tariff->rate_ppm !== $rate) {
                    $tariffId = $db->table('ef_tariffs')->insertGetId(['binding_id' => $binding->id,
                        'rate_ppm' => $rate, 'effective_at' => time()]);
                    $db->table('ef_bindings')->where('id', $binding->id)->update(['tariff_id' => $tariffId]);
                    $tariff = $db->table('ef_tariffs')->find($tariffId);
                }
                $users = [];
                {
                    $nodeEnabled = (int) $node->type === 1 && ((int) $node->node_bandwidth_limit === 0
                        || (int) $node->node_bandwidth < (int) $node->node_bandwidth_limit);
                    foreach (User::orderBy('id')->lockForUpdate()->get() as $user) {
                        $period = self::period($user, time());
                        $selfBilled = $db->table('ef_usage')->where('agent_id', $agentId)->where('user_id', $user->id)
                            ->where('period', $period)->where('charged', 1)->selectRaw('COALESCE(SUM(billed_u+billed_d),0) AS total')->first()->total;
                        $users[] = [
                            'id' => (int) $user->id, 'enabled' => $nodeEnabled && self::allowed($user, $node), 'password' => self::credential((int) $binding->id, (int) $user->id),
                            'period' => $period, 'reset_day' => (int) $user->auto_reset_day,
                            'expires_at' => (int) $user->class === 0 ? PHP_INT_MAX : strtotime($user->class_expire),
                            'used' => (int) $user->u + (int) $user->d, 'limit' => (int) $user->transfer_enable,
                            'self_billed' => (int) $selfBilled,
                        ];
                    }
                }
                $bindings[] = [
                    'id' => (int) $binding->id, 'inbound_id' => (int) $binding->inbound_id,
                    'outbound' => $config['outbound'] ?? '',
                    'server' => $node->server, 'port' => (int) $config['port'], 'method' => $config['method'],
                    'probe_password' => $binding->probe_password, 'tariff_id' => (int) $tariff->id,
                    'rate_ppm' => (int) $tariff->rate_ppm, 'users' => $users,
                ];
            }
            $revision = hash('sha256', json_encode([$agent->billing_start, $bindings], JSON_THROW_ON_ERROR));
            return ['agent_id' => $agentId, 'revision' => $revision, 'server_time' => time(),
                'lease_until' => time() + 300, 'timezone' => date_default_timezone_get(),
                'billing_start' => (int) $agent->billing_start, 'bindings' => $bindings];
        });
    }

    /** 先完成校验再写入；事务提交后才返回确认。 */
    public static function usage(string $agentId, array $events): array
    {
        if (count($events) > 500) {
            throw new InvalidArgumentException('Batch exceeds 500 events');
        }
        $db = DB::connection();
        MonthlyTrafficReset::run(new DateTimeImmutable());
        return $db->transaction(static function () use ($agentId, $events, $db): array {
            $agent = $db->table('ef_agents')->where('id', $agentId)->lockForUpdate()->first();
            $acks = [];
            foreach ($events as $e) {
                foreach (['binding_id', 'user_id', 'seq', 'started_at', 'ended_at', 'raw_u', 'raw_d', 'tariff_id'] as $key) {
                    if (! isset($e[$key]) || ! is_int($e[$key]) || $e[$key] < 0 || $e[$key] > 1_000_000_000_000_000) {
                        throw new InvalidArgumentException('Invalid integer: ' . $key);
                    }
                }
                if (! is_string($e['event_id'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{1,96}$/D', $e['event_id'])
                    || ! is_string($e['epoch'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $e['epoch'])
                    || $e['started_at'] > $e['ended_at'] || $e['ended_at'] > time() + 60
                    || $e['ended_at'] - $e['started_at'] > 3600) {
                    throw new InvalidArgumentException('Invalid event identity or interval');
                }
                $ordered = [];
                foreach (['event_id','binding_id','user_id','epoch','seq','started_at','ended_at','raw_u','raw_d','tariff_id'] as $key) {
                    $ordered[$key] = $e[$key];
                }
                $digest = hash('sha256', json_encode($ordered, JSON_THROW_ON_ERROR));
                $old = $db->table('ef_usage')->where('agent_id', $agentId)->where('event_id', $e['event_id'])->first();
                if ($old !== null) {
                    if (! hash_equals($old->digest, $digest)) {
                        throw new InvalidArgumentException('Event ID reused with different contents');
                    }
                    $acks[] = $e['event_id'];
                    continue;
                }
                $binding = $db->table('ef_bindings')->where('agent_id', $agentId)->where('id', $e['binding_id'])->first();
                if ($binding === null || ! $db->table('ef_credentials')->where('binding_id', $binding->id)->where('user_id', $e['user_id'])->exists()) {
                    throw new InvalidArgumentException('Unauthorized binding or user');
                }
                $tariff = $db->table('ef_tariffs')->where('binding_id', $binding->id)->where('id', $e['tariff_id'])->first();
                if ($tariff === null || $e['ended_at'] < (int) $tariff->effective_at) {
                    throw new InvalidArgumentException('Invalid tariff');
                }
                $user = User::where('id', $e['user_id'])->lockForUpdate()->firstOrFail();
                $occurredAt = max($e['started_at'], $e['ended_at'] - 1);
                $period = self::period($user, $occurredAt);
                $charged = (int) $agent->billing_start > 0 && $e['started_at'] >= (int) $agent->billing_start;
                $u = (int) bcdiv(bcmul((string) $e['raw_u'], (string) $tariff->rate_ppm, 0), '1000000', 0);
                $d = (int) bcdiv(bcmul((string) $e['raw_d'], (string) $tariff->rate_ppm, 0), '1000000', 0);
                $db->table('ef_usage')->insert($ordered + [
                    'agent_id' => $agentId, 'digest' => $digest, 'billed_u' => $u, 'billed_d' => $d,
                    'period' => $period, 'charged' => (int) $charged, 'received_at' => time(),
                ]);
                if ($charged) {
                    // 旧周期的延迟事件只记入历史，不扣减新周期额度。
                    if ($period === self::period($user, time())) {
                        $user->u += $u;
                        $user->d += $d;
                    }
                    $user->transfer_total += $e['raw_u'] + $e['raw_d'];
                    if (date('Y-m-d', $occurredAt) === date('Y-m-d')) {
                        $user->transfer_today += $u + $d;
                    }
                    $user->last_use_time = max((int) $user->last_use_time, $e['ended_at']);
                    $user->save();
                    Node::where('id', $binding->node_id)->increment('node_bandwidth', $e['raw_u'] + $e['raw_d']);
                    // 用户行锁也用于保护旧的非唯一 hourly_usage 表。
                    $day = date('Y-m-d', $occurredAt);
                    $hour = (int) date('G', $occurredAt);
                    $row = $db->table('hourly_usage')->where('user_id', $user->id)->where('date', $day)->lockForUpdate()->first();
                    $hours = $row ? json_decode($row->usage, true, 512, JSON_THROW_ON_ERROR) : [];
                    $hours[$hour] = ($hours[$hour] ?? 0) + $u + $d;
                    $values = ['usage' => json_encode((object) $hours, JSON_THROW_ON_ERROR)];
                    if ($row) {
                        $db->table('hourly_usage')->where('id', $row->id)->update($values);
                    } else {
                        $db->table('hourly_usage')->insert($values + ['user_id' => $user->id, 'date' => $day]);
                    }
                }
                $acks[] = $e['event_id'];
            }
            return $acks;
        }, 3);
    }

    public static function health(int $bindingId): array
    {
        $db = DB::connection();
        $b = $db->table('ef_bindings')->find($bindingId);
        $a = $b ? $db->table('ef_agents')->where('id', $b->agent_id)->first() : null;
        if (! $a || ! $a->enabled) {
            return [0, '尚未绑定 Agent'];
        }
        if ($a->last_seen < time() - 90) {
            return [-1, 'Agent 心跳已过期'];
        }
        if ($a->core_status !== 'running') {
            return [-1, '核心未运行'];
        }
        if ($a->error !== '') {
            return [0, '配置或采集异常：' . $a->error];
        }
        if ($b->probe_at < time() - 180) {
            return [0, '外部探测结果已过期'];
        }
        return [(int) $b->probe_ok === 1 ? 1 : ((int) $b->failures >= 3 ? -1 : 0),
            $b->probe_reason . ' · 探测 ' . date('m-d H:i:s', $b->probe_at)];
    }
}
