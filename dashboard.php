<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

include 'config.php';

$username = htmlspecialchars($_SESSION['username']);
$role = $_SESSION['role'] ?? 'user';

// Pobierz wszystkie urządzenia (unikalne UUID)
$devices = $pdo->query("
    SELECT DISTINCT device_uuid, hostname, os, cpu_model, ram_size, device_type 
    FROM devices
")->fetchAll(PDO::FETCH_ASSOC);

// Przygotuj stmt do pobrania ostatnich statystyk
$stats_stmt = $pdo->prepare("SELECT * FROM device_stats WHERE device_uuid=:uuid ORDER BY timestamp DESC LIMIT 1");

// Połącz urządzenia ze statystykami i oblicz statusy
$online_count = $warn_count = $offline_count = 0;

foreach ($devices as $index => $device) {
    $stats_stmt->execute([':uuid' => $device['device_uuid']]);
    $stats = $stats_stmt->fetch();
    $devices[$index]['stats'] = $stats ?: null;

    // domyślnie offline
    $status_class = 'offline';
    $status_text = 'Offline';

    if ($stats) {
        $last_ts = strtotime($stats['timestamp']);
        $minutes_diff = (time() - $last_ts) / 60;

        if ($minutes_diff <= 5 && $stats['internet'] == 1) {
            $status_class = ($stats['cpu_temp'] > 70 || $stats['ram_percent'] > 80 || $stats['disk_percent'] > 80) ? 'warn' : 'online';
            $status_text = ($status_class == 'warn') ? 'Ostrzeżenie' : 'Online';
        }
    }

    // zapis do tablicy
    $devices[$index]['status_class'] = $status_class;
    $devices[$index]['status_text'] = $status_text;

    // Liczenie KPI
    if ($status_class == 'online') $online_count++;
    elseif ($status_class == 'warn') $warn_count++;
    else $offline_count++;
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <title>NetMon — Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="src/dashboard.css" />
  <style>
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 8px 12px; border: 1px solid #ccc; text-align: left; }
    .status.online { color: #00c853; font-weight: bold; }
    .status.warn { color: #ffab00; font-weight: bold; }
    .status.offline { color: #d50000; font-weight: bold; }
    .kpi { display: flex; gap: 10px; margin: 20px 0; }
    .card { flex: 1; padding: 20px; border-radius: 8px; color: #fff; text-align: center; }
    .ok { background: #00c853; }
    .warn { background: #ffab00; }
    .crit { background: #d50000; }
  </style>
</head>
<body>
<div class="layout">

<?php include 'sidebar.php'; ?>

<main class="main">
  <header class="topbar">
    <div class="welcome">Witaj, <strong><?php echo $username; ?></strong></div>
    <div class="actions">
      <a href="logout.php" class="btn">🚪 Wyloguj</a>
    </div>
  </header>

  <!-- KPI Cards -->
  <section class="kpi">
    <div class="card ok">
      <h3>✅ Online</h3>
      <p id="kpi-online"><?php echo $online_count; ?></p>
    </div>
    <div class="card warn">
      <h3>⚠️ Ostrzeżenia</h3>
      <p id="kpi-warn"><?php echo $warn_count; ?></p>
    </div>
    <div class="card crit">
      <h3>🔴 Offline</h3>
      <p id="kpi-offline"><?php echo $offline_count; ?></p>
    </div>
  </section>

  <!-- Devices Table -->
  <section class="devices">
    <h2>Urządzenia końcowe</h2>
    <table>
      <thead>
        <tr>
          <th>Nazwa</th>
          <th>IP</th>
          <th>Status</th>
          <th>Ostatni ping</th>
          <th>System</th>
          <th>CPU</th>
          <th>RAM</th>
          <th>Dysk %</th>
          <th>Typ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($devices as $device): 
            $stats = $device['stats'];
        ?>
        <tr id="device-<?php echo $device['device_uuid']; ?>">
          <td><?php echo htmlspecialchars($device['hostname']); ?></td>
          <td class="ip"><?php echo $stats['ip_address'] ?? '-'; ?></td>
          <td><span class="status <?php echo $device['status_class']; ?>"><?php echo $device['status_text']; ?></span></td>
          <td class="last_ping"><?php echo $stats['timestamp'] ?? '-'; ?></td>
          <td><?php echo htmlspecialchars($device['os']); ?></td>
          <td><?php echo htmlspecialchars($device['cpu_model']); ?></td>
          <td class="ram"><?php echo $stats ? round($stats['ram_used']/1024/1024/1024,2).' GB' : '-'; ?></td>
          <td class="disk"><?php echo $stats ? $stats['disk_percent'].'%' : '-'; ?></td>
          <td><?php echo htmlspecialchars($device['device_type']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- Alerts -->
  <section class="alerts">
    <h2>Ostatnie alerty</h2>
    <ul>
      <?php foreach ($devices as $device):
          $s = $device['stats'];
          if ($s) {
              if ($s['internet']==0) {
                  echo "<li><span class='crit'>[CRIT]</span> {$device['hostname']} jest offline (ostatni ping {$s['timestamp']})</li>";
              }
              if ($s['cpu_temp']>70 || $s['ram_percent']>80 || $s['disk_percent']>80) {
                  echo "<li><span class='warn'>[WARN]</span> {$device['hostname']} wymaga uwagi (CPU {$s['cpu_temp']}°C, RAM {$s['ram_percent']}%, Dysk {$s['disk_percent']}%)</li>";
              }
          }
      endforeach; ?>
    </ul>
  </section>
</main>
</div>

<!-- Automatyczne odświeżanie co 45 sekund -->
<script>
async function fetchDeviceStats() {
    try {
        const res = await fetch('fetch_stats.php');
        const data = await res.json();

        document.getElementById('kpi-online').textContent = data.kpi.online;
        document.getElementById('kpi-warn').textContent = data.kpi.warn;
        document.getElementById('kpi-offline').textContent = data.kpi.offline;

        data.devices.forEach(d => {
            const row = document.getElementById('device-' + d.device_uuid);
            if (!row) return;
            row.querySelector('.ip').textContent = d.ip_address || '-';
            const statusEl = row.querySelector('.status');
            statusEl.textContent = d.status_text;
            statusEl.className = 'status ' + d.status_class;
            row.querySelector('.last_ping').textContent = d.last_ping || '-';
            row.querySelector('.ram').textContent = d.ram_used ? (d.ram_used/1024/1024/1024).toFixed(2)+' GB' : '-';
            row.querySelector('.disk').textContent = d.disk_percent ? d.disk_percent+'%' : '-';
        });

        const alertList = document.querySelector('.alerts ul');
        alertList.innerHTML = '';
        data.alerts.forEach(a => {
            const li = document.createElement('li');
            li.innerHTML = `<span class="${a.class}">[${a.type}]</span> ${a.message}`;
            alertList.appendChild(li);
        });

    } catch(err) {
        console.error('Błąd fetch:', err);
    }
}

fetchDeviceStats();
setInterval(fetchDeviceStats, 45000);
</script>

</body>
</html>
