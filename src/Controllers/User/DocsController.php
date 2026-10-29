<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\Docs;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class DocsController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('display_docs') ||
            (Config::obtain('display_docs_only_for_paid_user') && $this->user->class === 0)) {
            return $response->withRedirect('/user');
        }

        $docs = (new Docs())->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id', 'desc')->get();

        return $response->write(
            $this->view()
                ->assign('docs', $docs)
                ->assign('tutorial_os', $this->tutorialPlatform($request))
                ->fetch('user/docs/index.tpl')
        );
    }

    private function tutorialPlatform(ServerRequest $request): string
    {
        $platform = $request->getQueryParams()['os'] ?? '';

        return is_string($platform) && in_array($platform, ['windows', 'android', 'ios', 'mac', 'linux', 'faq'], true)
            ? $platform : '';
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('display_docs') ||
            (Config::obtain('display_docs_only_for_paid_user') && $this->user->class === 0)) {
            return $response->withRedirect('/user/docs');
        }

        $doc = (new Docs())->where('status', 1)->where('id', $args['id'])->first();

        if (! $doc) {
            return $response->withRedirect('/user/docs');
        }

        return $response->write(
            $this->view()
                ->assign('doc', $doc)
                ->fetch('user/docs/view.tpl')
        );
    }
}
