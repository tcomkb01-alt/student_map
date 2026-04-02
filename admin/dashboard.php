<?php
/**
 * dashboard.php – Admin Dashboard
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$pdo = getDB();

// ── Stats ────────────────────────────────────────────────────────────────────
$total      = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$withCoords = (int)$pdo->query('SELECT COUNT(*) FROM students WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchColumn();
$noCoords   = $total - $withCoords;
$classes    = (int)$pdo->query('SELECT COUNT(DISTINCT class) FROM students WHERE class != ""')->fetchColumn();

// ── All students for map ──────────────────────────────────────────────────────
$students = $pdo->query('SELECT * FROM students ORDER BY class, student_number')->fetchAll();

// ── Class list for filter ─────────────────────────────────────────────────────
$classList = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'แดชบอร์ด';
$activePage = 'dashboard';
require_once __DIR__ . '/../components/header.php';
?>

<div class="layout">
  <?php require_once __DIR__ . '/../components/navbar.php'; ?>

  <div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
      <div class="flex items-center gap-3">
        <button id="sidebar-toggle" class="btn btn-light btn-icon" style="display:none;" aria-label="Toggle menu">
          ☰
        </button>
        <div>
          <h1 style="font-size:1.1rem;font-weight:700;">แดชบอร์ด</h1>
          <p style="font-size:.78rem;color:var(--text-muted);">ภาพรวมระบบแผนที่เยี่ยมบ้าน</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/apps" class="btn btn-warning btn-sm">
          🧩 App Center
        </a>
        <a href="<?= BASE_URL ?>/admin/students?action=add" class="btn btn-primary btn-sm">
          ➕ เพิ่มนักเรียน
        </a>
        <a href="<?= BASE_URL ?>/admin/students" class="btn btn-light btn-sm">
          👨‍🎓 จัดการนักเรียน
        </a>
      </div>
    </div>

    <div class="page-content">

      <!-- Stat Cards -->
      <div class="grid-cols-4 mb-6">
        <div class="stat-card">
          <div class="stat-icon purple">👨‍🎓</div>
          <div>
            <div class="stat-label">นักเรียนทั้งหมด</div>
            <div class="stat-value"><?= number_format($total) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">📍</div>
          <div>
            <div class="stat-label">มีพิกัดแล้ว</div>
            <div class="stat-value"><?= number_format($withCoords) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">⚠️</div>
          <div>
            <div class="stat-label">ยังไม่มีพิกัด</div>
            <div class="stat-value"><?= number_format($noCoords) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">🏫</div>
          <div>
            <div class="stat-label">จำนวนห้องเรียน</div>
            <div class="stat-value"><?= number_format($classes) ?></div>
          </div>
        </div>
      </div>

      <!-- Map Card -->
      <div class="card">
        <div class="card-header">
          <div class="flex items-center gap-3" style="flex-wrap:wrap;gap:.75rem;">
            <span style="font-weight:700;font-size:1rem;">🗺️ แผนที่นักเรียนทั้งหมด</span>

            <!-- Class filter -->
            <select id="classFilter" class="form-control form-select" style="width:auto;min-width:140px;">
              <option value="">— ทุกห้องเรียน —</option>
              <?php foreach ($classList as $cls): ?>
                <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></option>
              <?php endforeach; ?>
            </select>

            <!-- Search -->
            <div class="search-box">
              <span class="search-icon">🔍</span>
              <input id="mapSearch" type="text" class="form-control" placeholder="ค้นหาชื่อ / เลขประจำตัว..." style="min-width:220px;">
            </div>
          </div>

          <!-- Satellite toggle -->
          <button id="toggleSatellite" class="btn btn-light btn-sm">
            🛰️ ดาวเทียม
          </button>
        </div>
        <div id="dashMap" style="height:520px;"></div>
      </div>

    </div><!--/ page-content -->
  </div><!--/ main-content -->
</div>

<!-- Leaflet + MarkerCluster -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="<?= BASE_URL ?>/assets/js/map-markers.js"></script>

<script>
'use strict';

// ── Student data (injected from PHP) ─────────────────────────────────────────
const ALL_STUDENTS = <?= json_encode($students, JSON_UNESCAPED_UNICODE) ?>;

// ── Map setup ─────────────────────────────────────────────────────────────────
const streetLayer = L.tileLayer(
  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  { attribution: '© OpenStreetMap contributors', maxZoom: 19 }
);
const satelliteLayer = L.tileLayer(
  'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
  { attribution: '© Esri — World Imagery', maxZoom: 19 }
);

const map = L.map('dashMap', {
  center: [13.7563, 100.5018],
  zoom: 11,
  layers: [streetLayer],
});

let satellite = false;
document.getElementById('toggleSatellite').addEventListener('click', function () {
  satellite = !satellite;
  if (satellite) {
    map.removeLayer(streetLayer);
    map.addLayer(satelliteLayer);
    this.textContent = '🗺️ แผนที่มาตรฐาน';
  } else {
    map.removeLayer(satelliteLayer);
    map.addLayer(streetLayer);
    this.textContent = '🛰️ ดาวเทียม';
  }
});

// ── Markers ───────────────────────────────────────────────────────────────────
const cluster = L.markerClusterGroup({ maxClusterRadius: 40, showCoverageOnHover: false });
const markerMap = {}; // student id → marker

function plotStudents(list) {
  cluster.clearLayers();
  const bounds = [];

  list.forEach(s => {
    if (!s.latitude || !s.longitude) return;
    const lat = parseFloat(s.latitude);
    const lng = parseFloat(s.longitude);
    const marker = L.marker([lat, lng], { icon: buildMarkerIcon(s) });
    marker.bindPopup(buildPopupHTML(s), { maxWidth: 280, minWidth: 260 });
    cluster.addLayer(marker);
    markerMap[s.id] = marker;
    bounds.push([lat, lng]);
  });

  map.addLayer(cluster);
  if (bounds.length) map.fitBounds(bounds, { padding: [40, 40] });
}

plotStudents(ALL_STUDENTS);

// ── Filter logic ──────────────────────────────────────────────────────────────
function applyFilters() {
  const cls = document.getElementById('classFilter').value.trim();
  const q   = document.getElementById('mapSearch').value.trim().toLowerCase();

  const filtered = ALL_STUDENTS.filter(s => {
    const matchClass = !cls || s.class === cls;
    const name = `${s.prefix||''} ${s.first_name||''} ${s.last_name||''}`.toLowerCase();
    const matchSearch = !q || name.includes(q) || (s.student_id||'').toLowerCase().includes(q);
    return matchClass && matchSearch;
  });

  plotStudents(filtered);

  // If single match with coords → open popup
  if (filtered.length === 1 && filtered[0].latitude) {
    const m = markerMap[filtered[0].id];
    if (m) { setTimeout(() => { m.openPopup(); }, 400); }
  }
}

document.getElementById('classFilter').addEventListener('change', applyFilters);
document.getElementById('mapSearch').addEventListener('input', applyFilters);

// Sidebar toggle show on mobile
if (window.innerWidth <= 768) {
  document.getElementById('sidebar-toggle').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
