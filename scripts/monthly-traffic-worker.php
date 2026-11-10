<?php

declare(strict_types=1);

use App\Models\Config;
use App\Services\DB;
use App\Services\MonthlyTrafficReset;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/.config.php';

date_default_timezone_set($_ENV['timeZone']);
DB::init();
DB::connection()->disableQueryLog();
$once = in_array('--once', $argv, true);

do {
    try {
        if (Config::obtain('monthly_quota_worker_enabled')) {
            $count = MonthlyTrafficReset::run(new DateTimeImmutable());
            if ($count > 0 || $once) {
                echo date(DATE_ATOM), ' monthly traffic reset: ', $count, " users\n";
            }
        }
    } catch (Throwable $error) {
        fwrite(STDERR, date(DATE_ATOM) . ' monthly traffic reset failed (' . $error::class . ")\n");
        if ($once) {
            exit(1);
        }
    }
    if (! $once) {
        sleep(300);
    }
} while (! $once);
