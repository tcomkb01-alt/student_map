<?php
/**
 * admin/visit_report.php – Home Visit progress report
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$pdo = getDB();

// ── API ENDPOINT ─────────────────────────────────────────────────────────────
if (isset($_GET['api']) && $_GET['api'] === 'missing_by_class') {
    header('Content-Type: application/json; charset=utf-8');
    $class = $_GET['class'] ?? '';
    
    if (empty($class)) {
        echo json_encode(['success' => false, 'message' => 'Class is required']);
        exit;
    }

    $sql = "
        SELECT s.id, s.student_id, s.prefix, s.first_name, s.last_name, s.student_number, s.parent_phone, s.latitude, s.longitude
        FROM students s
        LEFT JOIN visit_logs vl ON s.id = vl.student_id
        WHERE vl.id IS NULL AND s.class = :class
        ORDER BY s.student_number
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':class' => $class]);
    $students = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $students], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET STATS ───────────────────────────────────────────────────────────────

// 1. Overall Stats
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$uniqueVisited = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM visit_logs")->fetchColumn();
$notVisited    = $totalStudents - $uniqueVisited;
$overallPct    = $totalStudents > 0 ? round(($uniqueVisited / $totalStudents) * 100) : 0;

// 2. Stats by Class
$classStatsSql = "
    SELECT 
        s.class,
        COUNT(s.id) AS total_count,
        COUNT(DISTINCT vl.student_id) AS visited_count
    FROM students s
    LEFT JOIN visit_logs vl ON s.id = vl.student_id
    WHERE s.class != ''
    GROUP BY s.class
    ORDER BY s.class ASC
";
$classStats = $pdo->query($classStatsSql)->fetchAll();

$pageTitle  = 'รายงานสรุปผลการเยี่ยมบ้าน';
$activePage = 'apps';
require_once __DIR__ . '/../components/header.php';
?>

<style>
/* ── Premium Aesthetic ── */
:root {
  --primary-gradient: linear-gradient(135deg, #4f46e5, #7c3aed);
  --success-gradient: linear-gradient(135deg, #059669, #10b981);
  --warning-gradient: linear-gradient(135deg, #d97706, #f59e0b);
  --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.report-header-banner {
  background: var(--primary-gradient);
  color: #fff;
  padding: 2.5rem 2rem;
  border-radius: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: var(--card-shadow);
  text-align: center;
  position: relative;
  overflow: hidden;
}

.report-header-banner::after {
  content: '📊';
  position: absolute;
  right: -20px;
  bottom: -20px;
  font-size: 8rem;
  opacity: 0.15;
}

.glass-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: 1.25rem;
  border: 1px solid rgba(255,255,255,0.3);
  box-shadow: var(--card-shadow);
  margin-bottom: 2rem;
}

.stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card-premium {
  padding: 1.5rem;
  border-radius: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  background: #fff;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 12px rgba(0,0,0,.05);
}

.stat-icon-wrap {
  width: 54px; height: 54px;
  border-radius: 1rem;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
}

.progress-modern {
  height: 12px;
  background: #f1f5f9;
  border-radius: 99px;
  overflow: hidden;
  position: relative;
}
.progress-modern-bar {
  height: 100%;
  border-radius: 99px;
  transition: width 1s cubic-bezier(0.17, 0.67, 0.83, 0.67);
}

@media screen and (max-width:768px) {
  .stat-row { grid-template-columns: 1fr; }
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
          <a href="<?= BASE_URL ?>/admin/apps" style="font-size:.78rem;color:var(--text-muted);text-decoration:none;">🧩 App Center</a>
          <h1 style="font-size:1.1rem;font-weight:700;">รายงานสรุปผลการเยี่ยมบ้าน</h1>
          <p style="font-size:.78rem;color:var(--text-muted);">รายงานภาพรวมความคืบหน้าระดับสถานศึกษา</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
         <a href="<?= BASE_URL ?>/admin/visit_log" class="btn btn-primary btn-sm">📋 บันทึกการเยี่ยม</a>
      </div>
    </div>

    <div class="page-content" style="max-width: 1000px; margin: 0 auto;">

      <!-- Header Banner -->
      <div class="report-header-banner">
        <div style="font-size:.9rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem;opacity:.9;">School Overview Report</div>
        <h2 style="font-size:1.8rem;font-weight:800;margin-bottom:1rem;">ระบบติดตามแผนที่เยี่ยมบ้าน</h2>
        <div style="font-size:.95rem;opacity:.85;">รายงานวิเคราะห์สัดส่วนความคืบหน้าการลงพื้นที่เยี่ยมบ้านของคณะครูประจำชั้น</div>
      </div>

      <!-- Overall Statistics Row -->
      <div class="stat-row">
        <div class="stat-card-premium">
          <div class="stat-icon-wrap" style="background:#e0e7ff;color:#4f46e5;">👨‍🎓</div>
          <div>
            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted);">นักเรียนทั้งหมด</div>
            <div style="font-size:1.4rem;font-weight:800;"><?= number_format($totalStudents) ?> <small style="font-size:.8rem;font-weight:400;">คน</small></div>
          </div>
        </div>
        <div class="stat-card-premium">
          <div class="stat-icon-wrap" style="background:#d1fae5;color:#059669;">✅</div>
          <div>
            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted);">เยี่ยมบ้านแล้ว</div>
            <div style="font-size:1.4rem;font-weight:800;color:#059669;"><?= number_format($uniqueVisited) ?> <small style="font-size:.8rem;font-weight:400;color:var(--text-muted);">คน</small></div>
            <div style="font-size:.72rem;font-weight:700;margin-top:2px;">คิดเป็น <?= $overallPct ?>% ของทั้งหมด</div>
          </div>
        </div>
        <div class="stat-card-premium">
          <div class="stat-icon-wrap" style="background:#fef3c7;color:#d97706;">⌛</div>
          <div>
            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted);">ยังไม่ได้เยี่ยม</div>
            <div style="font-size:1.4rem;font-weight:800;color:#d97706;"><?= number_format($notVisited) ?> <small style="font-size:.8rem;font-weight:400;color:var(--text-muted);">คน</small></div>
            <div style="font-size:.72rem;font-weight:700;margin-top:2px;">เป้าหมายที่เหลือ <?= 100 - $overallPct ?>%</div>
          </div>
        </div>
      </div>

      <!-- Class Progress -->
      <div class="glass-card">
        <div class="card-header" style="border-bottom: 2px solid #f1f5f9; padding: 1.5rem;">
          <h3 style="font-size:1rem;font-weight:700;margin:0;">📈 รายงานความคืบหน้าแยกตามห้องเรียน</h3>
          <p style="font-size:.75rem;color:var(--text-muted);margin:0;">สรุปสัดส่วนการลงพื้นที่เยี่ยมบ้านต่อนักเรียนในกำกับดูแล</p>
        </div>
        <div class="card-body" style="padding: 1.5rem 2rem;">
          <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
            <?php foreach ($classStats as $row): 
                $classPct = $row['total_count'] > 0 ? round(($row['visited_count'] / $row['total_count']) * 100) : 0;
                $barColor = $classPct >= 80 ? '#10b981' : ($classPct >= 50 ? '#f59e0b' : '#ef4444');
                $remaining = $row['total_count'] - $row['visited_count'];
            ?>
            <div class="class-progress-item">
              <div class="flex justify-between items-end mb-2">
                <div>
                    <span style="font-weight:700;color:#334155;font-size:1.05rem;">ชั้นเรียน <?= htmlspecialchars($row['class']) ?></span>
                </div>
                <div style="text-align:right;">
                  <span style="font-weight:600; font-size:.85rem;">
                    <span style="color:var(--text-muted);">สำเร็จ:</span> 
                    <span style="color:<?= $barColor ?>;"><?= $row['visited_count'] ?></span> / <?= $row['total_count'] ?> คน 
                    <span style="background:<?= $barColor ?>;color:#fff;padding:.15rem .6rem;border-radius:99px;font-size:.7rem;margin-left:.4rem;"><?= $classPct ?>%</span>
                  </span>
                </div>
              </div>
              <div class="progress-modern" style="margin-bottom:.75rem;">
                <div class="progress-modern-bar" style="width:<?= $classPct ?>%; background:<?= $barColor ?>;"></div>
              </div>
              <?php if ($remaining > 0): ?>
              <button class="btn btn-light btn-sm" style="font-size:.75rem; padding:.3rem .8rem; border-radius:.5rem;" onclick="openMissingModal('<?= addslashes($row['class']) ?>')">
                🔍 รายชื่อที่ยังไม่ได้เยี่ยม (<?= $remaining ?> คน)
              </button>
              <?php else: ?>
              <span style="font-size:.75rem; color:#10b981; font-weight:600;">✨ เยี่ยมครบทุกห้องแล้ว</span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div>

<!-- ═══ MODAL: Missing Students ═══ -->
<div id="missingModal" class="modal-overlay">
  <div class="modal" style="max-width:700px;">
    <div class="modal-header">
      <div>
        <span class="modal-title" id="missingModalTitle">รายชื่อที่ยังไม่ได้เยี่ยม</span>
        <div id="missingModalSub" style="font-size:.78rem;color:var(--text-muted);margin-top:2px;"></div>
      </div>
      <button class="modal-close" onclick="closeModal('missingModal')">✕</button>
    </div>
    <div class="modal-body" style="padding:0;">
      <div class="table-responsive" style="max-height: 500px;">
        <table class="table table-sm" style="margin-bottom:0;">
          <thead style="position: sticky; top: 0; background: #fff; z-index: 10;">
            <tr>
              <th style="padding:.75rem 1rem;">เลขที่</th>
              <th style="padding:.75rem 1rem;">ชื่อ-นามสกุล</th>
              <th style="padding:.75rem 1rem;text-align:center;">พิกัด</th>
              <th style="padding:.75rem 1rem;text-align:center;">จัดการ</th>
            </tr>
          </thead>
          <tbody id="missingTableBody">
            <!-- Dynamic Content -->
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('missingModal')">ปิดหน้าต่าง</button>
    </div>
  </div>
</div>

<script>
/**
 * openMissingModal(className)
 */
async function openMissingModal(cls) {
  document.getElementById('missingModalTitle').textContent = `รายชื่อนักเรียนที่ต้องเยี่ยมบ้าน (${cls})`;
  document.getElementById('missingTableBody').innerHTML = '<tr><td colspan="4" class="text-center" style="padding:3rem;"><span class="spinner"></span> กำลังดึงข้อมูล...</td></tr>';
  openModal('missingModal');

  try {
    const res  = await fetch(`<?= BASE_URL ?>/admin/visit_report?api=missing_by_class&class=${encodeURIComponent(cls)}`);
    const json = await res.json();
    
    if (json.success) {
      renderMissingTable(json.data);
    } else {
      showToast('เกิดข้อผิดพลาดในการดึงข้อมูล', 'error');
    }
  } catch (e) {
    showToast('เกิดข้อผิดพลาด: ' + e.message, 'error');
  }
}

function renderMissingTable(students) {
  const tbody = document.getElementById('missingTableBody');
  if (!students.length) {
    tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:3rem;color:var(--text-muted);">เยี่ยมครบทุกคนแล้ว 🎉</td></tr>';
    return;
  }

  tbody.innerHTML = students.map(s => `
    <tr>
      <td style="padding:.75rem 1rem;color:var(--text-muted);font-size:.82rem;">${s.student_number || '—'}</td>
      <td style="padding:.75rem 1rem;">
        <div style="font-weight:700;font-size:.88rem;">${s.prefix || ''}${s.first_name} ${s.last_name}</div>
        <div style="font-size:.7rem;color:var(--text-muted);">เลขประจำตัว ${s.student_id}</div>
      </td>
      <td style="padding:.75rem 1rem;text-align:center;">
        ${(s.latitude && s.longitude) ? '📍' : '❌'}
      </td>
      <td style="padding:.75rem 1rem;text-align:center;">
        <a href="<?= BASE_URL ?>/admin/visit_log?add_visit=${s.id}" class="btn btn-primary btn-sm btn-icon" title="บันทึกการเยี่ยม">📋</a>
      </td>
    </tr>
  `).join('');
}

if (window.innerWidth <= 768) {
  document.getElementById('sidebar-toggle').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
