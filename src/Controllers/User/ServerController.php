<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Services\Subscribe;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function array_key_last;
use function ksort;
use function preg_match;
use const SORT_NUMERIC;

final class ServerController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodes = Subscribe::getUserNodes($this->user, true);
        $node_list = [];
        $node_groups = [];
        // 地区名称可能位于线路标签的路由前缀之后，而非标签开头。
        $regions = [
            'hk' => '香港|Hong Kong|🇭🇰', 'tw' => '台湾|台灣|Taiwan|🇹🇼',
            'sg' => '新加坡|Singapore|🇸🇬', 'jp' => '日本|Japan|🇯🇵',
            'us' => '美国|美國|United States|USA|🇺🇸', 'kr' => '韩国|韓國|Korea|🇰🇷',
            'gb' => '英国|英國|United Kingdom|Britain|🇬🇧', 'de' => '德国|德國|Germany|🇩🇪',
            'fr' => '法国|法國|France|🇫🇷', 'ca' => '加拿大|Canada|🇨🇦',
            'au' => '澳大利亚|澳洲|Australia|🇦🇺', 'ru' => '俄罗斯|俄羅斯|Russia|🇷🇺',
            'mo' => '澳门|澳門|Macao|Macau|🇲🇴', 'my' => '马来西亚|馬來西亞|Malaysia|🇲🇾',
            'th' => '泰国|泰國|Thailand|🇹🇭', 'vn' => '越南|Vietnam|🇻🇳',
            'ph' => '菲律宾|菲律賓|Philippines|🇵🇭', 'id' => '印尼|印度尼西亚|Indonesia|🇮🇩',
            'in' => '印度(?!尼)|India(?!nesia)|🇮🇳', 'nl' => '荷兰|荷蘭|Netherlands|🇳🇱',
            'tr' => '土耳其|Turkey|Türkiye|🇹🇷', 'br' => '巴西|Brazil|🇧🇷',
            'cn' => '中国|中國|回国|回國|China|🇨🇳',
        ];

        foreach ($nodes as $node) {
            $flag = '';
            foreach ($regions as $code => $pattern) {
                if (preg_match('/(?:' . $pattern . ')|(?:^|[\s_\-])' . $code . '(?=$|[\s_\-\d])/iu', $node->name)) {
                    $flag = $code;
                    break;
                }
            }
            $node_list[] = [
                'id' => $node->id,
                'name' => $node->name,
                'class' => (int) $node->node_class,
                'color' => $node->color,
                'connection_type' => $node->connection_type,
                'sort' => $node->sort(),
                'online_user' => $node->online_user,
                'online' => $node->getNodeOnlineStatus(),
                'health_reason' => $node->healthReason(),
                'traffic_rate' => $node->traffic_rate,
                'is_dynamic_rate' => $node->is_dynamic_rate,
                'region_flag' => $flag,
                'speed_limit' => (float) $node->node_speedlimit,
                'node_bandwidth' => Tools::autoBytes($node->node_bandwidth),
                'node_bandwidth_limit' => $node->node_bandwidth_limit === 0 ? '无限制' :
                    Tools::autoBytes($node->node_bandwidth_limit),
            ];
            $node_groups[(int) $node->node_class][] = $node_list[array_key_last($node_list)];
        }
        ksort($node_groups, SORT_NUMERIC);

        return $response->write(
            $this->view()
                ->assign('servers', $node_list)
                ->assign('server_groups', $node_groups)
                ->fetch('user/server.tpl')
        );
    }
}
