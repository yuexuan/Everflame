<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Subscribe\ShadowsocksCredentials;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/Subscribe/ShadowsocksCredentials.php';

final class ShadowsocksCredentialsTest extends TestCase
{
    private function imported(): array
    {
        return ['external_shadowsocks' => [
            'owner_user_id' => 1, 'port' => 1443, 'password' => 'external-fixture', 'method' => 'aes-128-gcm',
        ]];
    }

    public function testNativeNodesRetainPanelCredentials(): void
    {
        $user = (object) ['id' => 1, 'port' => 12345, 'passwd' => 'panel-fixture', 'method' => 'aes-256-gcm'];
        self::assertSame(['port' => 12345, 'password' => 'panel-fixture', 'method' => 'aes-256-gcm'], ShadowsocksCredentials::resolve([], $user));
        self::assertTrue(ShadowsocksCredentials::visibleTo([], 2));
    }

    public function testImportedNodePreservesItsOwnPortAndPasswordWithoutMutatingUser(): void
    {
        $user = (object) ['id' => 1, 'port' => 12345, 'passwd' => 'panel-fixture', 'method' => 'aes-256-gcm'];
        $before = clone $user;
        self::assertSame(['port' => 1443, 'password' => 'external-fixture', 'method' => 'aes-128-gcm'], ShadowsocksCredentials::resolve($this->imported(), $user));
        self::assertEquals($before, $user);
    }

    public function testOtherUsersCannotReceiveImportedCredentialsEvenIfAdmin(): void
    {
        self::assertFalse(ShadowsocksCredentials::visibleTo($this->imported(), 2));
        $this->expectException(InvalidArgumentException::class);
        ShadowsocksCredentials::resolve($this->imported(), (object) ['id' => 2, 'is_admin' => 1]);
    }

    public function testMissingOrInvalidOwnerFailsClosed(): void
    {
        self::assertFalse(ShadowsocksCredentials::visibleTo(['external_shadowsocks' => []], 1));
        self::assertFalse(ShadowsocksCredentials::visibleTo(['external_shadowsocks' => null], 1));
        self::assertFalse(ShadowsocksCredentials::visibleTo(['external_shadowsocks' => ['owner_user_id' => 0]], 0));
    }

    public function testInvalidImportedPortDoesNotFallBackToPanelPort(): void
    {
        $config = $this->imported();
        $config['external_shadowsocks']['port'] = 0;
        $this->expectException(InvalidArgumentException::class);
        ShadowsocksCredentials::resolve($config, (object) ['id' => 1, 'port' => 443]);
    }
}
