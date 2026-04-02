<?php
/**
 * public/parent_location.php – Parent GPS Location & Contact Update Page
 * ผู้ปกครองค้นหานักเรียน อัปเดตเบอร์โทร/ที่อยู่ และระบุพิกัดบ้านด้วย GPS
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>ข้อมูลผู้ปกครอง · <?= APP_NAME ?></title>
  <meta name="description" content="ผู้ปกครองระบุพิกัดที่อยู่บ้าน อัปเดตเบอร์โทรศัพท์ และที่อยู่นักเรียน">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary:    #6366f1;
      --primary-dk: #4f46e5;
      --primary-lt: #ede9fe;
      --success:    #10b981;
      --success-lt: #d1fae5;
      --warning:    #f59e0b;
      --warning-lt: #fef3c7;
      --danger:     #ef4444;
      --danger-lt:  #fee2e2;
      --cyan:       #06b6d4;
      --cyan-lt:    #cffafe;
      --text:       #0f172a;
      --muted:      #64748b;
      --border:     #e2e8f0;
      --page-bg:    #f1f5f9;
      --card-bg:    #fff;
      --r-xl:       1.75rem;
      --r-lg:       1.25rem;
      --r-md:       .85rem;
      --shadow-lg:  0 12px 48px rgba(0,0,0,.12);
      --shadow-md:  0 4px 20px rgba(0,0,0,.08);
    }

    body {
      font-family: 'Noto Sans Thai', sans-serif;
      background: var(--page-bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ══ HEADER ══ */
    .site-header {
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #06b6d4 100%);
      padding: 1.25rem 1.5rem 5rem;
      position: relative; overflow: hidden;
    }
    .site-header::before {
      content: ''; position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 50% 60% at 15% 70%, rgba(255,255,255,.07) 0%, transparent 70%),
        radial-gradient(ellipse 35% 45% at 85% 20%, rgba(6,182,212,.2)    0%, transparent 70%);
    }
    .blob { position:absolute; border-radius:50%; filter:blur(60px); opacity:.15; pointer-events:none; }
    .blob-a { width:400px; height:400px; background:#818cf8; top:-180px; right:-100px; }
    .blob-b { width:300px; height:300px; background:#34d399; bottom:-120px; left:-60px; }

    .header-inner {
      position:relative; z-index:2; max-width:640px; margin:0 auto;
      display:flex; align-items:center; gap:.85rem;
    }
    .back-btn {
      display:inline-flex; align-items:center; justify-content:center;
      width:40px; height:40px; border-radius:.75rem;
      background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.3);
      color:#fff; font-size:1.2rem; cursor:pointer;
      text-decoration:none; transition:background .2s; flex-shrink:0;
    }
    .back-btn:hover { background:rgba(255,255,255,.28); }
    .header-text { color:#fff; }
    .header-text h1 { font-size:1.35rem; font-weight:800; }
    .header-text p  { font-size:.82rem; opacity:.8; margin-top:.2rem; }

    /* ══ MAIN WRAP ══ */
    .main-wrap {
      max-width:640px; margin:-3.5rem auto 0;
      padding: 0 1rem 3rem;
      position:relative; z-index:5;
    }

    /* ══ CARD ══ */
    .card {
      background:var(--card-bg); border-radius:var(--r-xl);
      box-shadow:var(--shadow-lg); border:1px solid rgba(255,255,255,.8);
      overflow:hidden; margin-bottom:1.25rem;
    }
    .card-header {
      padding:1.1rem 1.5rem; border-bottom:1px solid var(--border);
      display:flex; align-items:center; gap:.7rem;
    }
    .card-header .icon {
      width:38px; height:38px; border-radius:.75rem;
      display:flex; align-items:center; justify-content:center;
      font-size:1.2rem; flex-shrink:0;
    }
    .card-header .title    { font-weight:700; font-size:1rem; }
    .card-header .subtitle { font-size:.78rem; color:var(--muted); margin-top:.1rem; }
    .card-body { padding:1.5rem; }

    /* ══ SEARCH ══ */
    .search-group { display:flex; gap:.65rem; }
    .iw { flex:1; position:relative; }
    .iw input {
      width:100%; padding:.85rem 1.1rem .85rem 2.75rem;
      border:2px solid var(--border); border-radius:.95rem;
      font-size:1rem; font-family:inherit; color:var(--text);
      background:#f8fafc; outline:none;
      transition:border-color .2s, box-shadow .2s;
    }
    .iw input:focus {
      border-color:var(--primary);
      box-shadow:0 0 0 4px rgba(99,102,241,.12);
      background:#fff;
    }
    .iw .ii {
      position:absolute; left:.9rem; top:50%; transform:translateY(-50%);
      font-size:1rem; pointer-events:none;
    }

    /* ══ BUTTONS ══ */
    .btn {
      display:inline-flex; align-items:center; gap:.45rem;
      padding:.85rem 1.35rem; border-radius:.95rem;
      font-size:.92rem; font-weight:700; font-family:inherit;
      border:none; cursor:pointer; transition:all .22s;
      text-decoration:none; white-space:nowrap;
    }
    .btn-sm { padding:.5rem .9rem; font-size:.82rem; border-radius:.7rem; }
    .btn-primary {
      background:linear-gradient(135deg,var(--primary),var(--primary-dk));
      color:#fff; box-shadow:0 4px 16px rgba(99,102,241,.35);
    }
    .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(99,102,241,.45); }
    .btn-success {
      background:linear-gradient(135deg,#10b981,#059669);
      color:#fff; box-shadow:0 4px 16px rgba(16,185,129,.35);
    }
    .btn-success:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(16,185,129,.45); }
    .btn-outline {
      background:transparent; color:var(--muted);
      border:2px solid var(--border);
    }
    .btn-outline:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-lt); }
    .btn-edit {
      background:var(--primary-lt); color:var(--primary-dk);
      border:1.5px solid #c4b5fd; flex-shrink:0;
    }
    .btn-edit:hover { background:#ddd6fe; }
    .btn-full { width:100%; justify-content:center; }

    /* ══ ALERTS ══ */
    .alert {
      padding:.85rem 1.1rem; border-radius:var(--r-md);
      font-size:.88rem; display:flex; align-items:flex-start; gap:.6rem;
      margin-bottom:1rem;
    }
    .alert-error { background:var(--danger-lt); color:#b91c1c; border:1px solid #fca5a5; }
    .alert-info  { background:#eff6ff;           color:#1d4ed8; border:1px solid #bfdbfe; }

    /* ══ STUDENT PROFILE ══ */
    .student-profile {
      display:flex; align-items:center; gap:1rem;
      padding:1.1rem 1.5rem; border-bottom:1px solid var(--border);
    }
    .student-avatar {
      width:70px; height:70px; border-radius:1rem; flex-shrink:0; overflow:hidden;
      background:linear-gradient(135deg,#ede9fe,#c4b5fd);
      display:flex; align-items:center; justify-content:center;
      font-size:2rem; border:3px solid rgba(99,102,241,.2);
    }
    .student-avatar img { width:100%; height:100%; object-fit:cover; }
    .student-info { flex:1; min-width:0; }
    .student-name { font-size:1.05rem; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .student-meta { font-size:.82rem; color:var(--muted); margin-top:.2rem; }
    .student-id-badge {
      display:inline-block; background:var(--primary-lt); color:var(--primary-dk);
      border-radius:.5rem; padding:.2rem .55rem;
      font-size:.78rem; font-weight:700; margin-top:.3rem;
    }

    /* ══ STATUS BADGES ══ */
    .status-badge {
      display:inline-flex; align-items:center; gap:.4rem;
      padding:.25rem .65rem; border-radius:99px;
      font-size:.75rem; font-weight:700; flex-shrink:0;
    }
    .badge-ok  { background:var(--success-lt); color:#059669; border:1px solid #6ee7b7; }
    .badge-warn{ background:var(--warning-lt); color:#92400e; border:1px solid #fcd34d; }
    .status-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .badge-ok   .status-dot { background:#10b981; }
    .badge-warn .status-dot { background:#f59e0b; }

    /* ══ INFO ROW (editable) ══ */
    .info-section { padding:0 1.5rem 1.25rem; }
    .info-section-title {
      font-size:.73rem; font-weight:700; text-transform:uppercase;
      letter-spacing:.07em; color:var(--muted); margin-bottom:.75rem;
    }
    .info-item {
      background:#f8fafc; border:1.5px solid var(--border);
      border-radius:var(--r-lg); padding:.9rem 1.1rem;
      margin-bottom:.75rem; transition:border-color .2s;
    }
    .info-item:last-child { margin-bottom:0; }
    .info-item-top {
      display:flex; align-items:center; gap:.6rem;
    }
    .info-item-icon { font-size:1.2rem; flex-shrink:0; }
    .info-item-label { font-size:.78rem; color:var(--muted); font-weight:600; flex:1; }
    .info-item-value {
      font-size:.95rem; font-weight:700; color:var(--text);
      margin-top:.35rem; padding-left:1.85rem;
      line-height:1.5; word-break:break-word;
    }
    .info-item-value.empty { color:var(--muted); font-weight:500; font-style:italic; }
    .info-item-bottom {
      display:flex; align-items:center; justify-content:space-between;
      margin-top:.6rem; padding-left:1.85rem;
    }

    /* ══ LOCATION STATUS (big) ══ */
    .location-status {
      display:flex; align-items:center; gap:.55rem;
      padding:.75rem 1rem; border-radius:var(--r-md);
      font-size:.85rem; font-weight:600; margin-bottom:.75rem;
    }
    .location-status.has-coords { background:var(--success-lt); color:#059669; border:1px solid #6ee7b7; }
    .location-status.no-coords  { background:var(--warning-lt); color:#92400e; border:1px solid #fcd34d; }
    .ls-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
    .has-coords .ls-dot { background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.25); }
    .no-coords  .ls-dot { background:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.25); }

    /* ══ GPS section ══ */
    .gps-section { padding:0 1.5rem 1.5rem; }

    /* ══ MODAL ══ */
    .modal-overlay {
      position:fixed; inset:0; z-index:1000;
      background:rgba(15,23,42,.6); backdrop-filter:blur(6px);
      display:flex; align-items:center; justify-content:center;
      padding:1rem; opacity:0; pointer-events:none; transition:opacity .28s;
    }
    .modal-overlay.open { opacity:1; pointer-events:all; }
    .modal-box {
      background:#fff; border-radius:var(--r-xl);
      width:100%; max-width:460px;
      box-shadow:0 32px 80px rgba(0,0,0,.2);
      transform:translateY(30px) scale(.97);
      transition:transform .3s cubic-bezier(.34,1.56,.64,1);
      overflow:hidden;
    }
    .modal-overlay.open .modal-box { transform:translateY(0) scale(1); }
    .modal-header {
      padding:1.2rem 1.5rem; border-bottom:1px solid var(--border);
      display:flex; align-items:center; justify-content:space-between;
    }
    .modal-header h2 { font-size:1.05rem; font-weight:800; }
    .modal-close {
      background:#f1f5f9; border:none; border-radius:.6rem;
      width:34px; height:34px; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      font-size:1rem; color:var(--muted); transition:background .2s;
    }
    .modal-close:hover { background:var(--danger-lt); color:var(--danger); }
    .modal-body { padding:1.5rem; }
    .modal-footer {
      padding:1rem 1.5rem; border-top:1px solid var(--border);
      display:flex; gap:.6rem; justify-content:flex-end;
    }

    /* ══ Edit modal inputs ══ */
    .edit-field-wrap { margin-bottom:1rem; }
    .edit-label {
      display:block; font-size:.8rem; font-weight:700;
      color:var(--muted); margin-bottom:.45rem;
    }
    .edit-input {
      width:100%; padding:.85rem 1.05rem;
      border:2px solid var(--border); border-radius:.9rem;
      font-size:1rem; font-family:inherit; color:var(--text);
      background:#f8fafc; outline:none;
      transition:border-color .2s, box-shadow .2s;
    }
    .edit-input:focus {
      border-color:var(--primary);
      box-shadow:0 0 0 4px rgba(99,102,241,.12);
      background:#fff;
    }
    textarea.edit-input {
      resize:vertical; min-height:100px; line-height:1.6;
    }
    .edit-hint { font-size:.77rem; color:var(--muted); margin-top:.35rem; }
    .edit-error { font-size:.82rem; color:#b91c1c; margin-top:.4rem; display:none; }

    /* ══ GPS MODAL ══ */
    .gps-loading {
      display:flex; flex-direction:column; align-items:center; gap:1rem;
      padding:2rem; text-align:center;
    }
    .pulse-ring {
      width:70px; height:70px; border-radius:50%;
      border:4px solid var(--primary);
      position:relative; display:flex; align-items:center; justify-content:center;
    }
    .pulse-ring::after {
      content:''; position:absolute; inset:-8px; border-radius:50%;
      border:4px solid rgba(99,102,241,.25);
      animation:pulseRing 1.5s ease infinite;
    }
    @keyframes pulseRing { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.4);opacity:0} }
    .pulse-ring-icon { font-size:1.8rem; }

    .coord-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
    .coord-box {
      background:#f8fafc; border:1.5px solid var(--border);
      border-radius:var(--r-md); padding:.9rem 1rem;
    }
    .coord-box .cl { font-size:.75rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
    .coord-box .cv { font-size:1.15rem; font-weight:800; color:var(--primary-dk); margin-top:.15rem; font-variant-numeric:tabular-nums; }
    .coord-acc { font-size:.8rem; color:var(--muted); margin-top:.6rem; }
    #modal-map { height:200px; border-radius:var(--r-md); margin-top:1rem; overflow:hidden; }

    /* ══ TOAST ══ */
    .toast {
      position:fixed; bottom:1.5rem; left:50%;
      transform:translateX(-50%);
      background:#0f172a; color:#fff;
      padding:.75rem 1.5rem; border-radius:99px;
      font-size:.9rem; font-weight:600;
      box-shadow:0 8px 32px rgba(0,0,0,.25);
      z-index:3000; display:flex; align-items:center; gap:.5rem;
      opacity:0; transition:opacity .3s; pointer-events:none;
    }
    .toast.show { opacity:1; }
    .toast.success { background:linear-gradient(135deg,#059669,#10b981); }
    .toast.error   { background:linear-gradient(135deg,#dc2626,#ef4444); }

    /* ══ SPINNER ══ */
    .spinner {
      width:17px; height:17px; flex-shrink:0;
      border:2.5px solid rgba(255,255,255,.35);
      border-top-color:#fff; border-radius:50%;
      animation:spin .7s linear infinite;
    }
    @keyframes spin { to { transform:rotate(360deg); } }

    @media(max-width:480px) {
      .search-group { flex-direction:column; }
      .student-profile { padding:1rem; }
      .card-body,.info-section,.gps-section { padding-left:1rem; padding-right:1rem; }
    }
  </style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<div class="site-header">
  <div class="blob blob-a"></div>
  <div class="blob blob-b"></div>
  <div class="header-inner">
    <a href="<?= BASE_URL ?>/public/" class="back-btn" title="กลับหน้าหลัก">←</a>
    <div class="header-text">
      <h1>👨‍👩‍👧 อัปเดตข้อมูลผู้ปกครอง</h1>
      <p>ค้นหานักเรียน · อัปเดตเบอร์โทร · ที่อยู่ · พิกัดบ้าน</p>
    </div>
  </div>
</div>

<!-- ═══ MAIN ═══ -->
<div class="main-wrap">

  <!-- ── Card: ค้นหา ── -->
  <div class="card" id="card-search">
    <div class="card-header">
      <div class="icon" style="background:#ede9fe;">🔍</div>
      <div>
        <div class="title">ค้นหานักเรียน</div>
        <div class="subtitle">กรอกเลขประจำตัวนักเรียนเพื่อค้นหา</div>
      </div>
    </div>
    <div class="card-body">
      <div id="search-error" class="alert alert-error" style="display:none;"></div>
      <div class="search-group">
        <div class="iw">
          <span class="ii">🎓</span>
          <input type="text" id="student-id-input"
                 placeholder="เลขประจำตัวนักเรียน เช่น 67001"
                 inputmode="text" autocomplete="off" maxlength="30">
        </div>
        <button class="btn btn-primary" id="btn-search" onclick="searchStudent()">
          <span id="search-btn-text">ค้นหา</span>
          <span id="search-spinner" class="spinner" style="display:none;"></span>
        </button>
      </div>
      <p style="font-size:.78rem;color:var(--muted);margin-top:.65rem;">
        💡 หากไม่ทราบเลขประจำตัว ติดต่อครูประจำชั้น
      </p>
    </div>
  </div>

  <!-- ── Card: ข้อมูลนักเรียน ── -->
  <div class="card" id="card-student" style="display:none;">

    <!-- โปรไฟล์ -->
    <div class="student-profile" id="student-profile-area"></div>

    <!-- ข้อมูลติดต่อ -->
    <div class="info-section" style="padding-top:1.25rem;">
      <div class="info-section-title">📋 ข้อมูลติดต่อ</div>

      <!-- เบอร์โทร -->
      <div class="info-item" id="item-phone">
        <div class="info-item-top">
          <span class="info-item-icon">📞</span>
          <span class="info-item-label">เบอร์โทรศัพท์ผู้ปกครอง</span>
        </div>
        <div class="info-item-value" id="val-phone">—</div>
        <div class="info-item-bottom">
          <span id="badge-phone"></span>
          <button class="btn btn-edit btn-sm" onclick="openEditModal('phone')">
            ✏️ แก้ไข
          </button>
        </div>
      </div>

      <!-- ที่อยู่ -->
      <div class="info-item" id="item-address">
        <div class="info-item-top">
          <span class="info-item-icon">🏠</span>
          <span class="info-item-label">ที่อยู่บ้านนักเรียน</span>
        </div>
        <div class="info-item-value" id="val-address">—</div>
        <div class="info-item-bottom">
          <span id="badge-address"></span>
          <button class="btn btn-edit btn-sm" onclick="openEditModal('address')">
            ✏️ แก้ไข
          </button>
        </div>
      </div>
    </div>

    <!-- พิกัดบ้าน -->
    <div class="gps-section">
      <div class="info-section-title">📍 พิกัดที่อยู่บ้าน (GPS)</div>
      <div id="location-status-badge"></div>
      <button class="btn btn-primary btn-full" id="btn-open-gps" onclick="openGPSModal()">
        📡 ระบุตำแหน่งบ้านด้วย GPS
      </button>
    </div>

  </div><!-- /card-student -->

</div><!-- /main-wrap -->

<!-- ═══ EDIT CONTACT MODAL ═══ -->
<div class="modal-overlay" id="edit-modal" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="edit-modal-title">✏️ แก้ไขข้อมูล</h2>
      <button class="modal-close" onclick="closeEditModal()" title="ปิด">✕</button>
    </div>
    <div class="modal-body">
      <div class="edit-field-wrap">
        <label class="edit-label" id="edit-field-label">ข้อมูล</label>
        <!-- phone input -->
        <input type="tel" class="edit-input" id="edit-phone-input"
               placeholder="เช่น 0812345678" maxlength="20"
               inputmode="tel" style="display:none;">
        <!-- address textarea -->
        <textarea class="edit-input" id="edit-address-input"
                  placeholder="กรอกที่อยู่บ้านนักเรียน..."
                  maxlength="1000" rows="4"
                  style="display:none;"></textarea>
        <div class="edit-error" id="edit-error">⚠️ กรุณากรอกข้อมูล</div>
      </div>
      <p class="edit-hint" id="edit-hint"></p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeEditModal()">ยกเลิก</button>
      <button class="btn btn-success" id="btn-save-contact" onclick="saveContact()">
        💾 บันทึก
      </button>
    </div>
  </div>
</div>

<!-- ═══ GPS MODAL ═══ -->
<div class="modal-overlay" id="gps-modal" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-header">
      <h2>📡 ระบุตำแหน่งบ้าน</h2>
      <button class="modal-close" onclick="closeGPSModal()" title="ปิด">✕</button>
    </div>
    <div class="modal-body">
      <div class="gps-loading" id="gps-loading">
        <div class="pulse-ring"><span class="pulse-ring-icon">📡</span></div>
        <div>
          <p style="font-weight:700;font-size:.95rem;">กำลังระบุตำแหน่ง GPS…</p>
          <p style="font-size:.82rem;color:var(--muted);margin-top:.35rem;">กรุณาอนุญาตการเข้าถึงตำแหน่ง</p>
        </div>
      </div>
      <div id="gps-error" class="alert alert-error" style="display:none;"></div>
      <div id="gps-result" style="display:none;">
        <div class="coord-grid">
          <div class="coord-box">
            <div class="cl">Latitude (ละติจูด)</div>
            <div class="cv" id="display-lat">—</div>
          </div>
          <div class="coord-box">
            <div class="cl">Longitude (ลองจิจูด)</div>
            <div class="cv" id="display-lng">—</div>
          </div>
        </div>
        <div class="coord-acc" id="display-accuracy"></div>
        <div id="modal-map"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeGPSModal()">ยกเลิก</button>
      <button class="btn btn-primary" id="btn-retry" onclick="getGPS()" style="display:none;">🔄 ลองใหม่</button>
      <button class="btn btn-success" id="btn-save-loc" onclick="saveLocation()" style="display:none;">💾 บันทึกตำแหน่ง</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';

let currentStudent = null;
let currentLat = null, currentLng = null;
let modalMap = null, modalMarker = null;
let editField = null; // 'phone' | 'address'

/* ═══════════ SEARCH ═══════════ */
async function searchStudent() {
  const val = document.getElementById('student-id-input').value.trim();
  if (!val) { showSearchError('กรุณากรอกเลขประจำตัวนักเรียน'); return; }
  setSearchLoading(true);
  clearSearchError();
  try {
    const res  = await fetch(BASE_URL + '/public/api?action=find_student&student_id=' + encodeURIComponent(val));
    const json = await res.json();
    if (!json.success || !json.data) {
      showSearchError(json.message || 'ไม่พบนักเรียนที่มีเลขประจำตัวนี้');
      document.getElementById('card-student').style.display = 'none';
      return;
    }
    currentStudent = json.data;
    renderStudentCard(currentStudent);
    document.getElementById('card-student').style.display = 'block';
    document.getElementById('card-student').scrollIntoView({ behavior:'smooth', block:'start' });
  } catch(e) {
    showSearchError('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่');
  } finally { setSearchLoading(false); }
}

function setSearchLoading(on) {
  document.getElementById('search-btn-text').textContent = on ? 'กำลังค้นหา…' : 'ค้นหา';
  document.getElementById('search-spinner').style.display = on ? 'inline-block' : 'none';
  document.getElementById('btn-search').disabled = on;
}
function showSearchError(msg) {
  const el = document.getElementById('search-error');
  el.innerHTML = '⚠️ ' + msg; el.style.display = 'flex';
}
function clearSearchError() { document.getElementById('search-error').style.display = 'none'; }

/* ═══════════ RENDER STUDENT CARD ═══════════ */
function renderStudentCard(s) {
  // Profile
  const pa = document.getElementById('student-profile-area');
  pa.innerHTML = `
    <div class="student-avatar">
      ${s.profile_image
        ? `<img src="${BASE_URL}/uploads/students/${escHtml(s.profile_image)}" alt="รูปนักเรียน" onerror="this.parentElement.innerHTML='👤'">`
        : '👤'}
    </div>
    <div class="student-info">
      <div class="student-name">${escHtml(s.prefix)} ${escHtml(s.first_name)} ${escHtml(s.last_name)}</div>
      <div class="student-meta">ชั้น ${escHtml(s.class || '—')}</div>
      <div class="student-id-badge">🎓 ${escHtml(s.student_id)}</div>
    </div>`;

  renderPhoneRow(s.parent_phone);
  renderAddressRow(s.address);
  renderLocationStatus(s.latitude, s.longitude);
}

function renderPhoneRow(phone) {
  const hasPhone = phone && phone.trim();
  document.getElementById('val-phone').innerHTML =
    hasPhone ? escHtml(phone) : '<span class="empty">ยังไม่มีข้อมูล</span>';
  document.getElementById('val-phone').className = 'info-item-value' + (hasPhone ? '' : ' empty');
  document.getElementById('badge-phone').outerHTML =
    `<span id="badge-phone" class="status-badge ${hasPhone ? 'badge-ok' : 'badge-warn'}">
      <span class="status-dot"></span>
      ${hasPhone ? 'มีข้อมูลแล้ว' : 'ยังไม่มีข้อมูล'}
    </span>`;
}

function renderAddressRow(address) {
  const hasAddr = address && address.trim();
  document.getElementById('val-address').innerHTML =
    hasAddr ? escHtml(address) : '<span class="empty">ยังไม่มีข้อมูล</span>';
  document.getElementById('val-address').className = 'info-item-value' + (hasAddr ? '' : ' empty');
  document.getElementById('badge-address').outerHTML =
    `<span id="badge-address" class="status-badge ${hasAddr ? 'badge-ok' : 'badge-warn'}">
      <span class="status-dot"></span>
      ${hasAddr ? 'มีข้อมูลแล้ว' : 'ยังไม่มีข้อมูล'}
    </span>`;
}

function renderLocationStatus(lat, lng) {
  const hasCrd = lat && lng;
  document.getElementById('location-status-badge').innerHTML = hasCrd
    ? `<div class="location-status has-coords">
         <div class="ls-dot"></div>
         ✅ มีพิกัดแผนที่แล้ว (${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)})
       </div>`
    : `<div class="location-status no-coords">
         <div class="ls-dot"></div>
         ⚠️ ยังไม่มีพิกัดที่อยู่บ้าน — กรุณาระบุตำแหน่ง
       </div>`;
  document.getElementById('btn-open-gps').innerHTML =
    hasCrd ? '📡 อัปเดตตำแหน่งบ้านใหม่' : '📡 ระบุตำแหน่งบ้านด้วย GPS';
}

/* ═══════════ EDIT CONTACT MODAL ═══════════ */
const FIELD_CONFIG = {
  phone: {
    title:       '📞 แก้ไขเบอร์โทรศัพท์',
    label:       'เบอร์โทรศัพท์ผู้ปกครอง',
    hint:        'กรอกเบอร์โทรศัพท์ 10 หลัก เช่น 0812345678',
    inputId:     'edit-phone-input',
    dbField:     'parent_phone',
    studentKey:  'parent_phone',
    renderFn:    (v) => renderPhoneRow(v),
  },
  address: {
    title:       '🏠 แก้ไขที่อยู่บ้าน',
    label:       'ที่อยู่บ้านนักเรียน',
    hint:        'กรอกที่อยู่ให้ครบถ้วน เช่น บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด',
    inputId:     'edit-address-input',
    dbField:     'address',
    studentKey:  'address',
    renderFn:    (v) => renderAddressRow(v),
  },
};

function openEditModal(field) {
  if (!currentStudent) return;
  editField = field;
  const cfg = FIELD_CONFIG[field];

  document.getElementById('edit-modal-title').textContent = cfg.title;
  document.getElementById('edit-field-label').textContent = cfg.label;
  document.getElementById('edit-hint').textContent = cfg.hint;
  document.getElementById('edit-error').style.display = 'none';

  // Show correct input
  document.getElementById('edit-phone-input').style.display   = field === 'phone'   ? 'block' : 'none';
  document.getElementById('edit-address-input').style.display = field === 'address' ? 'block' : 'none';

  // Pre-fill value
  const currentVal = currentStudent[cfg.studentKey] || '';
  document.getElementById(cfg.inputId).value = currentVal;

  document.getElementById('edit-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById(cfg.inputId).focus(), 200);
}

function closeEditModal() {
  document.getElementById('edit-modal').classList.remove('open');
  document.body.style.overflow = '';
  editField = null;
}

async function saveContact() {
  if (!editField || !currentStudent) return;
  const cfg   = FIELD_CONFIG[editField];
  const input = document.getElementById(cfg.inputId);
  const value = input.value.trim();

  // Validate phone
  if (editField === 'phone') {
    const digits = value.replace(/\D/g,'');
    if (value && (digits.length < 9 || digits.length > 15)) {
      showEditError('เบอร์โทรศัพท์ไม่ถูกต้อง (ควรมี 9-15 หลัก)');
      return;
    }
  }

  const btn = document.getElementById('btn-save-contact');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> กำลังบันทึก…';
  document.getElementById('edit-error').style.display = 'none';

  try {
    const res  = await fetch(BASE_URL + '/public/api', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        action:     'update_contact',
        student_id: currentStudent.student_id,
        field:      cfg.dbField,
        value:      value,
      }),
    });
    const json = await res.json();

    if (json.success) {
      currentStudent[cfg.studentKey] = value;
      cfg.renderFn(value);
      closeEditModal();
      showToast('✅ บันทึกข้อมูลเรียบร้อยแล้ว', 'success');
    } else {
      showEditError(json.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
    }
  } catch(e) {
    showEditError('เกิดข้อผิดพลาดในการเชื่อมต่อ');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '💾 บันทึก';
  }
}

function showEditError(msg) {
  const el = document.getElementById('edit-error');
  el.textContent = '⚠️ ' + msg;
  el.style.display = 'block';
}

/* ═══════════ GPS MODAL ═══════════ */
function openGPSModal() {
  document.getElementById('gps-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
  resetGPSState();
  getGPS();
}

function closeGPSModal() {
  document.getElementById('gps-modal').classList.remove('open');
  document.body.style.overflow = '';
}

function resetGPSState() {
  document.getElementById('gps-loading').style.display = 'flex';
  document.getElementById('gps-error').style.display   = 'none';
  document.getElementById('gps-result').style.display  = 'none';
  document.getElementById('btn-retry').style.display   = 'none';
  document.getElementById('btn-save-loc').style.display = 'none';
  currentLat = null; currentLng = null;
  if (modalMap) { modalMap.remove(); modalMap = null; modalMarker = null; }
}

function getGPS() {
  if (!navigator.geolocation) {
    showGPSError('เบราว์เซอร์ของคุณไม่รองรับ GPS'); return;
  }
  document.getElementById('gps-loading').style.display = 'flex';
  document.getElementById('gps-error').style.display   = 'none';
  document.getElementById('gps-result').style.display  = 'none';
  document.getElementById('btn-retry').style.display   = 'none';
  document.getElementById('btn-save-loc').style.display = 'none';

  navigator.geolocation.getCurrentPosition(
    onGPSSuccess, onGPSError,
    { enableHighAccuracy:true, timeout:15000, maximumAge:0 }
  );
}

function onGPSSuccess(pos) {
  currentLat = pos.coords.latitude;
  currentLng = pos.coords.longitude;
  const acc  = pos.coords.accuracy;

  document.getElementById('gps-loading').style.display  = 'none';
  document.getElementById('gps-result').style.display   = 'block';
  document.getElementById('btn-save-loc').style.display = 'inline-flex';

  document.getElementById('display-lat').textContent = currentLat.toFixed(7);
  document.getElementById('display-lng').textContent = currentLng.toFixed(7);
  document.getElementById('display-accuracy').textContent = `📏 ความแม่นยำ: ±${Math.round(acc)} เมตร`;

  setTimeout(() => {
    if (modalMap) { modalMap.remove(); }
    modalMap = L.map('modal-map', { zoomControl:true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution:'© OpenStreetMap', maxZoom:19
    }).addTo(modalMap);
    modalMap.setView([currentLat, currentLng], 16);
    const icon = L.divIcon({
      html:`<div style="background:#4f46e5;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);"></div>`,
      className:'', iconSize:[16,16], iconAnchor:[8,8]
    });
    modalMarker = L.marker([currentLat,currentLng],{icon}).addTo(modalMap);
    modalMarker.bindPopup('📍 ตำแหน่งปัจจุบันของคุณ').openPopup();
  }, 150);
}

function onGPSError(err) {
  const msgs = {
    1: 'คุณปฏิเสธการเข้าถึง GPS กรุณาอนุญาตตำแหน่งในการตั้งค่าเบราว์เซอร์',
    2: 'ไม่สามารถระบุตำแหน่งได้ กรุณาเปิด GPS หรือเชื่อมต่ออินเทอร์เน็ต',
    3: 'หมดเวลาในการระบุตำแหน่ง กรุณาลองใหม่'
  };
  showGPSError(msgs[err.code] || 'เกิดข้อผิดพลาดในการระบุตำแหน่ง');
}

function showGPSError(msg) {
  document.getElementById('gps-loading').style.display = 'none';
  const el = document.getElementById('gps-error');
  el.innerHTML = '⚠️ ' + msg; el.style.display = 'flex';
  document.getElementById('btn-retry').style.display = 'inline-flex';
}

async function saveLocation() {
  if (!currentLat || !currentLng || !currentStudent) return;
  const btn = document.getElementById('btn-save-loc');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> กำลังบันทึก…';

  try {
    const res  = await fetch(BASE_URL + '/public/api', {
      method: 'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify({
        action:     'save_location',
        student_id: currentStudent.student_id,
        latitude:   currentLat,
        longitude:  currentLng,
      }),
    });
    const json = await res.json();
    if (json.success) {
      currentStudent.latitude  = currentLat;
      currentStudent.longitude = currentLng;
      renderLocationStatus(currentLat, currentLng);
      closeGPSModal();
      showToast('✅ บันทึกพิกัดบ้านเรียบร้อยแล้ว', 'success');
    } else {
      showToast('❌ ' + (json.message || 'เกิดข้อผิดพลาด'), 'error');
    }
  } catch(e) {
    showToast('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '💾 บันทึกตำแหน่ง';
  }
}

/* ═══════════ TOAST ═══════════ */
let toastTimer;
function showToast(msg, type = '') {
  clearTimeout(toastTimer);
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast ' + type;
  t.classList.add('show');
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

/* ═══════════ UTILS ═══════════ */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Keyboard shortcuts
document.getElementById('student-id-input')
  .addEventListener('keydown', e => { if (e.key === 'Enter') searchStudent(); });

// Close modals on overlay click
document.getElementById('edit-modal')
  .addEventListener('click', e => { if (e.target === e.currentTarget) closeEditModal(); });
document.getElementById('gps-modal')
  .addEventListener('click', e => { if (e.target === e.currentTarget) closeGPSModal(); });

// Save on Enter in phone field
document.getElementById('edit-phone-input')
  .addEventListener('keydown', e => { if (e.key === 'Enter') saveContact(); });
</script>
</body>
</html>
