<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use InvalidArgumentException;

final class MihomoProfile
{
    /** 仅使用当前用户有权访问的代理节点，组合生成完整配置。 */
    public static function build(array $proxies, array $options): array
    {
        $reserved = [
            'DIRECT', 'REJECT', 'REJECT-DROP', 'PASS', 'COMPATIBLE', 'GLOBAL',
            '🚀 节点选择', '🖐 手动节点', '🧷 固定节点',
            '📥 游戏下载', '🛑 广告拦截', '🐟 漏网之鱼',
            ...array_keys($options['regions']), ...array_keys($options['services']),
        ];
        $names = [];
        foreach ($proxies as $proxy) {
            $name = $proxy['name'] ?? '';
            if (! is_string($name) || trim($name) === '' || ! isset($proxy['type'])) {
                throw new InvalidArgumentException('Each Mihomo proxy must have a name and type.');
            }
            if (in_array($name, $reserved, true) || in_array($name, $names, true)) {
                throw new InvalidArgumentException('Duplicate or reserved Mihomo proxy name: ' . $name);
            }
            $names[] = $name;
        }

        // 代理路径只允许手动选择；节点故障时连接失败，无节点时明确阻断。
        $available = $names === [] ? ['REJECT'] : $names;
        $regions = [];
        foreach ($options['regions'] as $name => $pattern) {
            $matched = array_values(array_filter($names, static fn (string $node): bool =>
                preg_match('~' . $pattern . '~u', $node) === 1));
            if ($matched !== []) {
                $regions[] = ['name' => $name, 'type' => 'select', 'proxies' => $matched];
            }
        }
        $regionNames = array_column($regions, 'name');
        $choices = ['🚀 节点选择', '🧷 固定节点', '🖐 手动节点', ...$regionNames];
        $groups = [
            ['name' => '🚀 节点选择', 'type' => 'select', 'proxies' => ['🧷 固定节点', '🖐 手动节点', ...$regionNames]],
            ['name' => '🖐 手动节点', 'type' => 'select', 'proxies' => $available],
            ['name' => '🧷 固定节点', 'type' => 'select', 'proxies' => $available],
        ];
        $groups = [...$groups, ...$regions];
        foreach ($options['services'] as $name => [, $default]) {
            $groups[] = [
                'name' => $name, 'type' => 'select',
                // null 默认策略表示该服务直接固定具体节点，不跟随共享策略组。
                'proxies' => $default === null ? $available : array_values(array_unique([$default, ...$choices, 'DIRECT'])),
            ];
        }
        $groups[] = ['name' => '📥 游戏下载', 'type' => 'select', 'proxies' => ['DIRECT', ...$choices]];
        $groups[] = [
            'name' => '🛑 广告拦截', 'type' => 'select',
            'proxies' => array_values(array_unique([$options['ad-default'], 'REJECT', 'PASS'])),
        ];
        $groups[] = ['name' => '🐟 漏网之鱼', 'type' => 'select', 'proxies' => ['🚀 节点选择', 'DIRECT']];

        $providers = [];
        $addProvider = static function (string $tag, string $source, string $behavior = 'domain') use (&$providers, $options): void {
            $directory = $behavior === 'domain' ? 'geosite' : 'geoip';
            $providers[$tag] = [
                'type' => 'http', 'behavior' => $behavior, 'format' => 'mrs',
                'url' => rtrim($options['rules-base-url'], '/') . '/' . $directory . '/' . $source . '.mrs',
                'path' => './rule-providers/everflame/' . $tag . '.mrs',
                'interval' => $options['update-interval'],
                'proxy' => '🚀 节点选择',
            ];
        };
        $addProvider('private-domain', 'private');
        $addProvider('cn-domain', 'cn');
        $addProvider('ads-domain', 'category-ads-all');
        $addProvider('proxy-domain', 'geolocation-!cn');
        $addProvider('cn-ip', 'cn', 'ipcidr');
        $addProvider('private-ip', 'private', 'ipcidr');

        $rules = [
            'RULE-SET,private-domain,DIRECT',
            'RULE-SET,private-ip,DIRECT,no-resolve',
            ...$options['custom-rules'],
            'RULE-SET,ads-domain,🛑 广告拦截',
        ];
        foreach ($options['download-rulesets'] as $source) {
            $tag = 'download-' . str_replace('@', '-', $source);
            $addProvider($tag, $source);
            $rules[] = 'RULE-SET,' . $tag . ',📥 游戏下载';
        }
        foreach ($options['services'] as $group => [$source]) {
            foreach ($options['service-rules'][$group] ?? [] as $rule) {
                $rules[] = $rule . ',' . $group;
            }
            $addProvider($source, $source);
            $rules[] = 'RULE-SET,' . $source . ',' . $group;
            if ($source === 'telegram' || $source === 'netflix') {
                $tag = $source . '-ip';
                $addProvider($tag, $source, 'ipcidr');
                $rules[] = 'RULE-SET,' . $tag . ',' . $group . ',no-resolve';
            }
        }
        $rules = [
            ...$rules,
            'RULE-SET,cn-domain,DIRECT',
            'RULE-SET,proxy-domain,🚀 节点选择',
            'RULE-SET,cn-ip,DIRECT,no-resolve',
            'MATCH,🐟 漏网之鱼',
        ];

        $profile = array_merge($options['base'], [
            'proxies' => array_values($proxies),
            'proxy-groups' => $groups, 'rule-providers' => $providers, 'rules' => $rules,
        ]);
        if (isset($options['dns-settings'])) {
            $profile['dns'] = MihomoDns::compile($options['dns-settings'], $profile);
        }
        return $profile;
    }
}
