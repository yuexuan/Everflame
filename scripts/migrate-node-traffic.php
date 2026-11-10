<?php

declare(strict_types=1);

use App\Services\DB;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/.config.php';
DB::init();
$db = DB::connection();
$db->unprepared(file_get_contents(dirname(__DIR__) . '/db/node-traffic.sql'));
if ($db->getSchemaBuilder()->hasTable('ef_usage')) {
    $indexes = $db->select("SHOW INDEX FROM ef_usage WHERE Key_name = 'user_time'");
    if ($indexes === []) {
        $db->statement('ALTER TABLE ef_usage ADD INDEX user_time (user_id, charged, ended_at)');
    }
}
echo "Node traffic schema ready. Existing usage preserved.\n";
