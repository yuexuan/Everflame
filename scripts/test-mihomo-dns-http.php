<?php
// 隔离的 HTTP 测试夹具；仅通过 php -S 和 MIHOMO_DNS_TEST=1 运行，不加载生产配置。
declare(strict_types=1);
if (getenv('MIHOMO_DNS_TEST') !== '1' || PHP_SAPI !== 'cli-server') { exit(1); }
require '/var/www/html/vendor/autoload.php';
spl_autoload_register(static function ($name) {
    if (str_starts_with($name, 'App\\')) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($name, 4)) . '.php';
        if (is_file($file)) { require_once $file; }
    }
}, true, true);
if (!is_file('/tmp/mihomo-dns-test.sqlite')) { touch('/tmp/mihomo-dns-test.sqlite'); }
$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection(['driver' => 'sqlite', 'database' => '/tmp/mihomo-dns-test.sqlite']);
$capsule->setAsGlobal(); $capsule->bootEloquent();
$pdo = $capsule->getConnection()->getPdo();
$pdo->exec("CREATE TABLE IF NOT EXISTS config (id INTEGER PRIMARY KEY, item TEXT UNIQUE, value TEXT, class TEXT, type TEXT, is_public INTEGER, `default` TEXT, mark TEXT);
CREATE TABLE IF NOT EXISTS user (id INTEGER PRIMARY KEY, is_banned INTEGER, is_admin INTEGER, class INTEGER, node_group INTEGER, u INTEGER, d INTEGER, transfer_enable INTEGER, class_expire TEXT, port INTEGER, passwd TEXT, method TEXT);
CREATE TABLE IF NOT EXISTS link (id INTEGER PRIMARY KEY, userid INTEGER, token TEXT);
CREATE TABLE IF NOT EXISTS node (id INTEGER PRIMARY KEY, type INTEGER, node_class INTEGER, node_group INTEGER, node_bandwidth_limit INTEGER, node_bandwidth INTEGER, name TEXT, sort INTEGER, server TEXT, custom_config TEXT);
INSERT OR IGNORE INTO user VALUES (1,0,0,1,0,0,0,10000,'2099-01-01',443,'fixture-password','aes-128-gcm');
INSERT OR IGNORE INTO user VALUES (2,0,0,0,0,0,0,10000,'2099-01-01',443,'other-fixture','aes-128-gcm');
INSERT OR IGNORE INTO link VALUES (1,1,'fixture-nodes');
INSERT OR IGNORE INTO link VALUES (2,2,'fixture-empty');
INSERT OR IGNORE INTO node VALUES (1,1,1,0,0,0,'Fixture SS',0,'192.0.2.1','{}');");
$_ENV = ['Clash_Mihomo_Profile' => require dirname(__DIR__) . '/config/profiles/mihomo.php',
    'Subscribe' => true, 'subUrl' => 'http://127.0.0.1:18891', 'baseUrl' => 'http://127.0.0.1:18891', 'appName' => 'DNS fixture', 'enable_rate_limit' => false];
$user = App\Models\User::find(1);
$request = new Slim\Http\ServerRequest(GuzzleHttp\Psr7\ServerRequest::fromGlobals());
$response = new Slim\Http\Response(new GuzzleHttp\Psr7\Response(), new GuzzleHttp\Psr7\HttpFactory());
$path = $request->getUri()->getPath();
if (in_array($path, ['/js/mihomo-dns.js', '/css/mihomo-dns.css'], true)) {
    header('Content-Type: ' . (str_ends_with($path, '.js') ? 'text/javascript' : 'text/css'));
    readfile(dirname(__DIR__) . '/public' . $path); exit;
}
if (preg_match('~^/qa/(malio|tabler)$~', $path, $theme)) {
    $template = file_get_contents(dirname(__DIR__) . '/resources/views/' . $theme[1] . '/admin/setting/dns.tpl');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css"></head><body>';
    echo preg_replace("/\\{include[^}]+\\}/", '', $template);
    echo '</body></html>'; exit;
}
if (preg_match('~^/sub/(fixture-nodes|fixture-empty)/clash$~', $path, $matches)) {
    $response = (new App\Controllers\SubController())->index($request, $response, ['token' => $matches[1], 'subtype' => 'clash']);
} elseif ($path === '/admin/setting/dns/data') {
    $response = (new App\Controllers\Admin\Setting\DnsController())->data($request, $response, []);
} elseif ($path === '/admin/setting/dns' && $request->getMethod() === 'POST') {
    $response = (new App\Controllers\Admin\Setting\DnsController())->save($request, $response, []);
} else { http_response_code(404); exit; }
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $key => $values) { header($key . ': ' . implode(', ', $values)); }
echo $response->getBody();
