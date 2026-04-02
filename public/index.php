<?php
/**
 * public/index.php – Public Landing Page
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo        = getDB();
$total      = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$withCoords = (int)$pdo->query('SELECT COUNT(*) FROM students WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchColumn();
$classes    = (int)$pdo->query("SELECT COUNT(DISTINCT class) FROM students WHERE class != ''")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?> · ระบบแผนที่เยี่ยมบ้านนักเรียน</title>
  <meta name="description" content="ระบบแผนที่แสดงที่อยู่บ้านนักเรียน ค้นหานักเรียน และนำทางไปยังบ้านนักเรียนได้อย่างสะดวก">

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    /* ── Hero section ── */
    .hero {
      min-height: 100vh;
      background: linear-gradient(160deg, #4f46e5 0%, #7c3aed 40%, #06b6d4 100%);
      position: relative;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 50% at 20% 60%, rgba(255,255,255,.08) 0%, transparent 70%),
        radial-gradient(ellipse 40% 40% at 80% 30%, rgba(6,182,212,.25) 0%, transparent 70%);
    }
    /* Floating blobs */
    .blob {
      position: absolute; border-radius: 50%;
      filter: blur(70px); opacity: .18; pointer-events: none;
    }
    .blob-1 { width:500px;height:500px;background:#818cf8;top:-150px;right:-120px;animation:float1 8s ease-in-out infinite; }
    .blob-2 { width:350px;height:350px;background:#34d399;bottom:-100px;left:-80px;animation:float2 10s ease-in-out infinite; }
    .blob-3 { width:250px;height:250px;background:#f472b6;top:40%;left:55%;animation:float3 7s ease-in-out infinite; }
    @keyframes float1 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-30px,40px) scale(1.05)} }
    @keyframes float2 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(40px,-20px) scale(1.08)} }
    @keyframes float3 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-20px,30px) scale(.95)} }

    /* Nav */
    .pub-nav {
      position: relative; z-index: 10;
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.25rem 2.5rem;
    }
    .pub-nav .brand {
      display: flex; align-items: center; gap: .75rem;
      color: #fff; font-weight: 800; font-size: 1.05rem;
    }
    .pub-nav .brand .icon {
      width: 40px; height: 40px; border-radius: .75rem;
      background: rgba(255,255,255,.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; backdrop-filter: blur(4px);
    }

    /* Hero content */
    .hero-content {
      flex: 1;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      text-align: center;
      padding: 3rem 1.5rem;
      position: relative; z-index: 5;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: .5rem;
      background: rgba(255,255,255,.15);
      backdrop-filter: blur(6px);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 99px;
      padding: .45rem 1.1rem;
      font-size: .82rem; font-weight: 600; color: #fff;
      margin-bottom: 1.75rem;
      animation: fadeDown .6s ease;
    }
    .hero-title {
      font-size: clamp(2rem, 6vw, 3.75rem);
      font-weight: 900; color: #fff;
      line-height: 1.15;
      margin-bottom: 1.25rem;
      animation: fadeUp .7s ease;
    }
    .hero-title span { color: #fde68a; }
    .hero-subtitle {
      font-size: clamp(.95rem, 2vw, 1.2rem);
      color: rgba(255,255,255,.8);
      max-width: 560px; line-height: 1.7;
      margin-bottom: 2.5rem;
      animation: fadeUp .8s ease;
    }
    .hero-buttons {
      display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;
      animation: fadeUp .9s ease;
    }
    .btn-hero-primary {
      display: inline-flex; align-items: center; gap: .6rem;
      padding: .95rem 2.25rem;
      background: #fff;
      color: #4f46e5;
      border-radius: 99px;
      font-weight: 800; font-size: 1rem;
      box-shadow: 0 8px 32px rgba(0,0,0,.2);
      transition: all .25s;
      border: none; cursor: pointer;
      text-decoration: none;
      position: relative; overflow: hidden;
    }
    .btn-hero-primary::after {
      content:''; position:absolute; inset:0;
      background:linear-gradient(120deg,transparent 20%,rgba(79,70,229,.08) 50%,transparent 80%);
      transform:translateX(-100%); transition:transform .45s ease;
    }
    .btn-hero-primary:hover { transform:translateY(-3px); box-shadow:0 16px 48px rgba(0,0,0,.3); }
    .btn-hero-primary:hover::after { transform:translateX(100%); }

    .btn-hero-outline {
      display: inline-flex; align-items: center; gap: .6rem;
      padding: .95rem 2.25rem;
      background: rgba(255,255,255,.12);
      color: #fff;
      border-radius: 99px;
      font-weight: 700; font-size: 1rem;
      border: 1.5px solid rgba(255,255,255,.35);
      backdrop-filter: blur(4px);
      transition: all .25s;
      text-decoration: none;
    }
    .btn-hero-outline:hover { background:rgba(255,255,255,.2); transform:translateY(-3px); }

    /* Scroll indicator */
    .scroll-hint {
      position: relative; z-index: 5;
      text-align: center; padding-bottom: 2rem;
      color: rgba(255,255,255,.5); font-size: .78rem;
      animation: bounce 2s infinite;
    }
    @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(8px)} }

    @keyframes fadeDown { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:none} }
    @keyframes fadeUp   { from{opacity:0;transform:translateY(16px)}  to{opacity:1;transform:none} }

    /* Stats section */
    .stats-section {
      background: #fff;
      padding: 4rem 1.5rem;
    }
    .stat-number {
      font-size: 3rem; font-weight: 900;
      background: linear-gradient(135deg, #4f46e5, #06b6d4);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* Features */
    .features-section { background: #f8fafc; padding: 4rem 1.5rem; }
    .feature-card {
      background: #fff; border-radius: 1.5rem;
      padding: 2rem 1.75rem;
      box-shadow: 0 4px 20px rgba(0,0,0,.06);
      border: 1px solid #e2e8f0;
      transition: all .25s;
    }
    .feature-card:hover { transform: translateY(-6px); box-shadow: 0 12px 40px rgba(0,0,0,.1); }
    .feature-icon {
      width: 60px; height: 60px; border-radius: 1rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; margin-bottom: 1.25rem;
    }

    /* CTA */
    .cta-section {
      background: linear-gradient(135deg, #4f46e5, #06b6d4);
      padding: 5rem 1.5rem;
      text-align: center;
      position: relative; overflow: hidden;
    }
    .cta-section::before {
      content:''; position:absolute; inset:0;
      background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(255,255,255,.06) 0%,transparent 70%);
    }

    /* Footer */
    .pub-footer {
      background: #1e293b; color: #94a3b8;
      text-align: center; padding: 2rem 1.5rem;
      font-size: .82rem;
    }

    /* Map preview card */
    .map-preview-card {
      border-radius: 2rem; overflow: hidden;
      box-shadow: 0 24px 80px rgba(79,70,229,.25);
      border: 4px solid rgba(255,255,255,.2);
      margin: 2.5rem auto 0;
      max-width: 680px; width: 100%;
      animation: fadeUp 1s ease;
    }
    #previewMap { height: 320px; }

    /* Responsive nav */
    @media(max-width:600px) {
      .pub-nav { padding: 1rem 1.25rem; }
      .pub-nav .nav-links { display: none; }
    }
  </style>
</head>
<body style="background:#fff;">

<!-- ═══════════════════════════ HERO ═══════════════════════════ -->
<section class="hero">
  <!-- Blobs -->
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <!-- Nav -->
  <nav class="pub-nav">
    <div class="brand">
      <div class="icon">🏫</div>
      <span><?= APP_NAME ?></span>
    </div>
    <div class="nav-links flex items-center gap-3">
      <a href="<?= BASE_URL ?>/public/map"
         style="color:rgba(255,255,255,.8);font-size:.9rem;font-weight:600;transition:color .2s;"
         onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.8)'">
        🗺️ แผนที่
      </a>
      <a href="<?= BASE_URL ?>/public/parent_location"
         style="padding:.45rem 1rem;background:rgba(255,255,255,.15);color:#fff;border-radius:.75rem;font-size:.85rem;font-weight:700;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(4px);transition:all .2s;"
         onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
        👨‍👩‍👧 ผู้ปกครอง
      </a>
      <a href="<?= BASE_URL ?>/admin/login"
         style="padding:.45rem 1rem;background:rgba(255,255,255,.15);color:#fff;border-radius:.75rem;font-size:.85rem;font-weight:700;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(4px);transition:all .2s;">
        🔑 สำหรับผู้ดูแล
      </a>
    </div>
  </nav>

  <!-- Hero content -->
  <div class="hero-content">
    <div class="hero-badge">
      <span>✨</span> ระบบจัดการข้อมูลนักเรียนอัจฉริยะ
    </div>
    <h1 class="hero-title">
      แผนที่<span>เยี่ยมบ้าน</span><br>นักเรียน
    </h1>
    <p class="hero-subtitle">
      ค้นหาที่อยู่บ้านนักเรียน ดูตำแหน่งบนแผนที่แบบ Real-time<br>
      และนำทางไปยังบ้านนักเรียนได้ทันที ง่าย สะดวก รวดเร็ว
    </p>
    <div class="hero-buttons">
      <a href="<?= BASE_URL ?>/public/map" class="btn-hero-primary">
        🗺️ ดูแผนที่นักเรียน
      </a>
      <a href="<?= BASE_URL ?>/public/parent_location" class="btn-hero-outline" style="background:rgba(16,185,129,.2);border-color:rgba(52,211,153,.5);">
        👨‍👩‍👧 ระบุพิกัดบ้าน (ผู้ปกครอง)
      </a>
      <a href="<?= BASE_URL ?>/admin/login" class="btn-hero-outline">
        🔑 เข้าระบบผู้ดูแล
      </a>
    </div>

    <!-- Map preview -->
    <div class="map-preview-card">
      <div id="previewMap"></div>
    </div>
  </div>

  <!-- Scroll hint -->
  <div class="scroll-hint">
    ↓ เลื่อนดูข้อมูลเพิ่มเติม
  </div>
</section>

<!-- ═══════════════════════════ STATS ═══════════════════════════ -->
<section class="stats-section">
  <div style="max-width:900px;margin:0 auto;text-align:center;">
    <div class="hero-badge" style="background:#ede9fe;color:#4f46e5;border:1px solid #c4b5fd;margin-bottom:1rem;animation:none;">📊 สถิติระบบ</div>
    <h2 style="font-size:clamp(1.5rem,4vw,2.25rem);font-weight:800;margin-bottom:.75rem;">ข้อมูลในระบบ</h2>
    <p style="color:var(--text-muted);margin-bottom:3rem;">สรุปจำนวนนักเรียนและห้องเรียนที่บันทึกในระบบ</p>

    <div class="grid-cols-3" style="max-width:700px;margin:0 auto;">
      <div style="text-align:center;padding:2rem;border-radius:1.5rem;background:#f8fafc;">
        <div class="stat-number"><?= number_format($total) ?></div>
        <div style="color:var(--text-muted);font-weight:600;margin-top:.35rem;">นักเรียนทั้งหมด</div>
      </div>
      <div style="text-align:center;padding:2rem;border-radius:1.5rem;background:#f8fafc;">
        <div class="stat-number"><?= number_format($withCoords) ?></div>
        <div style="color:var(--text-muted);font-weight:600;margin-top:.35rem;">มีพิกัดบ้าน</div>
      </div>
      <div style="text-align:center;padding:2rem;border-radius:1.5rem;background:#f8fafc;">
        <div class="stat-number"><?= number_format($classes) ?></div>
        <div style="color:var(--text-muted);font-weight:600;margin-top:.35rem;">ห้องเรียน</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ FEATURES ═══════════════════════════ -->
<section class="features-section">
  <div style="max-width:1100px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:3rem;">
      <div class="hero-badge" style="background:#cffafe;color:#0891b2;border:1px solid #a5f3fc;margin-bottom:1rem;animation:none;">⚡ ฟีเจอร์</div>
      <h2 style="font-size:clamp(1.5rem,4vw,2.25rem);font-weight:800;margin-bottom:.75rem;">ความสามารถของระบบ</h2>
      <p style="color:var(--text-muted);">ออกแบบมาเพื่อความสะดวกในการเยี่ยมบ้านนักเรียน</p>
    </div>

    <div class="grid-cols-3">
      <div class="feature-card">
        <div class="feature-icon" style="background:#ede9fe;">🗺️</div>
        <h3 style="font-weight:700;font-size:1.05rem;margin-bottom:.65rem;">แผนที่แบบ Real-time</h3>
        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;">แสดงตำแหน่งบ้านนักเรียนทุกคนบนแผนที่ OpenStreetMap พร้อม Marker สวยงาม</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:#cffafe;">🔍</div>
        <h3 style="font-weight:700;font-size:1.05rem;margin-bottom:.65rem;">ค้นหาได้ทันที</h3>
        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;">ค้นหานักเรียนด้วยชื่อ นามสกุล หรือเลขประจำตัว แผนที่จะโฟกัสตำแหน่งบ้านทันที</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:#d1fae5;">📍</div>
        <h3 style="font-weight:700;font-size:1.05rem;margin-bottom:.65rem;">นำทางด้วย Google Maps</h3>
        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;">กดปุ่มเพื่อเปิดนำทางใน Google Maps ไปยังบ้านนักเรียนได้ทันที</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:#fef3c7;">👨‍🎓</div>
        <h3 style="font-weight:700;font-size:1.05rem;margin-bottom:.65rem;">บริหารข้อมูลนักเรียน</h3>
        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;">เพิ่ม แก้ไข ลบ และนำเข้าข้อมูลผ่าน CSV พร้อมรองรับภาษาไทย UTF-8</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:#fee2e2;">🛰️</div>
        <h3 style="font-weight:700;font-size:1.05rem;margin-bottom:.65rem;">โหมดดาวเทียม</h3>
        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;">สลับแผนที่เป็นภาพถ่ายดาวเทียมเพื่อดูรายละเอียดพื้นที่ได้ชัดเจนยิ่งขึ้น</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:#fce7f3;">📂</div>
        <h3 style="font-weight:700;font-size:1.05rem;margin-bottom:.65rem;">นำเข้าข้อมูล CSV</h3>
        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.7;">นำเข้าข้อมูลนักเรียนจากไฟล์ Excel/CSV ได้ทีละหลายร้อยแถว รองรับภาษาไทย</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ CTA ═══════════════════════════ -->
<section class="cta-section">
  <div style="position:relative;z-index:1;max-width:600px;margin:0 auto;">
    <div style="font-size:3rem;margin-bottom:1rem;">🚀</div>
    <h2 style="color:#fff;font-size:clamp(1.75rem,4vw,2.5rem);font-weight:900;margin-bottom:1rem;">
      พร้อมใช้งานแล้ววันนี้!
    </h2>
    <p style="color:rgba(255,255,255,.8);font-size:1rem;margin-bottom:2rem;line-height:1.7;">
      ดูแผนที่บ้านนักเรียนทุกคนได้ในหน้าเดียว ง่าย รวดเร็ว และใช้งานได้ฟรี
    </p>
    <a href="<?= BASE_URL ?>/public/map" class="btn-hero-primary" style="font-size:1.1rem;padding:1.1rem 2.5rem;">
      🗺️ เปิดแผนที่เดี๋ยวนี้
    </a>
  </div>
</section>

<!-- ═══════════════════════════ FOOTER ═══════════════════════════ -->
<footer class="pub-footer">
  <p><?= APP_NAME ?> · v<?= APP_VERSION ?> · พัฒนาด้วย OpenStreetMap &amp; Leaflet.js</p>
</footer>

<!-- Leaflet for preview map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<script src="<?= BASE_URL ?>/assets/js/map-markers.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';

// Fetch student data for mini preview map
fetch('<?= BASE_URL ?>/public/api?action=students')
  .then(r => r.json())
  .then(json => {
    const map = L.map('previewMap', {
      zoomControl: false, dragging: false,
      scrollWheelZoom: false, touchZoom: false,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(map);

    const cluster = L.markerClusterGroup({ maxClusterRadius: 30, showCoverageOnHover: false });
    const bounds  = [];
    (json.data || []).forEach(s => {
      if (!s.latitude || !s.longitude) return;
      const m = L.marker([parseFloat(s.latitude), parseFloat(s.longitude)], {
        icon: buildMarkerIcon(s)
      });
      m.bindPopup(buildPopupHTML(s));
      cluster.addLayer(m);
      bounds.push([parseFloat(s.latitude), parseFloat(s.longitude)]);
    });

    map.addLayer(cluster);
    if (bounds.length) map.fitBounds(bounds, { padding: [20,20] });
    else map.setView([13.7563,100.5018], 10);
  })
  .catch(() => {
    const map = L.map('previewMap', { zoomControl:false, dragging:false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    map.setView([13.7563,100.5018], 10);
  });
</script>
</body>
</html>
