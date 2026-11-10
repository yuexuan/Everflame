<?php

declare(strict_types=1);

use App\Models\Node;
use App\Models\User;
use App\Services\DB;
use App\Services\EfSync;
use App\Services\Subscribe;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/.config.php';
require dirname(__DIR__) . '/config/appprofile.php';
date_default_timezone_set($_ENV['timeZone']);
DB::init();
$agentId = $argv[1] ?? 'ef-hk-01';
$state = EfSync::desired($agentId);
$passwords = [];
foreach ([1, 2] as $uid) {
    $user = User::findOrFail($uid);
    $passwords[$uid] = [];
    foreach ($state['bindings'] as $b) {
        $passwords[$uid][$b['id']] = EfSync::credential($b['id'], $uid);
    }
    foreach (['clash', 'singbox', 'sip008', 'v2rayjson', 'ss', 'sip002'] as $format) {
        $raw = Subscribe::getContent($user, $format);
        if ($format === 'ss') {
            $decoded = '';
            foreach (explode("\n", trim($raw)) as $line) {
                $decoded .= base64_decode(explode('#', $line)[0], true);
            }
            $raw = $decoded;
        }
        // 检查共享解析器标识前，先统一 JSON 转义形式。
        $raw = str_replace(['\\/', '\\u002F'], '/', $raw);
        foreach ($passwords[$uid] as $password) {
            if (! str_contains($raw, $password)) {
                throw new RuntimeException("Credential mismatch: user=$uid format=$format");
            }
        }
        echo "PASS credentials user=$uid format=$format\n";
    }
}
foreach ($passwords[1] as $binding => $password) {
    if ($password === $passwords[2][$binding]) {
        throw new RuntimeException('Shared user identity detected');
    }
}
echo "PASS distinct identities on every line\n";
$db = DB::connection();
foreach ($db->table('ef_usage')->where('agent_id', $agentId)->where('charged', 1)
    ->select('user_id')->distinct()->get() as $row) {
    $user = User::find($row->user_id);
    $sum = $db->table('ef_usage')->where('agent_id', $agentId)->where('charged', 1)->where('user_id', $row->user_id)
        ->where('period', EfSync::period($user, time()))->selectRaw('SUM(billed_u) AS u, SUM(billed_d) AS d')->first();
    if ($user && ((int) $user->u !== (int) $sum->u || (int) $user->d !== (int) $sum->d)) {
        throw new RuntimeException('Current-cycle counter differs from ledger for user ' . $row->user_id);
    }
}
echo "PASS current-cycle counters equal charged ledger\n";
foreach (Node::where('type', 1)->get() as $node) {
    echo json_encode(['node_id' => $node->id, 'name' => $node->name, 'color' => $node->color,
        'reason' => $node->healthReason()], JSON_UNESCAPED_UNICODE), PHP_EOL;
}
