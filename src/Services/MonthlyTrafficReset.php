<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Models\User;
use App\Utils\Tools;
use DateTimeImmutable;

final class MonthlyTrafficReset
{
    public static function periodAt(DateTimeImmutable $now, int $resetDay): string
    {
        $day = min(max($resetDay, 1), (int) $now->format('t'));
        $month = $now->modify('first day of this month')->setTime(0, 0);

        return ((int) $now->format('j') < $day ? $month->modify('-1 month') : $month)->format('Y-m');
    }

    /** 每个计费月为用户重置一次额度，包括服务停机后的补重置。 */
    public static function run(DateTimeImmutable $now): int
    {
        $db = DB::connection();
        $lock = 'everflame-quota-' . sha1($db->getDatabaseName());
        if ((int) $db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            return 0;
        }

        try {
            return $db->transaction(static function () use ($now): int {
                $count = 0;
                $activation = Config::firstOrCreate(['item' => 'monthly_quota_started_at'], [
                    'value' => $now->format(DATE_ATOM), 'default' => '', 'class' => 'runtime',
                    'is_public' => 0, 'type' => 'string', 'mark' => '月度流量任务首次启用时间',
                ]);
                $startedAt = new DateTimeImmutable($activation->value);
                $users = User::where('class', 0)->where('is_banned', 0)
                    ->whereBetween('auto_reset_day', [1, 31])
                    ->where('auto_reset_bandwidth', '>', 0)->lockForUpdate()->get();
                foreach ($users as $user) {
                    $period = self::periodAt($now, (int) $user->auto_reset_day);
                    $registeredAt = new DateTimeImmutable($user->reg_date, $now->getTimezone());
                    $initialPeriod = self::periodAt($registeredAt > $startedAt ? $registeredAt : $startedAt, (int) $user->auto_reset_day);
                    $state = Config::firstOrCreate(['item' => 'monthly_quota_user_' . $user->id], [
                        'value' => $initialPeriod, 'default' => '', 'class' => 'runtime',
                        'is_public' => 0, 'type' => 'string', 'mark' => '用户月度流量最近处理周期',
                    ]);
                    // 保留初始周期的用量，同时补做停机期间错过的首次重置。
                    if ($state->value >= $period) {
                        continue;
                    }
                    $user->u = 0;
                    $user->d = 0;
                    $user->transfer_today = 0;
                    $user->transfer_enable = Tools::gbToB($user->auto_reset_bandwidth);
                    $user->save();
                    $state->value = $period;
                    $state->save();
                    $count++;
                }

                return $count;
            });
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }
}
