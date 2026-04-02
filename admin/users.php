<?php
/**
 * admin/users.php – User Management (Admin only)
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireAdmin(); // เฉพาะ admin เต็มสิทธิ์เท่านั้น

$pdo = getDB();
$myId = (int)$_SESSION['admin_id'];

/* ══════════════════════════════════════════════════════
   API endpoints  (JSON)
══════════════════════════════════════════════════════ */
if (!empty($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api = $_GET['api'];

    /* ── LIST ── */
    if ($api === 'list') {
        $rows = $pdo->query('SELECT id, username, full_name, role, created_at FROM admins ORDER BY id')
                    ->fetchAll();
        echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── ADD ── */
    if ($api === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $username  = trim($_POST['username']  ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role      = trim($_POST['role']      ?? 'admin_editor');
        $password  = trim($_POST['password']  ?? '');
        $confirm   = trim($_POST['confirm']   ?? '');

        if (!$username || !$full_name || !$password) {
            echo json_encode(['success'=>false,'message'=>'กรุณากรอกข้อมูลให้ครบ'], JSON_UNESCAPED_UNICODE); exit;
        }
        if ($password !== $confirm) {
            echo json_encode(['success'=>false,'message'=>'รหัสผ่านไม่ตรงกัน'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (mb_strlen($password) < 6) {
            echo json_encode(['success'=>false,'message'=>'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (!in_array($role, ['admin','admin_editor'], true)) {
            echo json_encode(['success'=>false,'message'=>'สิทธิ์ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE); exit;
        }

        // Check duplicate username
        $chk = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo json_encode(['success'=>false,'message'=>'ชื่อผู้ใช้นี้มีอยู่แล้วในระบบ'], JSON_UNESCAPED_UNICODE); exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password, full_name, role) VALUES (?,?,?,?)');
        $stmt->execute([$username, $hash, $full_name, $role]);
        echo json_encode(['success'=>true,'message'=>'เพิ่มผู้ใช้เรียบร้อยแล้ว','id'=>$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── EDIT (username / full_name / role) ── */
    if ($api === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id        = (int)($_POST['id']        ?? 0);
        $username  = trim($_POST['username']   ?? '');
        $full_name = trim($_POST['full_name']  ?? '');
        $role      = trim($_POST['role']       ?? '');

        if (!$id || !$username || !$full_name) {
            echo json_encode(['success'=>false,'message'=>'ข้อมูลไม่ครบ'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (!in_array($role, ['admin','admin_editor'], true)) {
            echo json_encode(['success'=>false,'message'=>'สิทธิ์ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE); exit;
        }
        // Cannot demote yourself from admin (safety)
        if ($id === $myId && $role !== 'admin') {
            echo json_encode(['success'=>false,'message'=>'ไม่สามารถลดสิทธิ์ตัวเองได้'], JSON_UNESCAPED_UNICODE); exit;
        }

        // Unique username check (exclude self)
        $chk = $pdo->prepare('SELECT id FROM admins WHERE username = ? AND id != ?');
        $chk->execute([$username, $id]);
        if ($chk->fetch()) {
            echo json_encode(['success'=>false,'message'=>'ชื่อผู้ใช้นี้มีอยู่แล้วในระบบ'], JSON_UNESCAPED_UNICODE); exit;
        }

        $pdo->prepare('UPDATE admins SET username=?, full_name=?, role=? WHERE id=?')
            ->execute([$username, $full_name, $role, $id]);
        echo json_encode(['success'=>true,'message'=>'บันทึกข้อมูลเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── CHANGE PASSWORD ── */
    if ($api === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id      = (int)($_POST['id']       ?? 0);
        $newpw   = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm']      ?? '');

        if (!$id || !$newpw) {
            echo json_encode(['success'=>false,'message'=>'กรุณากรอกรหัสผ่านใหม่'], JSON_UNESCAPED_UNICODE); exit;
        }
        if ($newpw !== $confirm) {
            echo json_encode(['success'=>false,'message'=>'รหัสผ่านไม่ตรงกัน'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (mb_strlen($newpw) < 6) {
            echo json_encode(['success'=>false,'message'=>'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'], JSON_UNESCAPED_UNICODE); exit;
        }

        $hash = password_hash($newpw, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE admins SET password=? WHERE id=?')->execute([$hash, $id]);
        echo json_encode(['success'=>true,'message'=>'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── DELETE ── */
    if ($api === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $myId) {
            echo json_encode(['success'=>false,'message'=>'ไม่สามารถลบบัญชีตัวเองได้'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ไม่พบผู้ใช้'], JSON_UNESCAPED_UNICODE); exit;
        }
        $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'ลบผู้ใช้เรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ══════════════════════════════════════════════════════
   Page render
══════════════════════════════════════════════════════ */
$pageTitle  = 'จัดการผู้ใช้งาน';
$activePage = 'users';
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
          <h1 style="font-size:1.1rem;font-weight:700;">จัดการผู้ใช้งาน</h1>
          <p style="font-size:.78rem;color:var(--text-muted);">เพิ่ม แก้ไข ลบ และกำหนดสิทธิ์ผู้ใช้งานระบบ</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn btn-primary btn-sm" onclick="openAddModal()">➕ เพิ่มผู้ใช้</button>
      </div>
    </div>

    <div class="page-content">

      <!-- Role legend -->
      <div class="card mb-4" style="border-left:4px solid #4f46e5;">
        <div class="card-body" style="padding:.9rem 1.25rem;">
          <div class="flex items-center gap-4" style="flex-wrap:wrap;font-size:.85rem;">
            <span style="font-weight:700;color:var(--text-muted);">ระดับสิทธิ์:</span>
            <span><span class="badge" style="background:#ede9fe;color:#4f46e5;border:1px solid #c4b5fd;">👑 Admin</span> — เข้าถึงทุกฟังก์ชัน รวมถึงลบข้อมูลและจัดการผู้ใช้</span>
            <span><span class="badge" style="background:#cffafe;color:#0891b2;border:1px solid #a5f3fc;">✏️ Admin Editor</span> — แก้ไข/เพิ่ม/ปักหมุดได้ ไม่สามารถลบข้อมูลหรือจัดการผู้ใช้ได้</span>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div class="card">
        <div class="card-header">
          <span style="font-weight:700;">รายชื่อผู้ใช้งาน</span>
          <span id="totalBadge" class="badge badge-primary">0 คน</span>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>ชื่อผู้ใช้</th>
                <th>ชื่อ-นามสกุล</th>
                <th>สิทธิ์</th>
                <th>วันที่สร้าง</th>
                <th style="text-align:center;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="userTableBody">
              <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div>

<!-- ═══ MODAL: Add User ═══ -->
<div id="addModal" class="modal-overlay">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">➕ เพิ่มผู้ใช้งาน</span>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="grid-cols-2">
        <div class="form-group">
          <label class="form-label">ชื่อผู้ใช้ (Username) <span style="color:red">*</span></label>
          <input type="text" id="add_username" class="form-control" placeholder="เช่น teacher01" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">ชื่อ-นามสกุล <span style="color:red">*</span></label>
          <input type="text" id="add_fullname" class="form-control" placeholder="ชื่อที่แสดงในระบบ">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">สิทธิ์การใช้งาน</label>
          <select id="add_role" class="form-control form-select">
            <option value="admin_editor">✏️ Admin Editor (แก้ไขได้ ลบไม่ได้)</option>
            <option value="admin">👑 Admin (สิทธิ์เต็ม)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">รหัสผ่าน <span style="color:red">*</span></label>
          <input type="password" id="add_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label class="form-label">ยืนยันรหัสผ่าน <span style="color:red">*</span></label>
          <input type="password" id="add_confirm" class="form-control" placeholder="กรอกรหัสผ่านอีกครั้ง" autocomplete="new-password">
        </div>
      </div>
      <div id="add_error" class="alert alert-danger" style="display:none;margin-top:.5rem;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('addModal')">ยกเลิก</button>
      <button class="btn btn-primary" onclick="doAdd()">💾 เพิ่มผู้ใช้</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Edit User ═══ -->
<div id="editModal" class="modal-overlay">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">✏️ แก้ไขข้อมูลผู้ใช้</span>
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit_id">
      <div class="grid-cols-2">
        <div class="form-group">
          <label class="form-label">ชื่อผู้ใช้ (Username) <span style="color:red">*</span></label>
          <input type="text" id="edit_username" class="form-control" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">ชื่อ-นามสกุล <span style="color:red">*</span></label>
          <input type="text" id="edit_fullname" class="form-control">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">สิทธิ์การใช้งาน</label>
          <select id="edit_role" class="form-control form-select">
            <option value="admin_editor">✏️ Admin Editor (แก้ไขได้ ลบไม่ได้)</option>
            <option value="admin">👑 Admin (สิทธิ์เต็ม)</option>
          </select>
          <small class="text-muted" id="edit_demote_warn" style="display:none;color:#d97706;">⚠️ ไม่สามารถลดสิทธิ์ตัวเองได้</small>
        </div>
      </div>
      <div id="edit_error" class="alert alert-danger" style="display:none;margin-top:.5rem;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('editModal')">ยกเลิก</button>
      <button class="btn btn-primary" onclick="doEdit()">💾 บันทึก</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Change Password ═══ -->
<div id="pwModal" class="modal-overlay">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <span class="modal-title">🔑 เปลี่ยนรหัสผ่าน</span>
      <button class="modal-close" onclick="closeModal('pwModal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="pw_id">
      <div class="form-group">
        <label class="form-label">ผู้ใช้งาน</label>
        <div id="pw_user_name" style="font-weight:700;padding:.5rem 0;font-size:.95rem;"></div>
      </div>
      <div class="form-group">
        <label class="form-label">รหัสผ่านใหม่ <span style="color:red">*</span></label>
        <input type="password" id="pw_new" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">ยืนยันรหัสผ่านใหม่ <span style="color:red">*</span></label>
        <input type="password" id="pw_confirm" class="form-control" placeholder="กรอกอีกครั้ง" autocomplete="new-password">
      </div>
      <div id="pw_error" class="alert alert-danger" style="display:none;margin-top:.5rem;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('pwModal')">ยกเลิก</button>
      <button class="btn btn-warning" onclick="doChangePassword()">🔑 เปลี่ยนรหัสผ่าน</button>
    </div>
  </div>
</div>

<script>
'use strict';
const API    = '<?= BASE_URL ?>/admin/users?api=';
const MY_ID  = <?= $myId ?>;

/* ────── Load users ────── */
async function loadUsers() {
  const res  = await fetch(API + 'list');
  const json = await res.json();
  if (!json.success) { showToast('โหลดข้อมูลไม่สำเร็จ','error'); return; }

  document.getElementById('totalBadge').textContent = json.data.length + ' คน';
  const tbody = document.getElementById('userTableBody');

  if (!json.data.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">ไม่พบข้อมูล</td></tr>';
    return;
  }

  tbody.innerHTML = json.data.map((u, i) => {
    const isMe   = u.id == MY_ID;
    const roleTag = u.role === 'admin'
      ? `<span class="badge" style="background:#ede9fe;color:#4f46e5;border:1px solid #c4b5fd;">👑 Admin</span>`
      : `<span class="badge" style="background:#cffafe;color:#0891b2;border:1px solid #a5f3fc;">✏️ Admin Editor</span>`;

    const meBadge = isMe ? `<span class="badge badge-success" style="margin-left:.35rem;">ฉัน</span>` : '';

    return `<tr>
      <td style="color:var(--text-muted);font-size:.8rem;">${i+1}</td>
      <td><code>${escHtml(u.username)}</code>${meBadge}</td>
      <td style="font-weight:600;">${escHtml(u.full_name)}</td>
      <td>${roleTag}</td>
      <td style="color:var(--text-muted);font-size:.82rem;">${u.created_at ? u.created_at.substring(0,10) : '—'}</td>
      <td style="text-align:center;white-space:nowrap;">
        <button class="btn btn-primary btn-sm btn-icon" title="แก้ไขข้อมูล" onclick='openEditModal(${JSON.stringify(u)})'>✏️</button>
        <button class="btn btn-warning btn-sm btn-icon" title="เปลี่ยนรหัสผ่าน" onclick='openPwModal(${JSON.stringify(u)})'>🔑</button>
        ${!isMe
          ? `<button class="btn btn-danger btn-sm btn-icon" title="ลบผู้ใช้" onclick="deleteUser(${u.id},'${escHtml(u.username)}')">🗑️</button>`
          : `<button class="btn btn-light btn-sm btn-icon" disabled title="ไม่สามารถลบตัวเองได้">🗑️</button>`}
      </td>
    </tr>`;
  }).join('');
}

/* ────── Add User ────── */
function openAddModal() {
  document.getElementById('add_username').value = '';
  document.getElementById('add_fullname').value = '';
  document.getElementById('add_password').value = '';
  document.getElementById('add_confirm').value  = '';
  document.getElementById('add_role').value     = 'admin_editor';
  document.getElementById('add_error').style.display = 'none';
  openModal('addModal');
}

async function doAdd() {
  const username  = document.getElementById('add_username').value.trim();
  const full_name = document.getElementById('add_fullname').value.trim();
  const role      = document.getElementById('add_role').value;
  const password  = document.getElementById('add_password').value.trim();
  const confirm   = document.getElementById('add_confirm').value.trim();

  if (!username || !full_name || !password) return showAddError('กรุณากรอกข้อมูลให้ครบ');
  if (password !== confirm) return showAddError('รหัสผ่านไม่ตรงกัน');
  if (password.length < 6) return showAddError('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');

  document.getElementById('add_error').style.display = 'none';
  const fd = new FormData();
  fd.set('username', username); fd.set('full_name', full_name);
  fd.set('role', role); fd.set('password', password); fd.set('confirm', confirm);

  const res  = await fetch(API + 'add', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) { showToast(json.message,'success'); closeModal('addModal'); loadUsers(); }
  else showAddError(json.message);
}

function showAddError(msg) {
  const el = document.getElementById('add_error');
  el.textContent = '⚠️ ' + msg; el.style.display = 'block';
}

/* ────── Edit User ────── */
let editingId = null;

function openEditModal(u) {
  editingId = u.id;
  document.getElementById('edit_id').value       = u.id;
  document.getElementById('edit_username').value = u.username;
  document.getElementById('edit_fullname').value = u.full_name;
  document.getElementById('edit_role').value     = u.role;
  document.getElementById('edit_error').style.display = 'none';

  const isMe = u.id == MY_ID;
  document.getElementById('edit_demote_warn').style.display = isMe ? 'block' : 'none';
  if (isMe) {
    document.getElementById('edit_role').disabled = true;
  } else {
    document.getElementById('edit_role').disabled = false;
  }
  openModal('editModal');
}

async function doEdit() {
  const id        = document.getElementById('edit_id').value;
  const username  = document.getElementById('edit_username').value.trim();
  const full_name = document.getElementById('edit_fullname').value.trim();
  const role      = document.getElementById('edit_role').value;

  if (!username || !full_name) return showEditError('กรุณากรอกข้อมูลให้ครบ');

  document.getElementById('edit_error').style.display = 'none';
  const fd = new FormData();
  fd.set('id', id); fd.set('username', username);
  fd.set('full_name', full_name); fd.set('role', role);

  const res  = await fetch(API + 'edit', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) { showToast(json.message,'success'); closeModal('editModal'); loadUsers(); }
  else showEditError(json.message);
}

function showEditError(msg) {
  const el = document.getElementById('edit_error');
  el.textContent = '⚠️ ' + msg; el.style.display = 'block';
}

/* ────── Change Password ────── */
function openPwModal(u) {
  document.getElementById('pw_id').value = u.id;
  document.getElementById('pw_user_name').textContent = u.username + ' — ' + u.full_name;
  document.getElementById('pw_new').value     = '';
  document.getElementById('pw_confirm').value = '';
  document.getElementById('pw_error').style.display = 'none';
  openModal('pwModal');
}

async function doChangePassword() {
  const id      = document.getElementById('pw_id').value;
  const newpw   = document.getElementById('pw_new').value.trim();
  const confirm = document.getElementById('pw_confirm').value.trim();

  if (!newpw) return showPwError('กรุณากรอกรหัสผ่านใหม่');
  if (newpw !== confirm) return showPwError('รหัสผ่านไม่ตรงกัน');
  if (newpw.length < 6) return showPwError('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');

  document.getElementById('pw_error').style.display = 'none';
  const fd = new FormData();
  fd.set('id', id); fd.set('new_password', newpw); fd.set('confirm', confirm);

  const res  = await fetch(API + 'change_password', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) { showToast(json.message,'success'); closeModal('pwModal'); }
  else showPwError(json.message);
}

function showPwError(msg) {
  const el = document.getElementById('pw_error');
  el.textContent = '⚠️ ' + msg; el.style.display = 'block';
}

/* ────── Delete User ────── */
function deleteUser(id, username) {
  confirmDialog(`ต้องการลบผู้ใช้ "${username}" ออกจากระบบ?`, async () => {
    const fd = new FormData(); fd.set('id', id);
    const res  = await fetch(API + 'delete', { method:'POST', body:fd });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadUsers();
  });
}

/* ────── Utility ────── */
function escHtml(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Sidebar on mobile
if (window.innerWidth <= 768) {
  document.getElementById('sidebar-toggle').style.display = 'flex';
}

loadUsers();
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
