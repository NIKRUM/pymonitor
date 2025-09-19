<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}
$role = $_SESSION['role'] ?? 'user'; 

include 'config.php';

// pobierz wszystkie urządzenia
$devices = $pdo->query("SELECT DISTINCT device_uuid, hostname, os, cpu_model, ram_size, device_type, created_at FROM devices")->fetchAll(PDO::FETCH_ASSOC);

// przygotuj stmt do pobrania ostatnich statystyk
$stats_stmt = $pdo->prepare("SELECT * FROM device_stats WHERE device_uuid=:uuid ORDER BY timestamp DESC LIMIT 1");

foreach ($devices as $index => $device) {
    $stats_stmt->execute([':uuid'=>$device['device_uuid']]);
    $stats = $stats_stmt->fetch() ?: null;
    $devices[$index]['stats'] = $stats;
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <title>NetMon — Urządzenia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="src/dashboard.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="layout">

  <?php include 'sidebar.php'; ?> <!-- panel po lewej -->

  <main class="main">
    <header class="topbar">
      <div class="welcome">Witaj, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
      <div class="actions">
        <a href="logout.php" class="btn">🚪 Wyloguj</a>
      </div>
    </header>

    <section class="devices">
      <h2>Panel urządzeń — szczegóły</h2>
      <table>
        <thead>
          <tr>
            <th>Nazwa</th>
            <th>UUID</th>
            <th>IP</th>
            <th>Status</th>
            <th>Ostatni ping</th>
            <th>OS</th>
            <th>CPU %</th>
            <th>CPU</th>
            <th>RAM użyte</th>
            <th>RAM całkowite</th>
            <th>Dysk %</th>
            <th>Typ</th>
            <th>Data dodania</th>
            <?php if ($role === 'admin'): ?>
              <th>Akcje</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($devices as $device):
            $stats = $device['stats'];
            $status_class = 'offline';
            $status_text = 'Offline';
            if ($stats) {
                if ($stats['internet']==1) {
                    $status_class = ($stats['cpu_temp']>70 || $stats['ram_percent']>80 || $stats['disk_percent']>80) ? 'warn' : 'online';
                    $status_text = ($status_class=='warn') ? 'Ostrzeżenie' : 'Online';
                }
            }
          ?>
          <tr id="device-<?php echo $device['device_uuid']; ?>">
            <td><?php echo htmlspecialchars($device['hostname']); ?></td>
            <td><?php echo $device['device_uuid']; ?></td>
            <td><?php echo $stats['ip_address'] ?? '-'; ?></td>
            <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
            <td><?php echo $stats['timestamp'] ?? '-'; ?></td>
            <td><?php echo htmlspecialchars($device['os']); ?></td>
            <td><?php echo $stats ? round($stats['cpu_percent']).'%' : '-'; ?></td>
            <td><?php echo htmlspecialchars($device['cpu_model']); ?></td>
            <td><?php echo $stats ? round($stats['ram_used']/1024/1024/1024,2).' GB' : '-'; ?></td>
            <td><?php echo round($device['ram_size']/1024/1024/1024,2).' GB'; ?></td>
            <td><?php echo $stats ? $stats['disk_percent'].'%' : '-'; ?></td>
            <td><?php echo htmlspecialchars($device['device_type']); ?></td>
            <td><?php echo $device['created_at']; ?></td>
            <td class="actions">
              <button class="toggle-chart" data-uuid="<?php echo $device['device_uuid']; ?>">📈 Wykres</button>
              <?php if ($role === 'admin'): ?>
              <form method="post" action="delete_device.php" onsubmit="return confirm('Na pewno chcesz usunąć to urządzenie?');">
                <input type="hidden" name="uuid" value="<?php echo $device['device_uuid']; ?>">
                <button type="submit" name="delete_device">❌ Usuń</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <tr class="chart-row" id="chart-<?php echo $device['device_uuid']; ?>" style="display:none;">
            <td colspan="14">
              <div class="charts-flex">
                <div class="chart-box"><canvas id="canvas-ram-<?php echo $device['device_uuid']; ?>"></canvas></div>
                <div class="chart-box"><canvas id="canvas-cpu-temp-<?php echo $device['device_uuid']; ?>"></canvas></div>
                <div class="chart-box"><canvas id="canvas-cpu-percent-<?php echo $device['device_uuid']; ?>"></canvas></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
</div>

<script>
const charts = {}; // cache

document.querySelectorAll('.toggle-chart').forEach(btn => {
    btn.addEventListener('click', async () => {
        const uuid = btn.dataset.uuid;
        const chartRow = document.getElementById('chart-' + uuid);

        if(chartRow.style.display === 'none') {
            chartRow.style.display = '';

            const res = await fetch('fetch_device_history.php?uuid=' + uuid);
            const data = await res.json();

            const commonOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                elements: { point: { radius: 0 } }
            };

            // RAM
            const ctxRam = document.getElementById('canvas-ram-' + uuid).getContext('2d');
            if(charts['ram-'+uuid]) charts['ram-'+uuid].destroy();
            charts['ram-'+uuid] = new Chart(ctxRam, {
                type: 'line',
                data: { labels: data.timestamps, datasets:[{label:'RAM (GB)', data:data.ram_gb, borderColor:'blue', borderWidth:1}]},
                options: { ...commonOpts, plugins:{...commonOpts.plugins, title:{display:true, text:'RAM'}} }
            });

            // CPU Temp
            const ctxTemp = document.getElementById('canvas-cpu-temp-' + uuid).getContext('2d');
            if(charts['temp-'+uuid]) charts['temp-'+uuid].destroy();
            charts['temp-'+uuid] = new Chart(ctxTemp, {
                type: 'line',
                data: { labels: data.timestamps, datasets:[{label:'CPU Temp (°C)', data:data.cpu_temp, borderColor:'red', borderWidth:1}]},
                options: { ...commonOpts, plugins:{...commonOpts.plugins, title:{display:true, text:'CPU Temp'}} }
            });

            // CPU %
            const ctxPercent = document.getElementById('canvas-cpu-percent-' + uuid).getContext('2d');
            if(charts['percent-'+uuid]) charts['percent-'+uuid].destroy();
            charts['percent-'+uuid] = new Chart(ctxPercent, {
                type: 'line',
                data: { labels: data.timestamps, datasets:[{label:'CPU %', data:data.cpu_percent, borderColor:'green', borderWidth:1}]},
                options: { 
                    ...commonOpts, 
                    plugins:{...commonOpts.plugins, title:{display:true, text:'CPU %'}},
                    scales: { y: { min:0, max:100 } }
                }
            });

        } else {
            chartRow.style.display = 'none';
        }
    });
});

async function updateStatusesAndKPI(){
    const res = await fetch('fetch_device_status.php');
    const data = await res.json();

    for(const uuid in data.devices){
        const statusEl = document.querySelector(`#device-${uuid} .status`);
        if(statusEl){
            statusEl.textContent = data.devices[uuid].status_text;
            statusEl.className = 'status ' + data.devices[uuid].status_class;
        }
    }

    document.getElementById('kpi-online').textContent = data.kpi.online;
    document.getElementById('kpi-warn').textContent = data.kpi.warn;
    document.getElementById('kpi-offline').textContent = data.kpi.offline;
}

setInterval(updateStatusesAndKPI, 45000);
updateStatusesAndKPI();
</script>

</body>
</html>
