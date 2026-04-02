<?php
/**
 * public/map.php – Public Interactive Map
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// ─── Password Protection ────────────────────────────────────────────────────
$correctPassword = MAP_PASSWORD;
$error = '';

// Check if password form was submitted
if (isset($_POST['map_password'])) {
    if ($_POST['map_password'] === $correctPassword) {
        $_SESSION['map_authorized'] = true;
    } else {
        $error = 'รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    }
}

// If not authorized, show login form
if (!isset($_SESSION['map_authorized']) || $_SESSION['map_authorized'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>เข้าสู่ระบบแผนที่ · <?= APP_NAME ?></title>
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
      <style>
        body {
          background: #f8fafc;
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100vh;
          margin: 0;
          font-family: 'Sarabun', sans-serif;
        }
        .login-card {
          background: #fff;
          padding: 2.5rem;
          border-radius: 1.5rem;
          box-shadow: 0 10px 25px rgba(0,0,0,0.05);
          width: 90%;
          max-width: 400px;
          text-align: center;
          animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .icon-circle {
          width: 64px;
          height: 64px;
          background: linear-gradient(135deg, #4f46e5, #06b6d4);
          color: #fff;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto 1.5rem;
          font-size: 1.5rem;
          box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
        }
        h1 { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem; }
        p { color: #64748b; font-size: 0.9rem; margin-bottom: 2rem; }
        input[type="password"] {
          width: 100%;
          padding: 0.85rem 1.1rem;
          border: 1.5px solid #e2e8f0;
          border-radius: 0.85rem;
          font-size: 1rem;
          margin-bottom: 1rem;
          outline: none;
          box-sizing: border-box;
          transition: border-color .2s, box-shadow .2s;
        }
        input[type="password"]:focus { border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        button {
          width: 100%;
          padding: 0.85rem;
          background: #4f46e5;
          color: #fff;
          border: none;
          border-radius: 0.85rem;
          font-size: 1rem;
          font-weight: 700;
          cursor: pointer;
          transition: all 0.2s;
          box-shadow: 0 4px 6px rgba(79, 70, 229, 0.1);
        }
        button:hover { background: #4338ca; transform: translateY(-1px); box-shadow: 0 6px 12px rgba(79, 70, 229, 0.15); }
        .error-msg {
          color: #ef4444;
          font-size: 0.85rem;
          margin-bottom: 1.5rem;
          background: #fee2e2;
          padding: 0.75rem;
          border-radius: 0.75rem;
          border: 1px solid rgba(239, 68, 68, 0.1);
        }
        .footer-link { margin-top: 1.5rem; }
        .footer-link a { color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: color .2s; }
        .footer-link a:hover { color: #4f46e5; }
      </style>
    </head>
    <body>
      <div class="login-card">
        <div class="icon-circle">🔐</div>
        <h1>ต้องการรหัสผ่าน</h1>
        <p>กรุณากรอกรหัสผ่านเพื่อเข้าใช้งานหน้าแผนที่</p>
        
        <?php if ($error): ?>
          <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
          <input type="password" name="map_password" placeholder="รหัสผ่านเข้าใช้งาน" required autofocus>
          <button type="submit">ตกลง</button>
        </form>
        
        <div class="footer-link">
            <a href="<?= BASE_URL ?>/public/">← กลับหน้าหลัก</a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo       = getDB();
$classList = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")
                 ->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แผนที่บ้านนักเรียน · <?= APP_NAME ?></title>
  <meta name="description" content="แผนที่แสดงที่อยู่บ้านนักเรียน ค้นหาและนำทางได้ทันที">

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <script>const BASE_URL = '<?= BASE_URL ?>';</script>

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; overflow: hidden; }

    /* ── Layout ────────────────────────────────── */
    #app {
      display: flex; height: 100vh;
      font-family: 'Sarabun', sans-serif;
    }

    /* ── Side Panel ────────────────────────────── */
    #sidePanel {
      width: 340px; flex-shrink: 0;
      background: #fff;
      display: flex; flex-direction: column;
      border-right: 1px solid #e2e8f0;
      z-index: 50; position: relative;
      transition: transform .3s;
    }

    .panel-header {
      padding: 1.1rem 1.25rem;
      border-bottom: 1px solid #e2e8f0;
      background: linear-gradient(135deg, #4f46e5, #06b6d4);
      color: #fff;
    }
    .panel-title {
      font-size: 1rem; font-weight: 800;
      display: flex; align-items: center; gap: .5rem;
      margin-bottom: .75rem;
    }

    .panel-controls { padding: .85rem 1.1rem; border-bottom: 1px solid #f1f5f9; }

    .search-wrap {
      position: relative; margin-bottom: .65rem;
    }
    .search-wrap input {
      width: 100%; padding: .6rem .9rem .6rem 2.4rem;
      border: 1.5px solid #e2e8f0; border-radius: .85rem;
      font-size: .88rem; font-family: inherit;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
    }
    .search-wrap input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
    .search-icon { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); font-size: .9rem; pointer-events: none; }

    .class-chips {
      display: flex; flex-wrap: wrap; gap: .4rem;
    }
    .chip {
      padding: .3rem .75rem; border-radius: 99px;
      font-size: .75rem; font-weight: 700; cursor: pointer;
      border: 1.5px solid #e2e8f0; color: #64748b;
      transition: all .2s; white-space: nowrap;
    }
    .chip:hover { border-color: #4f46e5; color: #4f46e5; }
    .chip.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }

    /* Student list */
    #studentList {
      flex: 1; overflow-y: auto;
      padding: .5rem .75rem;
    }
    .student-item {
      display: flex; align-items: center; gap: .75rem;
      padding: .7rem .85rem;
      border-radius: 1rem; cursor: pointer;
      transition: background .18s;
      margin-bottom: .2rem;
    }
    .student-item:hover { background: #f1f5f9; }
    .student-item.highlighted { background: #ede9fe; }
    .s-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      object-fit: cover;
      border: 2px solid #e2e8f0; flex-shrink: 0;
    }
    .s-avatar-placeholder {
      width: 44px; height: 44px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg,#4f46e5,#06b6d4);
      color: #fff; font-weight: 800; font-size: .95rem;
      flex-shrink: 0;
    }
    .s-name { font-weight: 700; font-size: .88rem; margin-bottom: .1rem; }
    .s-meta { font-size: .75rem; color: #64748b; }
    .s-coords {
      margin-left: auto; flex-shrink: 0;
      font-size: .7rem;
    }

    .list-empty {
      text-align: center; padding: 3rem 1rem; color: #94a3b8;
    }
    .list-count {
      padding: .5rem 1rem .25rem;
      font-size: .75rem; color: #94a3b8; font-weight: 600;
    }

    /* ── Map ───────────────────────────────────── */
    #mapWrap { flex: 1; position: relative; }
    #publicMap { width: 100%; height: 100%; }

    /* Map toolbar */
    .map-toolbar {
      position: absolute; top: 1rem; right: 1rem;
      z-index: 1000;
      display: flex; flex-direction: column; gap: .5rem;
    }
    .map-btn {
      background: #fff; border: none; cursor: pointer;
      padding: .6rem 1rem; border-radius: .85rem;
      font-size: .82rem; font-weight: 700;
      box-shadow: 0 2px 12px rgba(0,0,0,.15);
      transition: all .2s; white-space: nowrap;
      display: flex; align-items: center; gap: .4rem;
      color: #1e293b;
    }
    .map-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(0,0,0,.18); }
    .map-btn.active { background: #4f46e5; color: #fff; }

    /* Panel toggle button (mobile) */
    #panelToggle {
      position: absolute; left: 340px; top: 50%;
      transform: translateY(-50%); z-index: 200;
      background: #fff; border: 1px solid #e2e8f0;
      border-left: none;
      padding: .65rem .5rem;
      border-radius: 0 .75rem .75rem 0;
      cursor: pointer; font-size: .8rem;
      box-shadow: 2px 0 8px rgba(0,0,0,.08);
      transition: left .3s;
      display: none;
    }

    /* Back to home */
    .back-home {
      position: absolute; bottom: 1.25rem; left: 1.25rem;
      z-index: 1000;
    }
    .back-home a {
      background: #fff;
      padding: .55rem 1.1rem;
      border-radius: .85rem;
      font-size: .82rem; font-weight: 700;
      box-shadow: 0 2px 12px rgba(0,0,0,.15);
      color: #1e293b; display: flex; align-items: center; gap: .4rem;
      transition: all .2s;
      text-decoration: none;
    }
    .back-home a:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(0,0,0,.18); }

    /* Responsive */
    @media(max-width:768px) {
      #sidePanel {
        position: absolute; top: 0; left: 0; height: 100%;
        z-index: 200;
        transform: translateX(-100%);
      }
      #sidePanel.open { transform: none; }
      #panelToggle { display: flex; align-items: center; }
      #panelToggle.panel-open { left: 340px; }
      #panelToggle.panel-closed { left: 0; border-left: 1px solid #e2e8f0; border-radius: 0 .75rem .75rem 0; }
    }
  </style>
