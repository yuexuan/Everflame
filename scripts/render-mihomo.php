<?php

declare(strict_types=1);

// 离线组合 SLINX 导出的 Clash/Mihomo 节点与 EverFlame 模板；不导入机场数据库。
require_once dirname(__DIR__) . '/src/Services/Subscribe/MihomoProfile.php';

use App\Services\Subscribe\MihomoProfile;

if ($argc !== 3 || ! extension_loaded('yaml')) {
    fwrite(STDERR, "Usage: php scripts/render-mihomo.php <nodes.yaml> <output.yaml> (ext-yaml required)\n");
    exit(1);
}

try {
    $input = yaml_parse_file($argv[1]);
    if (! is_array($input) || ! isset($input['proxies']) || ! is_array($input['proxies'])) {
        throw new RuntimeException('Input must contain a proxies array from a Mihomo/Clash export.');
    }
    if (($input['proxy-providers'] ?? []) !== []) {
        throw new RuntimeException('Export concrete proxies first; remote proxy-providers are not imported.');
    }
    $options = require dirname(__DIR__) . '/config/profiles/mihomo.php';
    $profile = MihomoProfile::build($input['proxies'], $options);
    if (file_put_contents($argv[2], yaml_emit($profile, YAML_UTF8_ENCODING)) === false) {
        throw new RuntimeException('Cannot write output file.');
    }
    chmod($argv[2], 0600);
    fwrite(STDERR, 'Rendered ' . count($input['proxies']) . " proxies. Output contains node credentials.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
