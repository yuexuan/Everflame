<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use InvalidArgumentException;

/** 仅编译 DNS 配置，不接收代理节点、路由规则或凭据。 */
final class MihomoDns
{
    public static function defaults(): array
    {
        $providers = [];
        foreach (['google' => ['Google', 'https://8.8.8.8/dns-query', '🚀 节点选择'],
            'cloudflare' => ['Cloudflare', 'https://1.1.1.1/dns-query', '🚀 节点选择'],
            'ali' => ['阿里 DNS', 'https://dns.alidns.com/dns-query', 'DIRECT'],
            'tencent' => ['腾讯 DNS', 'https://doh.pub/dns-query', 'DIRECT']] as $id => [$name, $server, $egress]) {
            $providers[] = compact('id', 'name', 'egress') + ['enabled' => true, 'servers' => [$server], 'note' => ''];
        }
        $policies = [];
        foreach (['google' => 'google', 'youtube' => 'google', 'google-gemini' => 'google', 'cn-domain' => 'ali'] as $key => $providerId) {
            $policies[] = ['id' => $key, 'name' => $key, 'enabled' => true, 'priority' => 100,
                'match' => ['type' => 'rule-set', 'value' => $key], 'providerId' => $providerId];
        }
        return ['enable' => true, 'ipv6' => false, 'enhanced-mode' => 'fake-ip',
            'defaultProviderId' => 'cloudflare', 'providers' => $providers, 'policies' => $policies];
    }

    public static function address(string $server): void
    {
        if (filter_var($server, FILTER_VALIDATE_IP)) {
            return;
        }
        $url = parse_url($server);
        if (!is_array($url) || !in_array($url['scheme'] ?? '', ['https', 'tls', 'tcp', 'udp'], true)
            || empty($url['host']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || preg_match('/[\\s#]/u', $server)
            || (!filter_var(trim($url['host'], '[]'), FILTER_VALIDATE_IP) && !self::domain($url['host']))
            || (isset($url['port']) && ($url['port'] < 1 || $url['port'] > 65535))
            || (($url['scheme'] ?? '') !== 'https' && !empty($url['path']))) {
            throw new InvalidArgumentException('非法 DNS 地址（仅支持 IP、UDP/TCP、DoH、DoT，不接受出口后缀）：' . $server);
        }
    }

    private static function domain(string $domain): bool
    {
        return strlen($domain) <= 253 && (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $domain);
    }

    public static function compile(array $settings, array $profile): array
    {
        foreach (['enable', 'ipv6'] as $key) {
            if (!isset($settings[$key]) || !is_bool($settings[$key])) {
                throw new InvalidArgumentException('DNS 开关必须为布尔值：' . $key);
            }
        }
        if (!in_array($settings['enhanced-mode'] ?? '', ['fake-ip', 'redir-host'], true)) {
            throw new InvalidArgumentException('不支持的 DNS 模式');
        }
        foreach (['providers', 'policies'] as $list) {
            if (!is_array($settings[$list] ?? null) || !array_is_list($settings[$list]) || count($settings[$list]) > 200) {
                throw new InvalidArgumentException('Provider / Policy 必须为列表，最多 200 条');
            }
        }
        $groups = ['DIRECT', ...array_column($profile['proxy-groups'], 'name')];
        $providers = [];
        foreach ($settings['providers'] ?? [] as $provider) {
            $id = $provider['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-z0-9_-]{1,64}$/D', $id) || isset($providers[$id])
                || !is_string($provider['name'] ?? null) || trim($provider['name']) === ''
                || !is_bool($provider['enabled'] ?? null) || !is_string($provider['egress'] ?? null) || preg_match('/[#&=]/u', $provider['egress']) || !in_array($provider['egress'] ?? '', $groups, true)
                || !is_array($provider['servers'] ?? null) || $provider['servers'] === []) {
                throw new InvalidArgumentException('Provider 标识、名称、出口或服务器列表无效/重复：' . (string) $id);
            }
            $servers = [];
            foreach ($provider['servers'] as $server) {
                if (!is_string($server)) {
                    throw new InvalidArgumentException('DNS 地址必须为文本');
                }
                self::address($server);
                if (filter_var($server, FILTER_VALIDATE_IP)) {
                    $server = 'udp://' . (str_contains($server, ':') ? '[' . $server . ']' : $server);
                }
                $servers[] = $server . '#' . $provider['egress'];
            }
            $providers[$id] = ['enabled' => $provider['enabled'], 'servers' => $servers];
        }
        $resolve = static function ($id) use ($providers): array {
            if (!is_string($id) || !isset($providers[$id]) || !$providers[$id]['enabled']) {
                throw new InvalidArgumentException('Provider 被引用，不能删除或停用：' . (string) $id);
            }
            return $providers[$id]['servers'];
        };
        $dns = $profile['dns'];
        $dns['enable'] = $settings['enable'];
        $dns['ipv6'] = $settings['ipv6'];
        $dns['enhanced-mode'] = $settings['enhanced-mode'];
        $dns['nameserver'] = $resolve($settings['defaultProviderId'] ?? null);
        $policies = $settings['policies'] ?? [];
        if (!is_array($policies)) {
            throw new InvalidArgumentException('Policy 列表无效');
        }
        $ids = [];
        $keys = [];
        foreach ($policies as $policy) {
            $id = $policy['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-z0-9_-]{1,64}$/D', $id) || isset($ids[$id])
                || !is_string($policy['name'] ?? null) || trim($policy['name']) === ''
                || !is_int($policy['priority'] ?? null) || !is_bool($policy['enabled'] ?? null)) {
                throw new InvalidArgumentException('Policy 字段无效/标识重复');
            }
            $ids[$id] = true;
            $resolve($policy['providerId'] ?? null);
            $type = $policy['match']['type'] ?? '';
            $value = $policy['match']['value'] ?? '';
            if (!is_string($value) || !in_array($type, ['rule-set', 'domain', 'domain-suffix'], true)
                || ($type === 'rule-set' && (($profile['rule-providers'][$value]['behavior'] ?? '') !== 'domain' || $value === 'private-domain'))
                || ($type !== 'rule-set' && !self::domain($value))) {
                throw new InvalidArgumentException('仅允许现有域名规则集或合法 domain/domain-suffix；private 固定优先');
            }
            $key = $type . ':' . $value;
            if (isset($keys[$key])) {
                throw new InvalidArgumentException('重复/冲突的 DNS 匹配：' . $key);
            }
            $keys[$key] = true;
        }
        usort($policies, static fn ($a, $b) => [$a['priority'], $a['id']] <=> [$b['priority'], $b['id']]);
        $dns['nameserver-policy'] = ['rule-set:private-domain' => ['system']];
        foreach ($policies as $policy) {
            if (!$policy['enabled']) {
                continue;
            }
            ['type' => $type, 'value' => $value] = $policy['match'];
            $key = match ($type) {'rule-set' => 'rule-set:' . $value, 'domain-suffix' => '+.' . $value, default => $value};
            $dns['nameserver-policy'][$key] = $resolve($policy['providerId']);
        }
        return $dns;
    }
}
