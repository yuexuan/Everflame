<?php

declare(strict_types=1);

namespace App\Utils;

final class Cookie
{
    public static function set(array $arg, int $time): void
    {
        foreach ($arg as $key => $value) {
            setcookie($key, $value, $time, path: '/', secure: true, httponly: true);
        }
    }

    public static function setWithDomain(array $arg, int $time, string $domain): void
    {
        // Cookie domains cannot contain a port. Local hosts/IPs use host-only cookies.
        $host = parse_url('http://' . $domain, PHP_URL_HOST) ?: $domain;
        $cookieDomain = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) ? '' : $host;
        foreach ($arg as $key => $value) {
            setcookie($key, $value, $time, path: '/', domain: $cookieDomain, secure: true, httponly: true);
        }
    }

    public static function get(string $key): string
    {
        return $_COOKIE[$key] ?? '';
    }
}
