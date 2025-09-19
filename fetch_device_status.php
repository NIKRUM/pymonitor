<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

include 'config.php';
$pdo = new PDO($db_dsn, $db_user, $db_pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$devices = $pdo->query("SELECT device_uuid FROM devices")->fetchAll(PDO::FETCH_ASSOC);
$stmt_last = $pdo->prepare("SELECT * FROM device_stats WHERE device_uuid=:uuid ORDER BY timestamp DESC LIMIT 1");

$result = [
    'devices' => [],
    'kpi' => ['online'=>0,'warn'=>0,'offline'=>0]
];

foreach($devices as $dev){
    $stmt_last->execute([':uuid'=>$dev['device_uuid']]);
    $stats = $stmt_last->fetch();

    $status_class = 'offline';

    if($stats){
        $last_ts = strtotime($stats['timestamp']);
        $now = time();
        $minutes_diff = ($now - $last_ts)/60;

        if($minutes_diff <= 5 && $stats['internet']==1){
            $status_class = ($stats['cpu_temp']>70 || $stats['ram_percent']>80 || $stats['disk_percent']>80) ? 'warn' : 'online';
        }
    }

    // Zliczanie KPI
    if($status_class=='online') $result['kpi']['online']++;
    elseif($status_class=='warn') $result['kpi']['warn']++;
    else $result['kpi']['offline']++;

    $result['devices'][$dev['device_uuid']] = [
        'status_class' => $status_class,
        'status_text' => ($status_class=='online') ? 'Online' : (($status_class=='warn') ? 'Ostrzeżenie' : 'Offline'),
        'last_timestamp' => $stats['timestamp'] ?? null
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
