<?php
session_start();
if(!isset($_SESSION['user_id'])){
    http_response_code(403);
    exit;
}

include 'config.php';

$ext_devices = $pdo->query("SELECT * FROM ext_devices")->fetchAll();
$stmt_last = $pdo->prepare("SELECT * FROM ext_state WHERE ext_device_uuid=:uuid ORDER BY timestamp DESC LIMIT 1");

$result = [];

foreach($ext_devices as $dev){
    $stmt_last->execute([':uuid'=>$dev['device_uuid']]);
    $state = $stmt_last->fetch();

    $status_class = 'offline';
    $status_text = 'Offline';
    $last_ping = $state['timestamp'] ?? null;
    $ping_ms = $state['ping_ms'] ?? null;

    if($ping_ms !== null && $last_ping){
        $now = new DateTime('now', new DateTimeZone('Europe/Warsaw')); // ustaw swoją strefę
        $ping_time = new DateTime($last_ping, new DateTimeZone('Europe/Warsaw'));
        $interval = $now->getTimestamp() - $ping_time->getTimestamp();
        $minutes_diff = $interval / 60;

        if($minutes_diff <= 5){
            $status_class = 'online';
            $status_text = 'Online';
        }
    }

    $result[$dev['device_uuid']] = [
        'device_type'=>$dev['device_type'],
        'hostname'=>$dev['hostname'],
        'ip_address'=>$dev['ip_address'],
        'status_class'=>$status_class,
        'status_text'=>$status_text,
        'last_ping'=>$last_ping,
        'ping_ms'=>$ping_ms
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
