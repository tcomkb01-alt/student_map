<?php
/**
 * admin/visit_log.php – Home Visit Log Management
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$pdo       = getDB();
$adminId   = (int)$_SESSION['admin_id'];
$canDelete = canDelete();

/* ══════════════════════════════════════════════════════
   API endpoints
══════════════════════════════════════════════════════ */
if (!empty($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api = $_GET['api'];

    /* ── LIST ── */
    if ($api === 'list') {
        $class  = $_GET['class']  ?? '';
        $search = $_GET['search'] ?? '';
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where  = [];
        $params = [];
        if ($class) {
            $where[] = 's.class = ?';
            $params[] = $class;
        }
        if ($search) {
            $where[] = '(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM visit_logs vl
                     JOIN students s ON vl.student_id = s.id
                     $whereSql";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT vl.*, s.prefix, s.first_name, s.last_name, s.class,
                       s.student_number, s.student_id AS s_student_id, s.profile_image,
                       a.full_name AS visitor_name
                FROM visit_logs vl
                JOIN students s ON vl.student_id = s.id
                LEFT JOIN admins a ON vl.admin_id = a.id
                $whereSql
                ORDER BY vl.visit_date DESC, vl.id DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data'    => $rows,
            'total'   => $total,
            'pages'   => ceil($total / $limit),
            'page'    => $page,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── GET single ── */
    if ($api === 'get') {
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT vl.*, s.prefix, s.first_name, s.last_name, s.class, s.student_number, s.student_id AS s_student_id
             FROM visit_logs vl JOIN students s ON vl.student_id = s.id
             WHERE vl.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        echo json_encode(['success' => (bool)$row, 'data' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── SEARCH STUDENTS (for autocomplete) ── */
    if ($api === 'search_students') {
        $q    = '%' . trim($_GET['q'] ?? '') . '%';
        $rows = $pdo->prepare(
            'SELECT id, student_id, prefix, first_name, last_name, class, student_number
             FROM students
             WHERE student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?
             ORDER BY class, student_number LIMIT 20'
        );
        $rows->execute([$q, $q, $q]);
        echo json_encode(['success' => true, 'data' => $rows->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── SAVE (add / edit) ── */
    if ($api === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id         = (int)($_POST['id']         ?? 0);
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $visitDate  = trim($_POST['visit_date']  ?? '');
        $visitTime  = trim($_POST['visit_time']  ?? '') ?: null;
        $result     = trim($_POST['result']      ?? 'home_found');
        $note       = trim($_POST['note']        ?? '');
        $followUp   = trim($_POST['follow_up']   ?? '');

        if (!$studentId || !$visitDate) {
            echo json_encode(['success' => false, 'message' => 'กรุณาระบุนักเรียนและวันที่เยี่ยม'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $allowedResults = ['home_found', 'not_home', 'moved', 'other'];
        if (!in_array($result, $allowedResults, true)) {
            echo json_encode(['success' => false, 'message' => 'ผลการเยี่ยมไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Photo upload
        $photoFilename = null;
        if ($id) {
            $photoFilename = $pdo->prepare('SELECT photo FROM visit_logs WHERE id=?');
            $photoFilename->execute([$id]);
            $photoFilename = $photoFilename->fetchColumn();
        }

        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= MAX_FILE_SIZE) {
                if (in_array($file['type'], ALLOWED_TYPES)) {
                    $visitDir = UPLOAD_DIR . 'visits/';
                    if (!is_dir($visitDir)) mkdir($visitDir, 0775, true);

                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $name = 'visit_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $visitDir . $name)) {
                        // Delete old photo
                        if ($photoFilename && file_exists($visitDir . $photoFilename)) {
                            @unlink($visitDir . $photoFilename);
                        }
                        $photoFilename = $name;
                    }
                }
            }
        }

        if ($id) {
            $pdo->prepare('UPDATE visit_logs SET student_id=?, admin_id=?, visit_date=?, visit_time=?, result=?, note=?, follow_up=?, photo=? WHERE id=?')
                ->execute([$studentId, $adminId, $visitDate, $visitTime, $result, $note, $followUp, $photoFilename, $id]);
            echo json_encode(['success' => true, 'message' => 'แก้ไขบันทึกเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
        } else {
            $pdo->prepare('INSERT INTO visit_logs (student_id, admin_id, visit_date, visit_time, result, note, follow_up, photo) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$studentId, $adminId, $visitDate, $visitTime, $result, $note, $followUp, $photoFilename]);
            echo json_encode(['success' => true, 'message' => 'บันทึกการเยี่ยมบ้านสำเร็จ', 'id' => $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /* ── DELETE ── */
    if ($api === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!canDelete()) {
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ลบข้อมูล'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล'], JSON_UNESCAPED_UNICODE); exit; }

        $row = $pdo->prepare('SELECT photo FROM visit_logs WHERE id=?');
        $row->execute([$id]);
        $photo = $row->fetchColumn();
        if ($photo && file_exists(UPLOAD_DIR . 'visits/' . $photo)) {
            @unlink(UPLOAD_DIR . 'visits/' . $photo);
        }
        $pdo->prepare('DELETE FROM visit_logs WHERE id=?')->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'ลบบันทึกเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── CLASS LIST ── */
    if ($api === 'classes') {
        $rows = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ══════════════════════════════════════════════════════
   Page render
══════════════════════════════════════════════════════ */
$classList  = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle  = 'บันทึกการเยี่ยมบ้าน';
$activePage = 'apps';
require_once __DIR__ . '/../components/header.php';
?>

<div class="layout">
  <?php require_once __DIR__ . '/../components/navbar.php'; ?>

  <div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
      <div class="flex items-center gap-3">
        <button id="sidebar-toggle" class="btn btn-light btn-icon" style="display:none;" aria-label="Toggle menu">☰</button>
        <div>
          <a href="<?= BASE_URL ?>/admin/apps" style="font-size:.78rem;color:var(--text-muted);text-decoration:none;">🧩 App Center</a>
          <h1 style="font-size:1.1rem;font-weight:700;">📋 บันทึกการเยี่ยมบ้าน</h1>
          <p style="font-size:.78rem;color:var(--text-muted);">เพิ่มและดูประวัติการเยี่ยมบ้านนักเรียน</p>
        </div>
      </div>
      <div>
        <button class="btn btn-primary btn-sm" onclick="openVisitModal(0)">➕ บันทึกการเยี่ยม</button>
      </div>
    </div>

    <div class="page-content">

      <!-- Filter bar -->
      <div class="card mb-4">
        <div class="card-body" style="padding:1rem 1.5rem;">
          <div class="flex items-center gap-3" style="flex-wrap:wrap;">
            <select id="filterClass" class="form-control form-select" style="width:auto;min-width:150px;">
              <option value="">— ทุกห้องเรียน —</option>
              <?php foreach ($classList as $cls): ?>
                <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="search-box" style="flex:1;min-width:200px;">
              <span class="search-icon">🔍</span>
              <input id="searchInput" type="text" class="form-control" placeholder="ค้นหาชื่อ / เลขประจำตัว...">
            </div>
            <button class="btn btn-light btn-sm" onclick="loadVisits(1)">🔄 รีเฟรช</button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="card">
        <div class="card-header">
          <span style="font-weight:700;">ประวัติการเยี่ยมบ้าน</span>
          <span id="totalBadge" class="badge badge-primary">0 รายการ</span>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>วันที่เยี่ยม</th>
                <th>นักเรียน</th>
                <th>ชั้น</th>
                <th>ผลการเยี่ยม</th>
                <th>ผู้เยี่ยม</th>
                <th>บันทึก</th>
                <th style="text-align:center;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="visitTableBody">
              <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border);">
          <div id="pagination" class="pagination"></div>
        </div>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div>

<!-- ═══ MODAL: Add / Edit Visit ═══ -->
<div id="visitModal" class="modal-overlay">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header">
      <span class="modal-title" id="visitModalTitle">➕ บันทึกการเยี่ยมบ้าน</span>
      <button class="modal-close" onclick="closeModal('visitModal')">✕</button>
    </div>
    <form id="visitForm" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" id="v_id" name="id">
        <input type="hidden" id="v_student_id" name="student_id">

        <!-- Student search -->
        <div class="form-group" id="studentSearchGroup">
          <label class="form-label">🔍 ค้นหานักเรียน <span style="color:red">*</span></label>
          <input type="text" id="studentSearch" class="form-control" placeholder="พิมพ์ชื่อหรือเลขประจำตัวนักเรียน...">
          <div id="studentDropdown" style="position:relative;"></div>
        </div>

        <!-- Selected student info -->
        <div id="selectedStudentBox" style="display:none;padding:.75rem 1rem;background:#f8fafc;border-radius:.75rem;margin-bottom:1rem;border:1.5px solid #e0e7ff;">
          <div style="display:flex;align-items:center;gap:.75rem;">
            <div style="font-size:1.75rem;">👨‍🎓</div>
            <div>
              <div id="selectedStudentName" style="font-weight:700;font-size:.95rem;"></div>
              <div id="selectedStudentClass" style="font-size:.78rem;color:var(--text-muted);"></div>
            </div>
            <button type="button" onclick="clearSelectedStudent()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.1rem;" title="เปลี่ยนนักเรียน">✕</button>
          </div>
        </div>

        <div class="grid-cols-2">
          <!-- Visit date -->
          <div class="form-group">
            <label class="form-label">📅 วันที่เยี่ยมบ้าน <span style="color:red">*</span></label>
            <input type="date" name="visit_date" id="v_date" class="form-control">
          </div>
          <!-- Visit time -->
          <div class="form-group">
            <label class="form-label">🕐 เวลา (ไม่บังคับ)</label>
            <input type="time" name="visit_time" id="v_time" class="form-control">
          </div>
          <!-- Result -->
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label">📝 ผลการเยี่ยมบ้าน <span style="color:red">*</span></label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
              <label class="result-option" data-val="home_found">
                <input type="radio" name="result" value="home_found" checked style="display:none;">
                <span class="result-label">✅ พบที่บ้าน</span>
              </label>
              <label class="result-option" data-val="not_home">
                <input type="radio" name="result" value="not_home" style="display:none;">
                <span class="result-label">🚪 ไม่พบที่บ้าน</span>
              </label>
              <label class="result-option" data-val="moved">
                <input type="radio" name="result" value="moved" style="display:none;">
                <span class="result-label">📦 ย้ายที่อยู่แล้ว</span>
              </label>
              <label class="result-option" data-val="other">
                <input type="radio" name="result" value="other" style="display:none;">
                <span class="result-label">📌 อื่นๆ</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Note -->
        <div class="form-group">
          <label class="form-label">📄 บันทึกการเยี่ยม / สิ่งที่พบ</label>
          <textarea name="note" id="v_note" class="form-control" rows="3" placeholder="เช่น สภาพบ้าน ผู้ที่พบ สิ่งที่สังเกต..."></textarea>
        </div>

        <!-- Follow-up -->
        <div class="form-group">
          <label class="form-label">🔄 การติดตามผล (ถ้ามี)</label>
          <textarea name="follow_up" id="v_followup" class="form-control" rows="2" placeholder="เช่น นัดเยี่ยมครั้งต่อไป แจ้งผู้ปกครอง..."></textarea>
        </div>

        <!-- Photo -->
        <div class="form-group">
          <label class="form-label">📸 รูปถ่าย (ไม่บังคับ)</label>
          <input type="file" name="photo" id="v_photo" class="form-control" accept="image/*">
          <div id="photoPreviewBox" style="margin-top:.5rem;display:none;">
            <img id="photoPreview" style="max-height:160px;border-radius:.75rem;object-fit:cover;" src="" alt="">
          </div>
        </div>

      </div><!-- /modal-body -->
      <div class="modal-footer">
        <button type="button" class="btn btn-light" onclick="closeModal('visitModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" id="saveVisitBtn">💾 บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: View Visit Detail ═══ -->
<div id="detailModal" class="modal-overlay">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <span class="modal-title">📋 รายละเอียดการเยี่ยมบ้าน</span>
      <button class="modal-close" onclick="closeModal('detailModal')">✕</button>
    </div>
    <div class="modal-body" id="detailContent"></div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('detailModal')">ปิด</button>
    </div>
  </div>
</div>

<style>
.result-option {
  cursor: pointer;
  border: 2px solid #e2e8f0;
  border-radius: .75rem;
  padding: .6rem 1rem;
  transition: border-color .15s, background .15s;
  display: flex;
  align-items: center;
}
.result-option:has(input:checked) {
  border-color: #4f46e5;
  background: #ede9fe;
}
.result-label {
  font-size: .88rem;
  font-weight: 600;
  pointer-events: none;
}
.student-option {
  padding: .6rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  font-size: .88rem;
  transition: background .1s;
}
.student-option:hover { background: #f8fafc; }
.student-option:last-child { border-bottom: none; }
#studentDropdownList {
  position: absolute;
  left: 0; right: 0; top: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: .75rem;
  box-shadow: 0 8px 24px rgba(0,0,0,.12);
  z-index: 1000;
  max-height: 280px;
  overflow-y: auto;
}
.result-badge {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .2rem .6rem; border-radius: 99px; font-size: .75rem; font-weight: 600;
}
.result-badge.home_found  { background:#d1fae5; color:#065f46; }
.result-badge.not_home    { background:#fef3c7; color:#92400e; }
.result-badge.moved       { background:#fee2e2; color:#991b1b; }
.result-badge.other       { background:#e0e7ff; color:#3730a3; }
</style>

<script>
'use strict';

const API        = '<?= BASE_URL ?>/admin/visit_log?api=';
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
const UPLOAD_URL = '<?= BASE_URL ?>/uploads/students/';
const VISIT_URL  = '<?= BASE_URL ?>/uploads/visits/';
let currentPage  = 1;

const RESULT_LABELS = {
  home_found : '✅ พบที่บ้าน',
  not_home   : '🚪 ไม่พบที่บ้าน',
  moved      : '📦 ย้ายที่อยู่',
  other      : '📌 อื่นๆ',
};

/* ── Load visits ── */
async function loadVisits(page = 1) {
  currentPage = page;
  const cls    = document.getElementById('filterClass').value;
  const search = document.getElementById('searchInput').value.trim();
  const url    = `${API}list&page=${page}&class=${encodeURIComponent(cls)}&search=${encodeURIComponent(search)}`;

  document.getElementById('visitTableBody').innerHTML =
    '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">กำลังโหลด...</td></tr>';

  try {
    const res  = await fetch(url);
    const json = await res.json();
    if (!json.success) { showToast('โหลดข้อมูลไม่สำเร็จ', 'error'); return; }
    document.getElementById('totalBadge').textContent = `${json.total} รายการ`;
    renderTable(json.data);
    renderPagination(json.pages, json.page);
  } catch (e) {
    showToast('เกิดข้อผิดพลาด: ' + e.message, 'error');
  }
}

function resultBadge(r) {
  return `<span class="result-badge ${r}">${RESULT_LABELS[r] || r}</span>`;
}

function renderTable(rows) {
  const tbody = document.getElementById('visitTableBody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">ยังไม่มีบันทึกการเยี่ยมบ้าน</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map((v, i) => {
    const img = v.profile_image
      ? `<img src="${UPLOAD_URL}${v.profile_image}" class="avatar avatar-sm" style="margin-right:.4rem;">`
      : `<span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:#ede9fe;color:#4f46e5;font-weight:700;align-items:center;justify-content:center;font-size:.72rem;margin-right:.4rem;">${(v.first_name||'?')[0]}</span>`;

    const deleteBtn = CAN_DELETE
      ? `<button class="btn btn-danger btn-sm btn-icon" title="ลบ" onclick="deleteVisit(${v.id})">🗑️</button>`
      : '';

    const notePreview = v.note ? v.note.substring(0, 30) + (v.note.length > 30 ? '...' : '') : '—';

    return `<tr>
      <td style="color:var(--text-muted);font-size:.8rem;">${(currentPage-1)*20 + i+1}</td>
      <td style="font-size:.88rem;font-weight:600;white-space:nowrap;">
        ${v.visit_date}
        ${v.visit_time ? `<div style="font-size:.75rem;color:var(--text-muted);">${v.visit_time.substring(0,5)} น.</div>` : ''}
      </td>
      <td>
        <div style="display:flex;align-items:center;">
          ${img}
          <div>
            <div style="font-weight:600;font-size:.88rem;">${esc(v.prefix)} ${esc(v.first_name)} ${esc(v.last_name)}</div>
            <div style="font-size:.75rem;color:var(--text-muted);">เลข ${esc(v.s_student_id)}</div>
          </div>
        </div>
      </td>
      <td><span class="badge badge-primary">${esc(v.class)}</span></td>
      <td>${resultBadge(v.result)}</td>
      <td style="font-size:.82rem;">${esc(v.visitor_name || '—')}</td>
      <td style="font-size:.82rem;color:var(--text-muted);">${esc(notePreview)}</td>
      <td style="text-align:center;white-space:nowrap;">
        <button class="btn btn-light btn-sm btn-icon" title="ดูรายละเอียด" onclick="viewDetail(${v.id})">👁️</button>
        <button class="btn btn-primary btn-sm btn-icon" title="แก้ไข" onclick="openVisitModal(${v.id})">✏️</button>
        ${deleteBtn}
      </td>
    </tr>`;
  }).join('');
}

function renderPagination(pages, current) {
  const el = document.getElementById('pagination');
  if (pages <= 1) { el.innerHTML = ''; return; }
  let html = `<div class="page-item ${current===1?'disabled':''}" onclick="loadVisits(${current-1})">‹</div>`;
  for (let i = 1; i <= pages; i++) {
    if (i === 1 || i === pages || Math.abs(i - current) <= 2)
      html += `<div class="page-item ${i===current?'active':''}" onclick="loadVisits(${i})">${i}</div>`;
    else if (Math.abs(i - current) === 3)
      html += `<div class="page-item disabled">…</div>`;
  }
  html += `<div class="page-item ${current===pages?'disabled':''}" onclick="loadVisits(${current+1})">›</div>`;
  el.innerHTML = html;
}

/* ── Visit Modal ── */
let selectedStudent = null;

async function openVisitModal(id) {
  document.getElementById('visitForm').reset();
  document.getElementById('v_id').value = '';
  document.getElementById('photoPreviewBox').style.display = 'none';
  clearSelectedStudent();

  // Set today as default date
  if (!id) {
    document.getElementById('v_date').value = new Date().toISOString().substring(0, 10);
  }
  setResultOption('home_found');

  if (id) {
    document.getElementById('visitModalTitle').textContent = '✏️ แก้ไขบันทึกการเยี่ยม';
    const res  = await fetch(`${API}get&id=${id}`);
    const json = await res.json();
    if (!json.success) { showToast('โหลดข้อมูลไม่สำเร็จ', 'error'); return; }
    const v = json.data;

    document.getElementById('v_id').value      = v.id;
    document.getElementById('v_date').value    = v.visit_date;
    document.getElementById('v_time').value    = v.visit_time ? v.visit_time.substring(0, 5) : '';
    document.getElementById('v_note').value    = v.note     || '';
    document.getElementById('v_followup').value= v.follow_up|| '';
    setResultOption(v.result);

    // Show selected student (read-only mode)
    displaySelectedStudent({ id: v.student_id, prefix: v.prefix, first_name: v.first_name, last_name: v.last_name, class: v.class, student_number: v.student_number, s_student_id: v.s_student_id });
    document.getElementById('studentSearchGroup').style.display = 'none';

    if (v.photo) {
      document.getElementById('photoPreview').src = VISIT_URL + v.photo;
      document.getElementById('photoPreviewBox').style.display = 'block';
    }
  } else {
    document.getElementById('visitModalTitle').textContent = '➕ บันทึกการเยี่ยมบ้าน';
    document.getElementById('studentSearchGroup').style.display = 'block';
  }

  openModal('visitModal');
}

function setResultOption(val) {
  document.querySelectorAll('.result-option input[name="result"]').forEach(r => {
    r.checked = (r.value === val);
    r.closest('.result-option').style.borderColor = (r.value === val) ? '#4f46e5' : '#e2e8f0';
    r.closest('.result-option').style.background  = (r.value === val) ? '#ede9fe' : '';
  });
}

document.querySelectorAll('.result-option').forEach(el => {
  el.addEventListener('click', () => {
    const val = el.dataset.val;
    document.querySelector(`.result-option input[value="${val}"]`).checked = true;
    setResultOption(val);
  });
});

function clearSelectedStudent() {
  selectedStudent = null;
  document.getElementById('v_student_id').value = '';
  document.getElementById('selectedStudentBox').style.display = 'none';
  document.getElementById('studentSearch').value = '';
  document.getElementById('studentSearchGroup').style.display = 'block';
  removeDropdown();
}

function displaySelectedStudent(s) {
  selectedStudent = s;
  document.getElementById('v_student_id').value = s.id;
  document.getElementById('selectedStudentName').textContent =
    `${s.prefix || ''} ${s.first_name} ${s.last_name}`;
  document.getElementById('selectedStudentClass').textContent =
    `ชั้น ${s.class} · เลขที่ ${s.student_number} · เลขประจำตัว ${s.s_student_id || s.student_id || ''}`;
  document.getElementById('selectedStudentBox').style.display = 'block';
  document.getElementById('studentSearchGroup').style.display = 'none';
  removeDropdown();
}

// Student search autocomplete
let searchTimeout;
document.getElementById('studentSearch').addEventListener('input', function () {
  clearTimeout(searchTimeout);
  const q = this.value.trim();
  if (!q) { removeDropdown(); return; }
  searchTimeout = setTimeout(() => searchStudents(q), 300);
});

async function searchStudents(q) {
  const res  = await fetch(`${API}search_students&q=${encodeURIComponent(q)}`);
  const json = await res.json();
  removeDropdown();
  if (!json.data.length) return;

  const list = document.createElement('div');
  list.id = 'studentDropdownList';
  json.data.forEach(s => {
    const item = document.createElement('div');
    item.className = 'student-option';
    item.innerHTML = `<strong>${esc(s.prefix)} ${esc(s.first_name)} ${esc(s.last_name)}</strong>
      <span style="color:var(--text-muted);font-size:.78rem;margin-left:.5rem;">ชั้น ${esc(s.class)} · เลข ${esc(s.student_id)}</span>`;
    item.addEventListener('click', () => displaySelectedStudent(s));
    list.appendChild(item);
  });
  document.getElementById('studentDropdown').appendChild(list);
}

function removeDropdown() {
  const el = document.getElementById('studentDropdownList');
  if (el) el.remove();
}

document.addEventListener('click', e => {
  if (!e.target.closest('#studentDropdown') && !e.target.closest('#studentSearch')) removeDropdown();
});

// Photo preview
document.getElementById('v_photo').addEventListener('change', function () {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('photoPreview').src = e.target.result;
    document.getElementById('photoPreviewBox').style.display = 'block';
  };
  reader.readAsDataURL(file);
});

// Submit
document.getElementById('visitForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const sid = document.getElementById('v_student_id').value;
  if (!sid) { showToast('กรุณาเลือกนักเรียนก่อน', 'warning'); return; }

  const btn = document.getElementById('saveVisitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> กำลังบันทึก...';

  const fd = new FormData(this);
  try {
    const res  = await fetch(`${API}save`, { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      closeModal('visitModal');
      loadVisits(currentPage);
    } else {
      showToast(json.message || 'เกิดข้อผิดพลาด', 'error');
    }
  } catch (err) {
    showToast('เกิดข้อผิดพลาด: ' + err.message, 'error');
  }
  btn.disabled = false;
  btn.innerHTML = '💾 บันทึก';
});

/* ── View Detail ── */
async function viewDetail(id) {
  const res  = await fetch(`${API}get&id=${id}`);
  const json = await res.json();
  if (!json.success) return;
  const v = json.data;

  document.getElementById('detailContent').innerHTML = `
    <div style="background:#f8fafc;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1rem;">
      <div style="font-weight:700;font-size:1.05rem;">${esc(v.prefix)} ${esc(v.first_name)} ${esc(v.last_name)}</div>
      <div style="font-size:.82rem;color:var(--text-muted);">ชั้น ${esc(v.class)} · เลขที่ ${esc(v.student_number)}</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
      <div><div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.25rem;">วันที่เยี่ยม</div>
           <div style="font-weight:600;">${v.visit_date}${v.visit_time ? ' · ' + v.visit_time.substring(0,5) + ' น.' : ''}</div></div>
      <div><div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.25rem;">ผลการเยี่ยม</div>
           <div>${resultBadge(v.result)}</div></div>
      <div style="grid-column:1/-1;"><div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.25rem;">ผู้เยี่ยม</div>
           <div style="font-weight:600;">${esc(v.visitor_name || '—')}</div></div>
    </div>
    ${v.note ? `<div style="margin-bottom:.75rem;"><div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.35rem;">บันทึกการเยี่ยม</div>
      <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.6rem;padding:.75rem;font-size:.88rem;line-height:1.6;">${esc(v.note)}</div></div>` : ''}
    ${v.follow_up ? `<div style="margin-bottom:.75rem;"><div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.35rem;">การติดตามผล</div>
      <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.6rem;padding:.75rem;font-size:.88rem;line-height:1.6;">${esc(v.follow_up)}</div></div>` : ''}
    ${v.photo ? `<div><div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.5rem;">รูปถ่าย</div>
      <img src="${VISIT_URL}${v.photo}" style="max-width:100%;border-radius:.75rem;object-fit:cover;max-height:260px;"></div>` : ''}
  `;
  openModal('detailModal');
}

/* ── Delete ── */
function deleteVisit(id) {
  confirmDialog('ต้องการลบบันทึกนี้?', async () => {
    const fd = new FormData(); fd.set('id', id);
    const res  = await fetch(`${API}delete`, { method: 'POST', body: fd });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadVisits(currentPage);
  });
}

/* ── Util ── */
function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Init ── */
document.getElementById('filterClass').addEventListener('change', () => loadVisits(1));
document.getElementById('searchInput').addEventListener('input',  () => loadVisits(1));
loadVisits(1);

// Auto-open modal if student_id is provided in URL
window.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const addStudentId = urlParams.get('add_visit');
  if (addStudentId) {
    // Small delay to ensure everything is ready
    setTimeout(async () => {
      // We need student info to display it
      const res = await fetch(`<?= BASE_URL ?>/admin/students?api=get&id=${addStudentId}`);
      const json = await res.json();
      if (json.success) {
        openVisitModal(0);
        const s = json.data;
        displaySelectedStudent({ 
          id: s.id, 
          prefix: s.prefix, 
          first_name: s.first_name, 
          last_name: s.last_name, 
          class: s.class, 
          student_number: s.student_number, 
          s_student_id: s.student_id 
        });
      }
    }, 500);
  }
});

if (window.innerWidth <= 768) document.getElementById('sidebar-toggle').style.display = 'flex';
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
