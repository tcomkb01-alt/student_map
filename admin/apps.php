<?php
/**
 * admin/apps.php – App Center
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$pdo = getDB();

// Quick stats
$totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalVisits   = 0;
$visitedUniq   = 0;

// Check if visit_logs table exists
$tblExists = $pdo->query("SHOW TABLES LIKE 'visit_logs'")->fetchColumn();
if ($tblExists) {
    $totalVisits = (int)$pdo->query('SELECT COUNT(*) FROM visit_logs')->fetchColumn();
    $visitedUniq = (int)$pdo->query('SELECT COUNT(DISTINCT student_id) FROM visit_logs')->fetchColumn();
}

$visitPct = $totalStudents > 0 ? round(($visitedUniq / $totalStudents) * 100) : 0;

$pageTitle  = 'App Center';
$activePage = 'apps';
require_once __DIR__ . '/../components/header.php';
?>

<style>
.app-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}
.app-card {
  background: #fff;
  border-radius: 1.25rem;
  box-shadow: 0 2px 12px rgba(0,0,0,.07);
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
  text-decoration: none;
  color: inherit;
  display: flex;
  flex-direction: column;
}
.app-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px rgba(0,0,0,.13);
}
.app-card-banner {
  height: 130px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 4rem;
  position: relative;
  overflow: hidden;
}
.app-card-banner::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,.08);
}
.app-card-body {
  padding: 1.25rem 1.5rem 1.5rem;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.app-card-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin-bottom: .35rem;
}
.app-card-desc {
  font-size: .82rem;
  color: var(--text-muted);
  flex: 1;
  margin-bottom: 1rem;
  line-height: 1.55;
}
.app-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.app-card-stat {
  font-size: .78rem;
  color: var(--text-muted);
}
.app-card-stat strong {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
}
.app-card.coming-soon {
  opacity: .65;
  pointer-events: none;
}
.coming-badge {
  font-size: .68rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #64748b;
  border-radius: 99px;
  padding: .2rem .7rem;
  border: 1px solid #e2e8f0;
}
</style>

<div class="layout">
  <?php require_once __DIR__ . '/../components/navbar.php'; ?>

  <div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
      <div class="flex items-center gap-3">
        <button id="sidebar-toggle" class="btn btn-light btn-icon" style="display:none;" aria-label="Toggle menu">☰</button>
        <div>
          <h1 style="font-size:1.1rem;font-weight:700;">🧩 App Center</h1>
          <p style="font-size:.78rem;color:var(--text-muted);">ระบบเสริมสำหรับการเยี่ยมบ้านนักเรียน</p>
        </div>
      </div>
    </div>

    <div class="page-content">

      <!-- Section: Home Visit -->
      <div style="margin-bottom:.75rem;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:1rem;">📋 ระบบเยี่ยมบ้าน</div>
      </div>

      <div class="app-grid mb-6">

        <!-- Visit Log -->
        <a href="<?= BASE_URL ?>/admin/visit_log" class="app-card">
          <div class="app-card-banner" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
            📋
          </div>
          <div class="app-card-body">
            <div class="app-card-title">บันทึกการเยี่ยมบ้าน</div>
            <div class="app-card-desc">เพิ่ม แก้ไข และดูประวัติการเยี่ยมบ้านนักเรียน พร้อมบันทึกผลการเยี่ยม ปัญหาที่พบ และแนบรูปถ่าย</div>
            <div class="app-card-footer">
              <div class="app-card-stat">
                ทั้งหมด <strong><?= number_format($totalVisits) ?></strong> ครั้ง
              </div>
              <span class="btn btn-primary btn-sm" style="pointer-events:none;">เปิดใช้งาน →</span>
            </div>
          </div>
        </a>

        <!-- Visit Report -->
        <a href="<?= BASE_URL ?>/admin/visit_report" class="app-card">
          <div class="app-card-banner" style="background:linear-gradient(135deg,#059669,#10b981);">
            📊
          </div>
          <div class="app-card-body">
            <div class="app-card-title">รายงานการเยี่ยมบ้าน</div>
            <div class="app-card-desc">สรุปสถิติการเยี่ยมบ้านรายชั้น รายครู ความคืบหน้า และ Export รายงาน PDF</div>
            <div class="app-card-footer">
              <div class="app-card-stat">
                เยี่ยมแล้ว <strong><?= $visitedUniq ?></strong> / <?= $totalStudents ?> คน
                <span style="color:<?= $visitPct >= 80 ? '#059669' : ($visitPct >= 50 ? '#d97706' : '#dc2626') ?>;">
                  (<?= $visitPct ?>%)
                </span>
              </div>
              <span class="btn btn-success btn-sm" style="pointer-events:none;background:#059669;color:#fff;">เปิดดู →</span>
            </div>
          </div>
        </a>

        <!-- Route Planner (Coming Soon) -->
        <div class="app-card coming-soon">
          <div class="app-card-banner" style="background:linear-gradient(135deg,#0891b2,#06b6d4);">
            🗺️
          </div>
          <div class="app-card-body">
            <div class="app-card-title">วางแผนเส้นทางเยี่ยม <span class="coming-badge">เร็วๆ นี้</span></div>
            <div class="app-card-desc">เลือกนักเรียนหลายคนแล้วระบบจะแนะนำเส้นทางเยี่ยมที่ประหยัดเวลาที่สุด พร้อมส่งออกไป Google Maps</div>
            <div class="app-card-footer">
              <div class="app-card-stat">กำลังพัฒนา</div>
              <span class="coming-badge">Coming Soon</span>
            </div>
          </div>
        </div>

        <!-- Scheduling (Coming Soon) -->
        <div class="app-card coming-soon">
          <div class="app-card-banner" style="background:linear-gradient(135deg,#d97706,#f59e0b);">
            🗓️
          </div>
          <div class="app-card-body">
            <div class="app-card-title">ตารางนัดหมายเยี่ยมบ้าน <span class="coming-badge">เร็วๆ นี้</span></div>
            <div class="app-card-desc">วางแผนตารางเวลาเยี่ยมบ้านล่วงหน้า กำหนดครูรับผิดชอบ และดูปฏิทินภาพรวม</div>
            <div class="app-card-footer">
              <div class="app-card-stat">กำลังพัฒนา</div>
              <span class="coming-badge">Coming Soon</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Progress overview -->
      <?php if ($tblExists): ?>
      <div class="card">
        <div class="card-header">
          <span style="font-weight:700;">📈 ภาพรวมความคืบหน้าการเยี่ยมบ้าน</span>
        </div>
        <div class="card-body">
          <div style="margin-bottom:.5rem;display:flex;justify-content:space-between;font-size:.85rem;">
            <span>เยี่ยมแล้ว <strong><?= $visitedUniq ?> คน</strong> จาก <strong><?= $totalStudents ?> คน</strong></span>
            <span style="font-weight:700;color:<?= $visitPct >= 80 ? '#059669' : ($visitPct >= 50 ? '#d97706' : '#dc2626') ?>;"><?= $visitPct ?>%</span>
          </div>
          <div style="height:12px;background:#f1f5f9;border-radius:99px;overflow:hidden;">
            <div style="height:100%;width:<?= $visitPct ?>%;background:<?= $visitPct >= 80 ? 'linear-gradient(90deg,#059669,#10b981)' : ($visitPct >= 50 ? 'linear-gradient(90deg,#d97706,#f59e0b)' : 'linear-gradient(90deg,#dc2626,#f87171)') ?>;border-radius:99px;transition:width .6s ease;"></div>
          </div>
          <div style="font-size:.76rem;color:var(--text-muted);margin-top:.5rem;">
            ยังเหลืออีก <?= $totalStudents - $visitedUniq ?> คนที่ยังไม่ได้รับการเยี่ยม
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-info" style="border-radius:1rem;">
        ℹ️ ยังไม่ได้สร้างตาราง <code>visit_logs</code> — กรุณารัน SQL ที่ได้รับก่อนใช้งานระบบบันทึกการเยี่ยมบ้าน
      </div>
      <?php endif; ?>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div>

<script>
if (window.innerWidth <= 768) {
  document.getElementById('sidebar-toggle').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
