<?php

declare(strict_types=1);

// ACL4SSR 风格的策略分组；规则数据由 MetaCubeX 独立更新，不包含节点凭据。
// 仅用于 Mihomo，旧 Clash/Premium 与 sing-box 不使用此配置。
return [
    'base' => [
        'mixed-port' => 7890,
        'allow-lan' => false,
        'mode' => 'rule',
        'log-level' => 'warning',
        'ipv6' => false,
        'tcp-concurrent' => true,
        'unified-delay' => true,
        'profile' => ['store-selected' => true, 'store-fake-ip' => true],
        'dns' => [
            'enable' => true,
            'listen' => '127.0.0.1:1053',
            'ipv6' => false,
            'enhanced-mode' => 'fake-ip',
            'fake-ip-range' => '198.18.0.1/16',
            'fake-ip-filter' => ['*.lan', '*.local', 'localhost', 'time.*.com', 'ntp.*.com'],
            'default-nameserver' => ['223.5.5.5', '119.29.29.29'],
            // 显式选择代理组，避免境外 DNS 经国内出口，以及节点域名解析循环。
            'nameserver' => ['https://1.1.1.1/dns-query#🚀 节点选择'],
            'proxy-server-nameserver' => ['https://dns.alidns.com/dns-query'],
            'direct-nameserver' => ['https://dns.alidns.com/dns-query'],
            'direct-nameserver-follow-policy' => true,
            'nameserver-policy' => [
                'rule-set:private-domain' => ['system'],
                'rule-set:cn-domain' => ['https://dns.alidns.com/dns-query'],
            ],
        ],
    ],
    'rules-base-url' => 'https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/meta/geo',
    'update-interval' => 86400,
    // 名称匹配仅用于分组，不代表实际地区或解锁能力；没有匹配节点时不生成该组。
    'regions' => [
        '🇭🇰 香港' => '(?i)(香港|港|Hong[ -]?Kong|\bHK\b|🇭🇰)',
        '🇹🇼 台湾' => '(?i)(台湾|台灣|Taiwan|\bTW\b|🇹🇼)',
        '🇯🇵 日本' => '(?i)(日本|东京|大阪|Japan|Tokyo|Osaka|\bJP\b|🇯🇵)',
        '🇸🇬 新加坡' => '(?i)(新加坡|狮城|獅城|Singapore|\bSG\b|🇸🇬)',
        '🇺🇸 美国' => '(?i)(美国|美國|洛杉矶|硅谷|United[ -]?States|Los[ -]?Angeles|\bUS\b|\bUSA\b|🇺🇸)',
        '🇰🇷 韩国' => '(?i)(韩国|韓國|首尔|Korea|Seoul|\bKR\b|🇰🇷)',
        '🇬🇧 英国' => '(?i)(英国|英國|伦敦|United[ -]?Kingdom|London|\bUK\b|\bGB\b|🇬🇧)',
    ],
    // [规则集名称, 默认策略]；null 表示独立手动选择具体节点。
    // 具体服务在宽泛的 Google、Apple、国内规则之前匹配。
    'services' => [
        '🤖 ChatGPT' => ['openai', '🧷 固定节点'],
        '🧠 Claude' => ['anthropic', '🧷 固定节点'],
        '✨ Gemini' => ['google-gemini', '🧷 固定节点'],
        '🛠 GitHub' => ['github', null],
        '📹 YouTube' => ['youtube', '🚀 节点选择'],
        '🎥 Netflix' => ['netflix', '🚀 节点选择'],
        '🏰 Disney+' => ['disney', '🚀 节点选择'],
        '🎵 Spotify' => ['spotify', '🚀 节点选择'],
        '🎶 TikTok' => ['tiktok', '🧷 固定节点'],
        '📲 Telegram' => ['telegram', '🚀 节点选择'],
        '💬 Discord' => ['discord', '🚀 节点选择'],
        '🐦 X' => ['twitter', '🚀 节点选择'],
        '☁️ OneDrive' => ['onedrive', 'DIRECT'],
        '🔍 Google' => ['google', '🚀 节点选择'],
        '🍎 Apple' => ['apple', 'DIRECT'],
        'Ⓜ️ Microsoft' => ['microsoft', 'DIRECT'],
        '🎮 Steam' => ['steam', '🚀 节点选择'],
        '🕹 Epic' => ['epicgames', '🚀 节点选择'],
    ],
    // 补充社区规则集的缺口，目标自动使用对应服务组；自定义覆盖规则仍优先。
    // 来源：https://api.github.com/meta 及 GitHub Copilot allowlist reference（2026-11-09）。
    'service-rules' => [
        '🛠 GitHub' => [
            'DOMAIN,default.exp-tas.com',
            'DOMAIN,mavenregistryv2prod.blob.core.windows.net',
            'DOMAIN,npmregistryv2prod.blob.core.windows.net',
            'DOMAIN,nugetregistryv2prod.blob.core.windows.net',
            'DOMAIN,rubygemsregistryv2prod.blob.core.windows.net',
            // 限定 GitHub 的存储账号/CDN 命名，避免接管整个 Azure 公共域名。
            'DOMAIN-REGEX,^productionresultssa[0-9]+\\.blob\\.core\\.windows\\.net$',
            'DOMAIN-REGEX,^copilot-reports-[a-z0-9-]+\\.b01\\.azurefd\\.net$',
            'DOMAIN-REGEX,^usagereports[a-z0-9-]*\\.blob\\.core\\.windows\\.net$',
        ],
    ],
    // 只把国内游戏下载/CDN 规则直连；不按应用进程、任意端口放行整个游戏平台。
    'download-rulesets' => ['steam@cn', 'category-games@cn'],
    // 从上至下优先于公共规则，供站长补充细分。策略名称须来自最终配置。
    // 例如：'DOMAIN-SUFFIX,example.com,🧠 Claude'
    'custom-rules' => [],
    // 默认关闭域名广告拦截，避免登录/支付误伤；客户端可手动切换为 REJECT。
    'ad-default' => 'PASS',
];
