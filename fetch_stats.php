<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Brak dostępu']);
    exit;
}

// konfiguracja bazy
include 'config.php';

try {
    $pdo = new PDO($db_dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// pobierz wszystkie urządzenia
$devices = $pdo->query("SELECT * FROM devices")->fetchAll(PDO::FETCH_ASSOC);

$stats_stmt = $pdo->prepare("SELECT * FROM device_stats WHERE device_uuid=:uuid ORDER BY timestamp DESC LIMIT 1");

$online_count = $warn_count = $offline_count = 0;
$alerts = [];
$devices_out = [];

foreach ($devices as $device) {
    $stats_stmt->execute([':uuid'=>$device['device_uuid']]);
    $s = $stats_stmt->fetch();

    $status_class = 'offline';
    $status_text = 'Offline';
    if ($s) {
        if ($s['internet']==1) {
            $status_class = ($s['cpu_temp']>70 || $s['ram_percent']>80 || $s['disk_percent']>80) ? 'warn' : 'online';
            $status_text = ($status_class=='warn') ? 'Ostrzeżenie' : 'Online';
            $online_count++;
            if ($status_class=='warn') $warn_count++;
        } else {
            $offline_count++;
        }
    } else {
        $offline_count++;
    }

    // alerty
    if ($s) {
        if ($s['internet']==0) {
            $alerts[] = ['type'=>'CRIT','class'=>'crit','message'=>"{$device['hostname']} jest offline (ostatni ping {$s['timestamp']})"];
        }
        if ($s['cpu_temp']>70 || $s['ram_percent']>80 || $s['disk_percent']>80) {
            $alerts[] = ['type'=>'WARN','class'=>'warn','message'=>"{$device['hostname']} wymaga uwagi (CPU {$s['cpu_temp']}°C, RAM {$s['ram_percent']}%, Dysk {$s['disk_percent']}%)"];
        }
    }

    $devices_out[] = [
        'device_uuid'=>$device['device_uuid'],
        'ip_address'=>$s['ip_address'] ?? '-',
        'status_class'=>$status_class,
        'status_text'=>$status_text,
        'last_ping'=>$s['timestamp'] ?? '-',
        'ram_used'=>$s['ram_used'] ?? null,
        'disk_percent'=>$s['disk_percent'] ?? null
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'kpi'=>[
        'online'=>$online_count,
        'warn'=>$warn_count,
        'offline'=>$offline_count
    ],
    'devices'=>$devices_out,
    'alerts'=>$alerts
]);
