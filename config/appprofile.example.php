<?php

declare(strict_types=1);

$_ENV['V2RayJson_Config'] = [
    'log' => [
        'error' => [
            'level' => 'error',
            'type' => 'console',
        ],
        'access' => [
            'type' => 'none',
        ],
    ],
    'dns' => [
        'nameServer' => [
            [
                'address' => '1.1.1.1',
            ],
            [
                'address' => '1.0.0.1',
            ],
        ],
    ],
    'inbounds' => [
        [
            'protocol' => 'socks',
            'settings' => [
                'udpEnabled' => true,
                'address' => '127.0.0.1',
                'packetEncoding' => 'packet',
            ],
            'port' => 7892,
            'listen' => '127.0.0.1',
        ],
        [
            'protocol' => 'http',
            'settings' => [],
            'port' => 7893,
            'listen' => '127.0.0.1',
        ],
    ],
    'outbounds' => [],
];

//sing-box 的 override_android_vpn 仅 Android 支持，按订阅客户端 User Agent 判断
$isSfaVisitor = str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'SFA');

$_ENV['SingBox_Config'] = [
    'log' => [
        'disabled' => false,
        'level' => 'error',
        'timestamp' => true,
    ],
    'dns' => [
        'servers' => [
            [
                'tag' => 'local',
                'type' => 'local',
                //'detour' => 'direct',
            ],
            [
                'tag' => 'alidns',
                'type' => 'quic',
                'server' => '223.6.6.6',
                'server_port' => 853,
                //'domain_resolver' => 'local',
                //'detour' => 'direct',
            ],
            [
                'tag' => 'cloudflare',
                'type' => 'tls',
                'server' => '1.1.1.1',
                'server_port' => 853,
                //'domain_resolver' => 'local',
                'detour' => 'select',
            ],
            [
                'tag' => 'google',
                'type' => 'tls',
                'server' => '8.8.8.8',
                'server_port' => 853,
                //'domain_resolver' => 'local',
                'detour' => 'select',
            ],
            [
                'tag' => 'opendns',
                'type' => 'tls',
                'server' => '208.67.222.222',
                'server_port' => 853,
                //'domain_resolver' => 'local',
                'detour' => 'select',
            ],
            [
                'tag' => 'mine_853',
                'type' => $_ENV['dns_type_853'],
                'server' => $_ENV['dns_server_853'],
                'server_port' => $_ENV['dns_server_port_853'],
                'domain_resolver' => 'alidns',
                //'detour' => 'select',
            ],
            [
                'tag' => 'mine_443',
                'type' => $_ENV['dns_type_443'],
                'server' => $_ENV['dns_server_443'],
                'server_port' => $_ENV['dns_server_port_443'],
                'path' => $_ENV['dns_path_443'],
                'domain_resolver' => 'alidns',
                //'detour' => 'select',
            ],
            [
                'tag' => 'fakeip',
                'type' => 'fakeip',
                'inet4_range' => '198.18.0.0/15',
                'inet6_range' => 'fc00::/18',
            ],
        ],
        'rules' => [
            [
                'query_type' => [
                    'SVCB',
                    'HTTPS',
                ],
                'action' => 'predefined',
                'rcode' => 'REFUSED',
            ],
            [
                'rule_set' => [
                    'geosite-category-ads-all',
                ],
                'action' => 'predefined',
                'rcode' => 'REFUSED',
            ],
            [
                'clash_mode' => 'Global',
                'server' => 'fakeip',
            ],
            [
                'rule_set' => [
                    'geosite-geolocation-cn',
                    'geosite-cn',
                    'geosite-netease',
                    'geosite-bilibili',
                ],
                'server' => 'fakeip',
            ],
            [
                'action' => 'evaluate',
                'server' => $_ENV['dns_select'],
                'disable_cache' => true,
                'client_subnet' => '111.222.0.0',
            ],
            [
                'rule_set' => [
                    'geosite-geolocation-!cn',
                ],
                'server' => $_ENV['dns_select'],
                'disable_cache' => true,
                'client_subnet' => '111.222.0.0',
            ],
            [
                'type' => 'logical',
                'mode' => 'and',
                'rules' => [
                    [
                        'rule_set' => [
                            'geosite-geolocation-!cn',
                        ],
                        'invert' => true,
                    ],
                    [
                        'match_response' => true,
                        'rule_set' => [
                            'geoip-cn',
                        ],
                    ],
                ],
                'action' => 'route',
                'server' => $_ENV['dns_select'],
                'disable_cache' => true,
                'client_subnet' => '111.222.0.0',
            ],
            [
                'clash_mode' => 'Rule',
                'server' => 'fakeip',
            ],
            [
                'clash_mode' => 'Direct',
                'server' => 'local',
            ],
        ],
        'final' => 'cloudflare',
        'disable_cache' => true,
        //'independent_cache' => true,
    ],
    'inbounds' => [
        [
            'type' => 'tun',
            'tag' => 'in',
            'address' => [
                '172.18.0.1/30',
                'fdfe:dcba:9876::1/126',
            ],
            'auto_route' => true,
            'strict_route' => true,
            'udp_timeout' => 60,
            'stack' => 'mixed',
        ],
    ],
    'outbounds' => [
        [
            'tag' => 'select',
            'type' => 'selector',
            'outbounds' => [
                'auto',
            ],
            'default' => 'auto',
            'interrupt_exist_connections' => true,
        ],
        [
            'type' => 'urltest',
            'tag' => 'auto',
            'outbounds' => [],
            'url' => 'https://cp.cloudflare.com/generate_204',
            'interval' => '3m',
            'tolerance' => 50,
            'idle_timeout' => '30m',
            'interrupt_exist_connections' => true,
        ],
        [
            'type' => 'direct',
            'tag' => 'direct',
        ],
    ],
    'route' => [
        'rules' => [
            [
                'inbound' => 'in',
                'action' => 'sniff',
                'timeout' => '1s',
            ],
            [
                'inbound' => 'in',
                'protocol' => 'dns',
                'port' => [
                    53,
                ],
                'action' => 'hijack-dns',
            ],
            [
                'rule_set' => [
                    'geosite-category-ads-all',
                ],
                'action' => 'reject',
                'method' => 'default',
            ],
            [
                'clash_mode' => 'Direct',
                'outbound' => 'direct',
            ],
            [
                'clash_mode' => 'Global',
                'outbound' => 'select',
            ],
            [
                'protocol' => 'stun',
                'action' => 'reject',
                'method' => 'default',
            ],
            [
                'rule_set' => [
                    'geosite-geolocation-!cn',
                ],
                'outbound' => 'select',
            ],
            //[
                //'action' => 'resolve',
                //'server' => $_ENV['dns_select'],
                //'disable_cache' => true,
                //'rewrite_ttl' => 60,
                //'client_subnet' => '111.222.0.0',
            //],
            [
                'type' => 'logical',
                'mode' => 'and',
                'rules' => [
                    [
                        'rule_set' => [
                            'geosite-geolocation-!cn',
                            'geosite-geolocation-cn',
                            'geosite-cn',
                            'geosite-netease',
                            'geosite-bilibili',
                        ],
                        'invert' => true,
                    ],
                    [
                        'rule_set' => [
                            'geoip-cn',
                        ],
                        'invert' => true,
                    ],
                ],
                'action' => 'route',
                'outbound' => 'select',
            ],
            [
                'rule_set' => [
                    'geosite-geolocation-cn',
                    'geosite-cn',
                    'geosite-netease',
                    'geosite-bilibili',
                ],
                'outbound' => 'direct',
            ],
            [
                'rule_set' => [
                    'geoip-cn',
                ],
                'outbound' => 'direct',
            ],
            [
                'type' => 'logical',
                'mode' => 'and',
                'rules' => [
                    [
                        'rule_set' => [
                            'geosite-geolocation-!cn',
                        ],
                        'invert' => true,
                    ],
                    [
                        'rule_set' => [
                            'geoip-cn',
                        ],
                    ],
                ],
                'action' => 'route',
                'outbound' => 'direct',
            ],
            [
                'ip_is_private' => true,
                'outbound' => 'direct',
            ],
        ],
        'rule_set' => [
            [
                'tag' => 'geoip-cn',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geoip@rule-set/geoip-cn.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
            [
                'tag' => 'geosite-cn',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geosite@rule-set/geosite-cn.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
            [
                'tag' => 'geosite-category-ads-all',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geosite@rule-set/geosite-category-ads-all.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
            [
                'tag' => 'geosite-geolocation-cn',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geosite@rule-set/geosite-geolocation-cn.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
            [
                'tag' => 'geosite-geolocation-!cn',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geosite@rule-set/geosite-geolocation-!cn.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
            [
                'tag' => 'geosite-netease',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geosite@rule-set/geosite-netease.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
            [
                'tag' => 'geosite-bilibili',
                'type' => 'remote',
                'format' => 'binary',
                'url' => 'https://' . $_ENV['jsdelivr_url'] . '/gh/SagerNet/sing-geosite@rule-set/geosite-bilibili.srs',
                'update_interval' => '1d',
                'http_client' => [
                    'engine' => 'go',
                    'version' => 2,
                    'disable_version_fallback' => false,
                ],
            ],
        ],
        'final' => 'select',
        'auto_detect_interface' => true,
        'override_android_vpn' => $isSfaVisitor,
        'default_domain_resolver' => [
            'server' => 'local',
            'rewrite_tll' => 60,
        ],
    ],
    'experimental' => [
        'cache_file' => [
            'enabled' => true,
            'cache_id' => '',
            'path' => 'cache.db',
        ],
    ],
];

// Mihomo 完整订阅模板。客户端每 24 小时更新 MRS 规则，无需每日改模板。
// 旧自定义 appprofile.php 未设置此项时仍使用原有 Clash_Config / Clash_Group_Config。
$_ENV['Clash_Mihomo_Profile'] = require __DIR__ . '/profiles/mihomo.php';
$_ENV['Clash_Mihomo_Profile']['base']['tcp-concurrent'] = $_ENV['tcp_concurrent'] ?? true;
