<?php

declare(strict_types=1);

use App\Models\Node;
use App\Models\User;
use App\Services\DB;
use App\Services\EfSync;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/.config.php';
date_default_timezone_set($_ENV['timeZone']);
if (! str_ends_with($_ENV['db_database'], '_sync_test')) {
    throw new RuntimeException('Run against an isolated *_sync_test database only');
}
DB::init();
function check(bool $ok, string $message): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message, PHP_EOL;
}
$db = DB::connection();
$agent = 'ef-hk-01';
$db->table('ef_usage')->delete();
$db->table('ef_agents')->where('id', $agent)->update(['billing_start' => time() - 7200]);
$state = EfSync::desired($agent);
$b = $state['bindings'][0];
$uid = $b['users'][0]['id'];
$user = User::findOrFail($uid);
$user->auto_reset_day = 1;
$user->auto_reset_bandwidth = 300;
$user->is_banned = 0;
$user->u = 100;
$user->d = 200;
$user->save();
EfSync::desired($agent);
$event = ['event_id' => 'integration-1', 'binding_id' => $b['id'], 'user_id' => $uid,
    'epoch' => 'test_epoch', 'seq' => 1, 'started_at' => time() - 2, 'ended_at' => time() - 1,
    'raw_u' => 101, 'raw_d' => 202, 'tariff_id' => $b['tariff_id']];
$before = User::find($uid);
$ack = EfSync::usage($agent, [$event]);
$once = User::find($uid);
check($ack === ['integration-1'] && $once->u === $before->u + 101 && $once->d === $before->d + 202, 'real transaction debits raw bytes once');
EfSync::usage($agent, [$event]);
$again = User::find($uid);
check($again->u === $once->u && $again->d === $once->d, 'lost ACK replay does not debit twice');
$changed = $event;
$changed['raw_d']++;
try {
    EfSync::usage($agent, [$changed]);
    throw new RuntimeException('conflicting event accepted');
} catch (InvalidArgumentException) {
    check(true, 'same event ID with changed payload rejected');
}
$valid = $event;
$valid['event_id'] = 'rolled-back';
$bad = $event;
$bad['event_id'] = 'unauthorized';
$bad['binding_id'] = 999999;
try {
    EfSync::usage($agent, [$valid, $bad]);
    throw new RuntimeException('unauthorized binding accepted');
} catch (InvalidArgumentException) {
    check(! $db->table('ef_usage')->where('event_id', 'rolled-back')->exists(), 'invalid batch rolls back every event');
}
$late = $event;
$late['event_id'] = 'last-month';
$late['started_at'] = strtotime('first day of last month 12:00:00');
$late['ended_at'] = $late['started_at'] + 1;
$db->table('ef_agents')->where('id', $agent)->update(['billing_start' => $late['started_at'] - 1]);
$db->table('ef_tariffs')->where('id', $b['tariff_id'])->update(['effective_at' => $late['started_at'] - 1]);
EfSync::usage($agent, [$late]);
check(User::find($uid)->u === $once->u && User::find($uid)->d === $once->d, 'late previous-cycle event does not debit current allowance');
check($db->table('ef_usage')->where('event_id', 'last-month')->value('charged') === 1, 'late event retained as charged historical ledger');
$future = $event;
$future['event_id'] = 'future';
$future['ended_at'] = time() + 600;
try {
    EfSync::usage($agent, [$future]);
    throw new RuntimeException('future event accepted');
} catch (InvalidArgumentException) {
    check(true, 'future timestamps rejected');
}
$binding = $db->table('ef_bindings')->where('id', $b['id']);
$binding->update(['probe_at' => time(), 'probe_ok' => 1, 'successes' => 2]);
$db->table('ef_agents')->where('id', $agent)->update(['last_seen' => time(), 'core_status' => 'running', 'error' => '']);
check(EfSync::health($b['id'])[0] === 1, 'green requires recent external probe and running core');
$db->table('ef_agents')->where('id', $agent)->update(['core_status' => 'stopped']);
check(EfSync::health($b['id'])[0] === -1, 'core stopped overrides previously green probe');
$db->table('ef_agents')->where('id', $agent)->update(['core_status' => 'running']);
$binding->update(['probe_at' => time() - 181]);
check(EfSync::health($b['id'])[0] === 0, 'expired probe cannot stay green');
echo "ALL EF SYNC INTEGRATION CHECKS PASSED\n";