</head>
<body>

<div id="app">

  <!-- ═══════ SIDE PANEL ═══════ -->
  <div id="sidePanel">
    <!-- Header -->
    <div class="panel-header">
      <div class="panel-title">🗺️ แผนที่บ้านนักเรียน</div>

      <!-- Search -->
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input id="searchInput" type="text" placeholder="ค้นหาชื่อ / นามสกุล / เลขประจำตัว...">
      </div>
    </div>

    <!-- Class chips -->
    <div class="panel-controls">
      <div class="class-chips" id="classChips">
        <div class="chip active" data-class="">ทั้งหมด</div>
        <?php foreach ($classList as $cls): ?>
          <div class="chip" data-class="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Student count -->
    <div class="list-count" id="listCount">กำลังโหลด...</div>

    <!-- Student list -->
    <div id="studentList">
      <div class="list-empty">
        <div style="font-size:2.5rem;margin-bottom:.5rem;">🔄</div>
        กำลังโหลดข้อมูล...
      </div>
    </div>
  </div>

  <!-- Mobile panel toggle -->
  <button id="panelToggle" aria-label="Toggle panel">◀</button>

  <!-- ═══════ MAP ═══════ -->
  <div id="mapWrap">
    <div id="publicMap"></div>

    <!-- Toolbar: top-right -->
    <div class="map-toolbar">
      <button class="map-btn" id="btnSatellite" onclick="toggleSatellite()">
        🛰️ ดาวเทียม
      </button>
      <button class="map-btn" onclick="fitAll()">
        🔭 ดูทั้งหมด
      </button>
    </div>

    <!-- Back home -->
    <div class="back-home">
      <a href="<?= BASE_URL ?>/public/">← หน้าหลัก</a>
    </div>
  </div>

