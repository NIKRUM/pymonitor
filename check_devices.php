<?php
// check_devices.php
require 'logger.php';

// DB
include 'config.php';
try {
    $pdo = new PDO($db_dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}

$results = [];

/* --- 1. Sprawdź ext_devices --- */
$extDevices = $pdo->query("SELECT device_uuid, ip_address, hostname FROM ext_devices")->fetchAll();

foreach ($extDevices as $ext) {
    $uuid = $ext['device_uuid'];
    $name = $ext['hostname'] ?: $uuid;
    $ip   = $ext['ip_address'];

    $ping = shell_exec("ping -c 1 -W 2 " . escapeshellarg($ip) . " 2>/dev/null");

    $pingMs = null;
    if ($ping && preg_match('/time=([\d\.]+) ms/', $ping, $m)) {
        $pingMs = (int) $m[1];
    }

    // zapisz stan
    $stmt = $pdo->prepare("INSERT INTO ext_state (ext_device_uuid, ping_ms) VALUES (?, ?)");
    $stmt->execute([$uuid, $pingMs]);

    // logowanie
    if ($pingMs === null) {
        log_event($pdo, $uuid, $name, "Utrata połączenia z urządzeniem zewnętrznym");
    } elseif ($pingMs > 100) {
        log_event($pdo, $uuid, $name, "Zbyt wysoki ping: {$pingMs} ms");
    }

    $results[] = ["uuid" => $uuid, "ping" => $pingMs];
}

/* --- 2. Sprawdź devices (offline detection) --- */
$devices = $pdo->query("SELECT device_uuid, hostname FROM devices")->fetchAll();

foreach ($devices as $d) {
    $uuid = $d['device_uuid'];
    $name = $d['hostname'] ?: $uuid;

    // ostatni stan
    $stmt = $pdo->prepare("SELECT * FROM device_stats WHERE device_uuid=? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$uuid]);
    $last = $stmt->fetch();

    if ($last) {
        $lastTime = strtotime($last['timestamp']);
        $now = time();

        // jeśli internet = 1 i minęło >5 minut → dopisz wpis z internet=0
        if ($last['internet'] == 1 && ($now - $lastTime) > 300) {
            $stmt = $pdo->prepare("INSERT INTO device_stats 
                (device_uuid, internet, disk_total, disk_used, disk_free, disk_percent, cpu_temp, ram_used, ram_percent, ip_address, cpu_percent) 
                VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $uuid,
                $last['disk_total'],
                $last['disk_used'],
                $last['disk_free'],
                $last['disk_percent'],
                $last['cpu_temp'],
                $last['ram_used'],
                $last['ram_percent'],
                $last['ip_address'],
                $last['cpu_percent']
            ]);

            log_event($pdo, $uuid, $name, "Urządzenie uznane za offline (brak internetu >5 minut)");
        }
    }
}

header('Content-Type: application/json');
echo json_encode($results);
