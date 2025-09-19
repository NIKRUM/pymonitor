<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$uuid = $_GET['uuid'] ?? '';
if(!$uuid) exit;

include 'config.php';
$pdo = new PDO($db_dsn, $db_user, $db_pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $pdo->prepare("SELECT timestamp, ram_used, cpu_temp, cpu_percent FROM device_stats WHERE device_uuid=:uuid ORDER BY timestamp DESC LIMIT 20");
$stmt->execute([':uuid'=>$uuid]);
$rows = array_reverse($stmt->fetchAll());

$timestamps = [];
$ram_gb = [];
$cpu_temp = [];
$cpu_percent = [];

foreach($rows as $r){
    $timestamps[] = $r['timestamp'];
    $ram_gb[] = round($r['ram_used']/1024/1024/1024,2);
    $cpu_temp[] = $r['cpu_temp'];
    $cpu_percent[] = $r['cpu_percent'];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'timestamps'=>$timestamps,
    'ram_gb'=>$ram_gb,
    'cpu_temp'=>$cpu_temp,
    'cpu_percent'=>$cpu_percent
]);

