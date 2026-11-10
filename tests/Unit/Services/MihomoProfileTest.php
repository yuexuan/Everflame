<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Subscribe\MihomoProfile;
use App\Services\Subscribe\MihomoDns;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/Subscribe/MihomoProfile.php';

require_once dirname(__DIR__, 3) . '/src/Services/Subscribe/MihomoDns.php';

final class MihomoProfileTest extends TestCase
{
    private function options(): array
    {
        return require dirname(__DIR__, 3) . '/config/profiles/mihomo.php';
    }

    private function proxies(): array
    {
        return [
            ['name' => 'HK Reality', 'type' => 'vless', 'server' => '192.0.2.1', 'port' => 443,
                'uuid' => '00000000-0000-4000-8000-000000000001', 'tls' => true,
                'servername' => 'example.com', 'reality-opts' => ['public-key' => 'fixture-key', 'short-id' => 'ab']],
            ['name' => 'US 02', 'type' => 'ss', 'server' => '192.0.2.2', 'port' => 443,
                'cipher' => 'aes-128-gcm', 'password' => 'fixture-password'],
        ];
    }

    public function testPreservesProtocolCredentialsAndOnlyIncludesSuppliedUserNodes(): void
    {
        $proxies = $this->proxies();
        $profile = MihomoProfile::build($proxies, $this->options());
        self::assertSame($proxies, $profile['proxies']);
        $single = MihomoProfile::build([$proxies[1]], $this->options());
        self::assertSame([$proxies[1]], $single['proxies']);
        self::assertStringNotContainsString('HK Reality', json_encode($single, JSON_THROW_ON_ERROR));
    }

    public function testRegionsReflectAvailableNodesWithoutDeadGroups(): void
    {
        $groups = array_column(MihomoProfile::build($this->proxies(), $this->options())['proxy-groups'], null, 'name');
        self::assertSame(['HK Reality'], $groups['🇭🇰 香港']['proxies']);
        self::assertSame(['US 02'], $groups['🇺🇸 美国']['proxies']);
        self::assertArrayNotHasKey('🇯🇵 日本', $groups);
        self::assertSame('🧷 固定节点', $groups['🤖 ChatGPT']['proxies'][0]);
        self::assertSame('🧷 固定节点', $groups['🧠 Claude']['proxies'][0]);
        self::assertSame('🧷 固定节点', $groups['🚀 节点选择']['proxies'][0]);
    }

    public function testNoNodesFailClosedAndAllGroupReferencesResolve(): void
    {
        foreach ([[], $this->proxies()] as $proxies) {
            $profile = MihomoProfile::build($proxies, $this->options());
            $groups = array_column($profile['proxy-groups'], null, 'name');
            $valid = ['DIRECT', 'REJECT', 'PASS', ...array_keys($groups), ...array_column($proxies, 'name')];
            foreach ($groups as $group) {
                self::assertNotEmpty($group['proxies']);
                foreach ($group['proxies'] as $reference) {
                    self::assertContains($reference, $valid, $group['name']);
                    self::assertNotSame($group['name'], $reference);
                }
            }
            if ($proxies === []) {
                foreach (['🖐 手动节点', '🧷 固定节点'] as $name) {
                    self::assertSame(['REJECT'], $groups[$name]['proxies']);
                }
            }
        }
    }

    public function testProxyPathsNeverAutomaticallySwitchNodes(): void
    {
        foreach ([[], $this->proxies()] as $proxies) {
            $profile = MihomoProfile::build($proxies, $this->options());
            $groups = array_column($profile['proxy-groups'], null, 'name');
            self::assertArrayNotHasKey('🛟 故障转移', $groups);
            self::assertArrayNotHasKey('♻️ 自动测速', $groups);
            foreach ($groups as $group) {
                self::assertSame('select', $group['type'], $group['name']);
                self::assertNotContains('🛟 故障转移', $group['proxies']);
                self::assertNotContains('♻️ 自动测速', $group['proxies']);
            }
            foreach (['🚀 节点选择', '🖐 手动节点', '🧷 固定节点', ...array_keys($this->options()['regions'])] as $name) {
                if (isset($groups[$name])) {
                    self::assertNotContains('DIRECT', $groups[$name]['proxies']);
                }
            }
        }
    }