</div><!-- #app -->

<!-- Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="<?= BASE_URL ?>/assets/js/map-markers.js"></script>

<script>
'use strict';

/* ── Map setup ─────────────────────────────────────────────── */
const streetLayer = L.tileLayer(
  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  { attribution:'© OpenStreetMap contributors', maxZoom:19 }
);
const satelliteLayer = L.tileLayer(
  'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
  { attribution:'© Esri World Imagery', maxZoom:19 }
);

const map = L.map('publicMap', {
  center: [13.7563, 100.5018], zoom: 11,
  layers: [streetLayer],
  zoomControl: true,
});

// Move zoom control to bottom-right
map.zoomControl.setPosition('bottomright');

let satelliteOn = false;
function toggleSatellite() {
  satelliteOn = !satelliteOn;
  const btn = document.getElementById('btnSatellite');
  if (satelliteOn) {
    map.removeLayer(streetLayer); map.addLayer(satelliteLayer);
    btn.textContent = '🗺️ แผนที่ปกติ'; btn.classList.add('active');
  } else {
    map.removeLayer(satelliteLayer); map.addLayer(streetLayer);
    btn.textContent = '🛰️ ดาวเทียม'; btn.classList.remove('active');
  }
}

/* ── Data & state ─────────────────────────────────────────── */
let allStudents  = [];
let filteredList = [];
let markerMap    = {};  // id → { marker, student }
let cluster      = null;
let selectedId   = null;

/* ── Fetch all students ────────────────────────────────────── */
async function fetchStudents(classFilter='', search='') {
  const url = `${BASE_URL}/public/api?action=students`
    + (classFilter ? `&class=${encodeURIComponent(classFilter)}` : '')
    + (search      ? `&search=${encodeURIComponent(search)}` : '');

  const res  = await fetch(url);
  const json = await res.json();
  return json.data || [];
}

/* ── Render markers ────────────────────────────────────────── */
function renderMarkers(students) {
  // Clear
  if (cluster) { cluster.clearLayers(); map.removeLayer(cluster); }
  cluster  = L.markerClusterGroup({ maxClusterRadius: 50, showCoverageOnHover: false, zoomToBoundsOnClick: true });
  markerMap = {};

  const bounds = [];
  students.forEach(s => {
    if (!s.latitude || !s.longitude) return;
    const lat = parseFloat(s.latitude);
    const lng = parseFloat(s.longitude);

    const marker = L.marker([lat, lng], { icon: buildMarkerIcon(s) });
    marker.bindPopup(buildPopupHTML(s), { maxWidth:280, minWidth:260 });
    marker.on('click', () => highlightStudent(s.id));

    cluster.addLayer(marker);
    markerMap[s.id] = { marker, student: s };
    bounds.push([lat, lng]);
  });

  cluster.addTo(map);
  return bounds;
}

