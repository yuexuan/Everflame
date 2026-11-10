<?php

declare(strict_types=1);
namespace App\Controllers\Admin\Setting;

use App\Controllers\BaseController;
use App\Services\Subscribe\MihomoDns;
use App\Services\Subscribe\MihomoDnsSettings;
use App\Services\Subscribe\MihomoProfile;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class DnsController extends BaseController
{
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('admin/setting/dns.tpl'));
    }

    private function profile(): array
    {
        if (!is_array($_ENV['Clash_Mihomo_Profile'] ?? null)) {
            throw new \RuntimeException('请先启用 Clash_Mihomo_Profile，DNS 管理仅作用于 Mihomo 生成路径');
        }
        return MihomoProfile::build([], $_ENV['Clash_Mihomo_Profile']);
    }

    public function data(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        try {
            $profile = $this->profile();
            $stored = MihomoDnsSettings::load(true);
            $settings = $stored ?? MihomoDns::defaults();
            return $response->withJson(['ret' => 1, 'settings' => $settings,
                'saved' => $stored !== null, 'groups' => ['DIRECT', ...array_column($profile['proxy-groups'], 'name')],
                'rulesets' => array_keys(array_filter($profile['rule-providers'], static fn ($p) => $p['behavior'] === 'domain')),
                'preview' => $stored === null ? $profile['dns'] : MihomoDns::compile($settings, $profile)]);
        } catch (Throwable $error) {
            return $response->withJson(['ret' => 0, 'msg' => $error->getMessage()], 422);
        }
    }

    public function save(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        try {
            $settings = json_decode((string) $request->getParam('settings'), true, 64, JSON_THROW_ON_ERROR);
            $dns = MihomoDns::compile($settings, $this->profile());
            if ($request->getParam('preview') !== '1') {
                MihomoDnsSettings::save($settings);
            }
            return $response->withJson(['ret' => 1, 'preview' => $dns, 'msg' => '配置有效；保存后下一次订阅请求生效']);
        } catch (Throwable $error) {
            return $response->withJson(['ret' => 0, 'msg' => $error->getMessage()], 422);
        }
    }
}