    public function testGithubSelectsItsOwnNodeAndRejectsWhenEmpty(): void
    {
        foreach ([[], $this->proxies(), [$this->proxies()[1]]] as $proxies) {
            $profile = MihomoProfile::build($proxies, $this->options());
            $groups = array_column($profile['proxy-groups'], null, 'name');
            self::assertSame('select', $groups['🛠 GitHub']['type']);
            self::assertSame($proxies === [] ? ['REJECT'] : array_column($proxies, 'name'), $groups['🛠 GitHub']['proxies']);
        }
    }

    public function testGithubSupplementRulesCoverSpecificEndpointsBeforeMicrosoftAndPreserveOverrides(): void
    {
        $options = $this->options();
        $override = 'DOMAIN,default.exp-tas.com,REJECT';
        $options['custom-rules'] = [$override];
        $rules = MihomoProfile::build($this->proxies(), $options)['rules'];
        $matches = static function (string $host) use ($rules): bool {
            foreach ($rules as $rule) {
                [$type, $pattern, $target] = array_pad(explode(',', $rule), 3, null);
                if ($target !== '🛠 GitHub') {
                    continue;
                }
                if (($type === 'DOMAIN' && $host === $pattern) ||
                    ($type === 'DOMAIN-REGEX' && preg_match('~' . $pattern . '~', $host) === 1)) {
                    return true;
                }
            }
            return false;
        };
        foreach (['default.exp-tas.com', 'mavenregistryv2prod.blob.core.windows.net',
            'npmregistryv2prod.blob.core.windows.net', 'nugetregistryv2prod.blob.core.windows.net',
            'rubygemsregistryv2prod.blob.core.windows.net', 'productionresultssa199.blob.core.windows.net',
            'copilot-reports-production-01.b01.azurefd.net', 'usagereports01.blob.core.windows.net'] as $host) {
            self::assertTrue($matches($host), $host);
        }
        foreach (['other.blob.core.windows.net', 'other.b01.azurefd.net', 'other.exp-tas.com',
            'productionresultssa199.blob.core.windows.net.example.org',
            'fakeproductionresultssa199.blob.core.windows.net'] as $host) {
            self::assertFalse($matches($host), $host);
        }
        foreach ($options['service-rules']['🛠 GitHub'] as $rule) {
            $position = array_search($rule . ',🛠 GitHub', $rules, true);
            self::assertIsInt($position);
            self::assertLessThan(array_search('RULE-SET,microsoft,Ⓜ️ Microsoft', $rules, true), $position);
            self::assertLessThan($position, array_search($override, $rules, true));
        }
        unset($options['service-rules']);
        self::assertNotEmpty(MihomoProfile::build([], $options)['rules']);
    }

    public function testServiceAndOverrideRulesPrecedeBroadCountryAndPlatformRules(): void
    {
        $options = $this->options();
        $custom = 'DOMAIN-SUFFIX,custom.example,🧠 Claude';
        $options['custom-rules'] = [$custom];
        $profile = MihomoProfile::build($this->proxies(), $options);
        $rules = $profile['rules'];
        $before = static function (string $specific, string $broad) use ($rules): void {
            $a = array_search($specific, $rules, true);
            $b = array_search($broad, $rules, true);
            self::assertIsInt($a);
            self::assertIsInt($b);
            self::assertLessThan($b, $a);
        };
        $before($custom, 'RULE-SET,anthropic,🧠 Claude');
        $before('RULE-SET,google-gemini,✨ Gemini', 'RULE-SET,google,🔍 Google');
        $before('RULE-SET,onedrive,☁️ OneDrive', 'RULE-SET,microsoft,Ⓜ️ Microsoft');
        $before('RULE-SET,download-steam-cn,📥 游戏下载', 'RULE-SET,steam,🎮 Steam');
        $before('RULE-SET,netflix,🎥 Netflix', 'RULE-SET,cn-ip,DIRECT,no-resolve');
        self::assertSame('MATCH,🐟 漏网之鱼', $rules[array_key_last($rules)]);
        foreach ($rules as $rule) {
            self::assertStringNotContainsString('DST-PORT,', $rule);
            self::assertStringNotContainsString('PROCESS-NAME,', $rule);
            if (str_starts_with($rule, 'RULE-SET,')) {
                self::assertArrayHasKey(explode(',', $rule)[1], $profile['rule-providers']);
            }
        }
    }

