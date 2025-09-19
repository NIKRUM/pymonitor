<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}
$role = $_SESSION['role'] ?? 'user';

// konfiguracja bazy
include 'config.php';


// pobierz wszystkie urządzenia sieciowe
$ext_devices = $pdo->query("SELECT * FROM ext_devices")->fetchAll();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <title>NetMon — Sieć</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="src/dashboard.css" />
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
  <h2>Urządzenia sieciowe</h2>
  <table>
    <thead>
      <tr>
        <th>Typ</th>
        <th>Host</th>
        <th>IP</th>
        <th>Status</th>
        <th>Ostatni ping</th>
        <th>Ping (ms)</th>
        <?php if ($role === 'admin'): ?>
        <th>Akcje</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody id="ext-table-body">
      <?php foreach ($ext_devices as $dev): ?>
      <tr id="ext-<?php echo $dev['device_uuid']; ?>">
        <td><?php echo htmlspecialchars($dev['device_type']); ?></td>
        <td><?php echo htmlspecialchars($dev['hostname']); ?></td>
        <td><?php echo htmlspecialchars($dev['ip_address']); ?></td>
        <td><span class="status">Offline</span></td>
        <td>-</td>
        <td>-</td>
        <?php if ($role === 'admin'): ?>
        <td>
          <form method="POST" action="delete_ext_device.php" onsubmit="return confirm('Na pewno chcesz usunąć to urządzenie?');">
            <input type="hidden" name="uuid" value="<?php echo $dev['device_uuid']; ?>">
            <button type="submit" class="btn btn-danger">🗑 Usuń</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

  </main>
</div>

<script>
async function updateNetworkStatuses(){
    const res = await fetch('fetch_ext_status.php');
    const data = await res.json();

    for(const uuid in data){
        const row = document.getElementById('ext-' + uuid);
        if(!row) continue;
        const statusEl = row.querySelector('.status');
        statusEl.textContent = data[uuid].status_text;
        statusEl.className = 'status ' + data[uuid].status_class;

        row.cells[4].textContent = data[uuid].last_ping ?? '-';
        row.cells[5].textContent = data[uuid].ping_ms !== null ? data[uuid].ping_ms : '-';
    }
}

// automatyczne odświeżanie co 45 sekund
setInterval(updateNetworkStatuses, 45000);
updateNetworkStatuses(); // od razu przy ładowaniu
</script>

</body>
</html>
