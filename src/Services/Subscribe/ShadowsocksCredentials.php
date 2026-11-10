<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use InvalidArgumentException;

final class ShadowsocksCredentials
{
    public static function visibleTo(array $config, int $userId): bool
    {
        if (! array_key_exists('external_shadowsocks', $config)) {
            return true;
        }

        return is_array($config['external_shadowsocks'])
            && (int) ($config['external_shadowsocks']['owner_user_id'] ?? 0) === $userId
            && $userId > 0;
    }

    /** 解析导入节点的连接凭据，不改变面板用户身份。 */
    public static function resolve(array $config, object $user): array
    {
        if (isset($config['managed_shadowsocks'])) {
            $managed = $config['managed_shadowsocks'];
            return ['port' => (int) $managed['port'], 'method' => $managed['method'],
                'password' => \App\Services\EfSync::credential((int) $managed['binding_id'], (int) $user->id)];
        }
        if (! array_key_exists('external_shadowsocks', $config)) {
            return ['port' => (int) $user->port, 'password' => $user->passwd, 'method' => $user->method];
        }
        if (! self::visibleTo($config, (int) $user->id)) {
            throw new InvalidArgumentException('Imported Shadowsocks node belongs to another user.');
        }
        $external = $config['external_shadowsocks'];
        $port = (int) ($external['port'] ?? 0);
        if ($port < 1 || $port > 65535 || ! is_string($external['password'] ?? null)
            || $external['password'] === '' || ! in_array($external['method'] ?? '', [
                'aes-128-gcm', 'aes-192-gcm', 'aes-256-gcm', 'chacha20-ietf-poly1305', 'xchacha20-ietf-poly1305',
            ], true)) {
            throw new InvalidArgumentException('Invalid imported Shadowsocks credentials.');
        }

        return ['port' => $port, 'password' => $external['password'], 'method' => $external['method']];
    }
}