    public function testRuleUpdatesDnsBootstrapAndOptionalAds(): void
    {
        $profile = MihomoProfile::build($this->proxies(), $this->options());
        $paths = [];
        foreach ($profile['rule-providers'] as $provider) {
            self::assertSame(86400, $provider['interval']);
            self::assertSame('mrs', $provider['format']);
            self::assertNotContains($provider['path'], $paths);
            $paths[] = $provider['path'];
        }
        self::assertArrayNotHasKey('geox-url', $profile);
        self::assertArrayNotHasKey('external-controller', $profile);
        self::assertNotEmpty($profile['dns']['proxy-server-nameserver']);
        self::assertSame(['system'], $profile['dns']['nameserver-policy']['rule-set:private-domain']);
        $groups = array_column($profile['proxy-groups'], null, 'name');
        self::assertSame('PASS', $groups['🛑 广告拦截']['proxies'][0]);
    }

    public function testDuplicateNamesCannotSilentlySelectAnotherProxy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $proxies = $this->proxies();
        MihomoProfile::build([$proxies[0], $proxies[0]], $this->options());
    }

    public function testReservedNamesCannotShadowPolicyGroups(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $proxy = $this->proxies()[0];
        $proxy['name'] = '🚀 节点选择';
        MihomoProfile::build([$proxy], $this->options());
    }

    public function testDnsPresetsPreserveEverythingOutsideDns(): void
    {
        foreach ([[], $this->proxies()] as $nodes) {
            $options = $this->options();
            $baseline = MihomoProfile::build($nodes, $options);
            $options['dns-settings'] = MihomoDns::defaults();
            $profile = MihomoProfile::build($nodes, $options);
            foreach (['google', 'youtube', 'google-gemini'] as $tag) {
                self::assertSame(['https://8.8.8.8/dns-query#🚀 节点选择'], $profile['dns']['nameserver-policy']['rule-set:' . $tag]);
                self::assertSame('domain', $profile['rule-providers'][$tag]['behavior']);
            }
            self::assertSame(['https://dns.alidns.com/dns-query#DIRECT'], $profile['dns']['nameserver-policy']['rule-set:cn-domain']);
            self::assertSame(['system'], $profile['dns']['nameserver-policy']['rule-set:private-domain']);
            self::assertSame(['https://1.1.1.1/dns-query#🚀 节点选择'], $profile['dns']['nameserver']);
            foreach (['proxy-server-nameserver', 'default-nameserver', 'direct-nameserver', 'direct-nameserver-follow-policy', 'fake-ip-filter'] as $key) {
                self::assertSame($baseline['dns'][$key], $profile['dns'][$key]);
            }
            unset($baseline['dns'], $profile['dns']);
            self::assertSame($baseline, $profile);
        }
    }

    public function testDnsCustomDomainAndSuffix(): void
    {
        $options = $this->options();
        $settings = MihomoDns::defaults();
        $settings['policies'][] = ['id' => 'custom', 'name' => 'custom', 'enabled' => true, 'priority' => 1,
            'match' => ['type' => 'domain-suffix', 'value' => 'example.com'], 'providerId' => 'tencent'];
        $options['dns-settings'] = $settings;
        self::assertSame(['https://doh.pub/dns-query#DIRECT'], MihomoProfile::build([], $options)['dns']['nameserver-policy']['+.example.com']);
    }

    public function testDnsRejectsInvalidConfiguration(): void
    {
        $cases = [];
        $s = MihomoDns::defaults(); $s['providers'][0]['servers'] = ['https://dns.google/dns-query#DIRECT']; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['providers'][0]['egress'] = 'missing'; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['providers'][0]['enabled'] = false; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['providers'][0]['servers'] = []; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['policies'][0]['match']['value'] = 'cn-ip'; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['policies'][0]['match']['value'] = 'missing'; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['policies'][0]['match']['type'] = 'GEOIP'; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['policies'][] = $s['policies'][0]; $cases[] = $s;
        $s = MihomoDns::defaults(); $duplicate = $s['policies'][0]; $duplicate['id'] = 'another'; $s['policies'][] = $duplicate; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['providers'][0]['servers'] = ['quic://dns.google']; $cases[] = $s;
        $s = MihomoDns::defaults(); $s['providers'][0]['servers'] = ['https://user:password@dns.google/dns-query']; $cases[] = $s;
        foreach ($cases as $settings) {
            try {
                MihomoDns::compile($settings, MihomoProfile::build([], $this->options()));
                self::fail('Expected invalid DNS configuration to be rejected');
            } catch (InvalidArgumentException $error) {
                self::assertNotEmpty($error->getMessage());
            }
        }
    }
}