/* ── Render student list ────────────────────────────────────── */
function renderList(students) {
  const container = document.getElementById('studentList');
  document.getElementById('listCount').textContent =
    `${students.length} คน${students.some(s=>!s.latitude) ? ' (บางคนยังไม่มีพิกัด)' : ''}`;

  if (!students.length) {
    container.innerHTML = '<div class="list-empty"><div style="font-size:2rem">😢</div>ไม่พบข้อมูลนักเรียน</div>';
    return;
  }

  container.innerHTML = students.map(s => {
    const hasCoord = s.latitude && s.longitude;
    const imgHtml  = s.profile_image
      ? `<img class="s-avatar" src="${BASE_URL}/uploads/students/${s.profile_image}" onerror="this.style.display='none';this.nextSibling.style.display='flex'">`
      : '';
    const placeholder = `<div class="s-avatar-placeholder" ${s.profile_image?'style="display:none"':''}>${(s.first_name||'?')[0]}</div>`;

    return `
      <div class="student-item" id="si_${s.id}" onclick="focusStudent(${s.id})">
        ${imgHtml}${placeholder}
        <div style="min-width:0;flex:1;">
          <div class="s-name truncate">${s.prefix||''} ${s.first_name||''} ${s.last_name||''}</div>
          <div class="s-meta">ชั้น ${s.class||'–'} · เลขที่ ${s.student_number||'–'}</div>
          <div class="s-meta" style="color:${s.parent_phone?'#64748b':'#d1d5db'}">${s.parent_phone||'ไม่มีเบอร์'}</div>
        </div>
        <div class="s-coords">${hasCoord ? '📍' : '⬜'}</div>
      </div>`;
  }).join('');
}

/* ── Focus & highlight ──────────────────────────────────────── */
function highlightStudent(id) {
  if (selectedId) {
    const prev = document.getElementById(`si_${selectedId}`);
    if (prev) prev.classList.remove('highlighted');
    const prevData = markerMap[selectedId];
    if (prevData) prevData.marker.setIcon(buildMarkerIcon(prevData.student, false));
  }

  selectedId = id;
  const item = document.getElementById(`si_${id}`);
  if (item) {
    item.classList.add('highlighted');
    item.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }
  const data = markerMap[id];
  if (data) data.marker.setIcon(buildMarkerIcon(data.student, true));
}

function focusStudent(id) {
  highlightStudent(id);
  const data = markerMap[id];
  if (!data) return;
  const s = data.student;
  if (s.latitude && s.longitude) {
    map.flyTo([parseFloat(s.latitude), parseFloat(s.longitude)], 17, { duration:.8 });
    setTimeout(() => data.marker.openPopup(), 900);
  } else {
    showToast('นักเรียนคนนี้ยังไม่มีพิกัดบ้าน', 'warning');
  }
}

function fitAll() {
  const bounds = Object.values(markerMap).map(d => [
    parseFloat(d.student.latitude), parseFloat(d.student.longitude)
  ]);
  if (bounds.length) map.fitBounds(bounds, { padding:[40,40] });
}

/* ── Filters ────────────────────────────────────────────────── */
let filterClass  = '';
let filterSearch = '';
let debounceTimer;

async function applyFilters() {
  const students = await fetchStudents(filterClass, filterSearch);
  allStudents    = students;
  filteredList   = students;
  const bounds   = renderMarkers(students);
  renderList(students);
  if (bounds.length && !filterSearch) map.fitBounds(bounds, { padding:[40,40] });
}

// Class chips
document.getElementById('classChips').addEventListener('click', e => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  filterClass = chip.dataset.class;
  applyFilters();
});

// Search
document.getElementById('searchInput').addEventListener('input', e => {
  clearTimeout(debounceTimer);
  filterSearch = e.target.value.trim();
  debounceTimer = setTimeout(applyFilters, 350);
});

/* ── Mobile panel toggle ────────────────────────────────────── */
const panel      = document.getElementById('sidePanel');
const panelBtn   = document.getElementById('panelToggle');
let panelVisible = false;

panelBtn.addEventListener('click', () => {
  panelVisible = !panelVisible;
  panel.classList.toggle('open', panelVisible);
  panelBtn.textContent = panelVisible ? '◀' : '▶';
  panelBtn.className   = `panel-${panelVisible ? 'open' : 'closed'}`;
  setTimeout(() => map.invalidateSize(), 310);
});

/* ── Toast (inline) ─────────────────────────────────────────── */
function showToast(msg, type='info') {
  const icons = {success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
  const div = document.createElement('div');
  div.className = `toast ${type}`;
  div.innerHTML = `<span>${icons[type]||'💬'}</span><span style="flex:1;font-size:.85rem;">${msg}</span>`;
  div.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:#fff;border-radius:1rem;padding:.85rem 1.25rem;display:flex;align-items:center;gap:.75rem;box-shadow:0 8px 32px rgba(0,0,0,.15);animation:slideInRight .3s ease;border-left:4px solid';
  div.style.borderLeftColor = type==='success'?'#10b981':type==='error'?'#ef4444':'#f59e0b';
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 3000);
}

/* ── Init ───────────────────────────────────────────────────── */
applyFilters();
</script>

<style>
  @keyframes slideInRight { from{transform:translateX(100%);opacity:0} to{transform:none;opacity:1} }
</style>
</body>
</html>
