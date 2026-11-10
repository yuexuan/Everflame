<?php

declare(strict_types=1);
namespace App\Services\Subscribe;

use App\Models\Config;
use Throwable;

final class MihomoDnsSettings
{
    public const ITEM = 'mihomo_dns';

    public static function load(): ?array
    {
        try {
            $row = (new Config())->where('item', self::ITEM)->first();
            if ($row === null) {
                return null;
            }
            $value = json_decode($row->value, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($value)) {
                throw new \UnexpectedValueException('DNS settings must be an object');
            }
            return $value;
        } catch (Throwable $error) {
            error_log('Mihomo DNS settings unavailable: ' . get_class($error));
            return null;
        }
    }

    public static function save(array $settings): void
    {
        Config::updateOrCreate(['item' => self::ITEM], [
            'value' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'class' => 'mihomo_dns', 'type' => 'string', 'is_public' => 0,
            'default' => '', 'mark' => 'Mihomo DNS Provider / Policy',
        ]);
    }
}
