<?php
$role = $role ?? 'user'; // jeśli nie ustawiono wcześniej
$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>
<!-- Sidebar -->
<aside class="sidebar">
  <div class="logo">🔍 NetMon</div>
  <nav>
    <a class="<?= ($current_page==='dashboard') ? 'active' : '' ?>" href="dashboard.php">📊 Dashboard</a>
    <a class="<?= ($current_page==='devices') ? 'active' : '' ?>" href="devices.php">💻 Urządzenia</a>
    <a class="<?= ($current_page==='network') ? 'active' : '' ?>" href="network.php">🌐 Sieć</a>
    <a class="<?= ($current_page==='alerts') ? 'active' : '' ?>" href="alert.php">⚠️ Alerty</a>
    <?php if ($role === 'admin'): ?>
      <a class="<?= ($current_page==='users') ? 'active' : '' ?>" href="users.php">👤 Użytkownicy</a>
    <?php endif; ?>
  </nav>
</aside>
<script>
function checkDevices() {
  fetch('check_devices.php')
    .then(r => r.json())
    .then(data => console.log("Check:", data))
    .catch(err => console.error("Error:", err));
}
setInterval(checkDevices, 30000);
</script>
