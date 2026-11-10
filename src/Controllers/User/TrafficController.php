<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Services\NodeTraffic;
use App\Services\TrafficWindow;
use App\Utils\Tools;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class TrafficController extends BaseController
{
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $now = new DateTimeImmutable();
        $error = null;
        try {
            $query = $request->getQueryParams();
            foreach (['range', 'start', 'end'] as $key) {
                if (isset($query[$key]) && ! is_string($query[$key])) {
                    throw new InvalidArgumentException('时间范围参数无效。');
                }
            }
            $window = TrafficWindow::resolve($query['range'] ?? '7d', $now, $query['start'] ?? null, $query['end'] ?? null);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $response = $response->withStatus(400);
            $window = TrafficWindow::resolve('7d', $now);
        }
        $series = NodeTraffic::report(NodeTraffic::buckets((int) $this->user->id, $window), $window);
        foreach ($series['nodes'] as &$node) {
            $node['formatted'] = Tools::autoBytes($node['bytes']);
        }
        unset($node);
        $used = max(0, (int) $this->user->u + (int) $this->user->d);
        $allowance = max(0, (int) $this->user->transfer_enable);
        $percent = $allowance > 0 ? round($used / $allowance * 100, 1) : 0;

        return $response->write($this->view()
            ->assign('traffic', $series)
            ->assign('traffic_window', $window)
            ->assign('traffic_error', $error)
            ->assign('traffic_json', json_encode($series['points'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR))
            ->assign('traffic_enabled', (bool) Config::obtain('traffic_log'))
            ->assign('retention_days', max(186, (int) Config::obtain('traffic_log_retention_days')))
            ->assign('traffic_date', $now->format('Y-m-d'))
            ->assign('traffic_calendar_json', json_encode(['today' => $now->format('Y-m-d'), 'start' => $window['start_date'], 'end' => $window['end_date'], 'range' => $window['range']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR))
            ->assign('traffic_timezone', $now->getTimezone()->getName())
            ->assign('used_traffic', Tools::autoBytes($used))
            ->assign('upload_traffic', Tools::autoBytes(max(0, (int) $this->user->u)))
            ->assign('download_traffic', Tools::autoBytes(max(0, (int) $this->user->d)))
            ->assign('remaining_traffic', Tools::autoBytes(max(0, $allowance - $used)))
            ->assign('total_traffic', Tools::autoBytes($allowance))
            ->assign('today_traffic', Tools::autoBytes(max(0, (int) $this->user->transfer_today)))
            ->assign('usage_percent', $percent)
            ->assign('progress_percent', min(100, $percent))
            ->assign('range_total', Tools::autoBytes((int) $series['total']))
            ->fetch('user/traffic.tpl'));
    }
}
