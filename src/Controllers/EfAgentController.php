<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DB;
use App\Services\EfSync;
use InvalidArgumentException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class EfAgentController
{
    public function handle(ServerRequest $request, Response $response, array $args): Response
    {
        $id = $request->getHeaderLine('X-EF-Agent');
        $token = $request->getHeaderLine('Authorization');
        $observer = in_array($args['action'], ['probe-state', 'probe'], true);
        $agent = DB::connection()->table('ef_agents')->where('id', $id)->where('enabled', 1)->first();
        if ($request->getHeaderLine('Host') !== parse_url($_ENV['baseUrl'], PHP_URL_HOST)
            || ! $agent || ! str_starts_with($token, 'Bearer ')
            || ! hash_equals($observer ? $agent->observer_hash : $agent->token_hash, hash('sha256', substr($token, 7)))) {
            return $response->withJson(['error' => 'Unauthorized'], 401);
        }
        $response = $response->withHeader('Cache-Control', 'no-store');
        $action = $args['action'];
        $expectedMethod = in_array($action, ['desired-state', 'probe-state'], true) ? 'GET' : 'POST';
        if ($request->getMethod() !== $expectedMethod) {
            return $response->withJson(['error' => 'Method not allowed'], 405);
        }
        try {
            if ($action === 'desired-state') {
                return $response->withJson(EfSync::desired($id));
            }
            if ($action === 'probe-state') {
                $state = EfSync::desired($id);
                foreach ($state['bindings'] as &$binding) {
                    unset($binding['users']);
                }
                return $response->withJson(['bindings' => $state['bindings']]);
            }
            $body = (string) $request->getBody();
            if (strlen($body) > 2_000_000) {
                throw new InvalidArgumentException('Payload too large');
            }
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new InvalidArgumentException('Expected object');
            }
            if ($action === 'usage') {
                if (! is_array($data['events'] ?? null)) {
                    throw new InvalidArgumentException('Expected events');
                }
                return $response->withJson(['ack' => EfSync::usage($id, $data['events'])]);
            }
            if ($action === 'heartbeat') {
                if (! in_array($data['core_status'] ?? '', ['running', 'stopped'], true)
                    || ! is_string($data['revision'] ?? null) || strlen($data['revision']) > 64
                    || ! is_string($data['error'] ?? null) || strlen($data['error']) > 1000) {
                    throw new InvalidArgumentException('Invalid heartbeat');
                }
                DB::connection()->table('ef_agents')->where('id', $id)->update([
                    'last_seen' => time(), 'core_status' => $data['core_status'],
                    'applied_revision' => $data['revision'], 'error' => $data['error'],
                ]);
                return $response->withJson(['ok' => true]);
            }
            if ($action === 'probe') {
                if (! is_int($data['binding_id'] ?? null) || ! is_bool($data['ok'] ?? null)
                    || (isset($data['inconclusive']) && ! is_bool($data['inconclusive']))
                    || ! is_string($data['reason'] ?? null) || strlen($data['reason']) > 255) {
                    throw new InvalidArgumentException('Invalid probe');
                }
                DB::connection()->transaction(static function () use ($id, $data): void {
                    $q = DB::connection()->table('ef_bindings')->where('agent_id', $id)->where('id', $data['binding_id']);
                    $b = $q->lockForUpdate()->first();
                    if (! $b) {
                        throw new InvalidArgumentException('Unauthorized binding');
                    }
                    if ($data['inconclusive'] ?? false) {
                        $q->update(['successes' => 0, 'failures' => 0, 'probe_at' => time(),
                            'probe_reason' => $data['reason'], 'probe_ok' => 0]);
                        return;
                    }
                    $successes = $data['ok'] ? $b->successes + 1 : 0;
                    $failures = $data['ok'] ? 0 : $b->failures + 1;
                    $q->update(['successes' => $successes, 'failures' => $failures,
                        'probe_at' => time(), 'probe_reason' => $data['reason'],
                        'probe_ok' => $successes >= 2 ? 1 : ($failures >= 3 ? 0 : $b->probe_ok)]);
                });
                return $response->withJson(['ok' => true]);
            }
            return $response->withJson(['error' => 'Unknown action'], 404);
        } catch (InvalidArgumentException|\JsonException $e) {
            return $response->withJson(['error' => $e->getMessage()], 422);
        }
    }
}
