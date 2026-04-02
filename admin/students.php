<?php
/**
 * students.php – Student Management (CRUD + CSV Import + Location Setting)
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$pdo       = getDB();
$canDelete = canDelete(); // true = admin, false = admin_editor

/* ══════════════════════════════════════════════════════════════════
   AJAX / API handlers  (return JSON)
══════════════════════════════════════════════════════════════════ */
if (!empty($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['api'];

    /* ── LIST ─────────────────────────────────────────────────── */
    if ($action === 'list') {
        $class  = $_GET['class']  ?? '';
        $search = $_GET['search'] ?? '';
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where  = [];
        $params = [];
        if ($class)  { $where[] = 'class = ?';  $params[] = $class; }
        if ($search) {
            $where[] = '(student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }
        $sql = 'SELECT * FROM students';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY class, student_number, id';

        $countStmt = $pdo->prepare(str_replace('SELECT *', 'SELECT COUNT(*)', $sql));
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql .= " LIMIT $limit OFFSET $offset";
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

    /* ── GET single ──────────────────────────────────────────── */
    if ($action === 'get') {
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        echo json_encode(['success' => (bool)$row, 'data' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── SAVE (add / edit) ────────────────────────────────────── */
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id             = (int)($_POST['id'] ?? 0);
        $student_number = (int)($_POST['student_number'] ?? 0);
        $student_id     = trim($_POST['student_id']   ?? '');
        $prefix         = trim($_POST['prefix']        ?? 'เด็กชาย');
        $first_name     = trim($_POST['first_name']    ?? '');
        $last_name      = trim($_POST['last_name']     ?? '');
        $class          = trim($_POST['class']         ?? '');
        $parent_phone   = trim($_POST['parent_phone']  ?? '');
        $address        = trim($_POST['address']       ?? '');
        $latitude       = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
        $longitude      = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

        if (!$first_name || !$last_name) {
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อ-นามสกุล']);
            exit;
        }

        // Handle profile image upload
        $profile_image = null;
        $existingImage = null;
        if ($id) {
            $row = $pdo->prepare('SELECT profile_image FROM students WHERE id=?');
            $row->execute([$id]);
            $existingImage = $row->fetchColumn();
        }

        if (!empty($_FILES['profile_image']['name'])) {
            $file      = $_FILES['profile_image'];
            $maxSize   = MAX_FILE_SIZE;
            $allowed   = ALLOWED_TYPES;

            if ($file['error'] !== UPLOAD_ERR_OK)      { echo json_encode(['success'=>false,'message'=>'อัพโหลดไฟล์ผิดพลาด']); exit; }
            if ($file['size'] > $maxSize)               { echo json_encode(['success'=>false,'message'=>'ไฟล์ใหญ่เกิน 5MB']); exit; }
            if (!in_array($file['type'], $allowed))     { echo json_encode(['success'=>false,'message'=>'อนุญาตเฉพาะไฟล์รูปภาพ']); exit; }

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'student_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            $dest     = UPLOAD_DIR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['success'=>false,'message'=>'บันทึกไฟล์ไม่สำเร็จ']);
                exit;
            }

            // Delete old image
            if ($existingImage && file_exists(UPLOAD_DIR . $existingImage)) {
                @unlink(UPLOAD_DIR . $existingImage);
            }

            $profile_image = $filename;
        } else {
            $profile_image = $existingImage; // keep existing
        }

        if ($id) {
            $stmt = $pdo->prepare('UPDATE students SET
                student_number=?, student_id=?, prefix=?, first_name=?, last_name=?,
                class=?, parent_phone=?, address=?, profile_image=?,
                latitude=?, longitude=?
                WHERE id=?');
            $stmt->execute([
                $student_number, $student_id, $prefix, $first_name, $last_name,
                $class, $parent_phone, $address, $profile_image,
                $latitude, $longitude, $id
            ]);
            echo json_encode(['success'=>true,'message'=>'แก้ไขข้อมูลสำเร็จ','id'=>$id], JSON_UNESCAPED_UNICODE);
        } else {
            $stmt = $pdo->prepare('INSERT INTO students
                (student_number, student_id, prefix, first_name, last_name,
                 class, parent_phone, address, profile_image, latitude, longitude)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $student_number, $student_id, $prefix, $first_name, $last_name,
                $class, $parent_phone, $address, $profile_image,
                $latitude, $longitude
            ]);
            echo json_encode(['success'=>true,'message'=>'เพิ่มนักเรียนสำเร็จ','id'=>$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /* ── DELETE single ────────────────────────────────────────── */
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!canDelete()) {
            echo json_encode(['success'=>false,'message'=>'คุณไม่มีสิทธิ์ลบข้อมูล'], JSON_UNESCAPED_UNICODE); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ไม่พบ ID']); exit; }

        $row = $pdo->prepare('SELECT profile_image FROM students WHERE id=?');
        $row->execute([$id]);
        $img = $row->fetchColumn();
        if ($img && file_exists(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);

        $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'ลบนักเรียนสำเร็จ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── DELETE ALL ───────────────────────────────────────────── */
    if ($action === 'delete_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!canDelete()) {
            echo json_encode(['success'=>false,'message'=>'คุณไม่มีสิทธิ์ลบข้อมูล'], JSON_UNESCAPED_UNICODE); exit;
        }
        // Remove all uploaded images
        $rows = $pdo->query('SELECT profile_image FROM students WHERE profile_image IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $img) {
            if (file_exists(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);
        }
        $pdo->exec('DELETE FROM students');
        echo json_encode(['success'=>true,'message'=>'ลบข้อมูลทั้งหมดสำเร็จ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── UPDATE LOCATION ──────────────────────────────────────── */
    if ($action === 'set_location' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id  = (int)($_POST['id'] ?? 0);
        $lat = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
        $lng = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ไม่พบ ID']); exit; }
        $pdo->prepare('UPDATE students SET latitude=?, longitude=? WHERE id=?')->execute([$lat,$lng,$id]);
        echo json_encode(['success'=>true,'message'=>'บันทึกพิกัดสำเร็จ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── PROMOTE CLASS ────────────────────────────────────────── */
    if ($action === 'promote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $rows  = $pdo->query("SELECT id, class FROM students WHERE class != ''")->fetchAll();
        $thaiNum = ['๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4','๕'=>'5','๖'=>'6'];
        $updated = 0;

        foreach ($rows as $r) {
            $cls = $r['class'];
            // Pattern: ป.X/Y or ม.X/Y  (e.g. ป.1/2)
            if (preg_match('/^(ป\.|ม\.)(\d+)(\/\d+.*)$/', $cls, $m)) {
                $level = (int)$m[2] + 1;
                $newCls = $m[1] . $level . $m[3];
                $pdo->prepare('UPDATE students SET class=? WHERE id=?')->execute([$newCls, $r['id']]);
                $updated++;
            }
        }
        echo json_encode(['success'=>true,'message'=>"เลื่อนชั้นสำเร็จ $updated คน"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── CSV IMPORT ───────────────────────────────────────────── */
    if ($action === 'import_csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['csv']['name'])) {
            echo json_encode(['success'=>false,'message'=>'กรุณาเลือกไฟล์ CSV']);
            exit;
        }

        $file     = $_FILES['csv'];
        $content  = file_get_contents($file['tmp_name']);

        // Detect & convert encoding to UTF-8
        $detected = mb_detect_encoding($content, ['UTF-8','TIS-620','Windows-874','ISO-8859-1'], true);
        if ($detected && $detected !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $detected);
        }
        // Remove BOM
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines   = preg_split('/\r\n|\r|\n/', trim($content));
        $header  = str_getcsv(array_shift($lines));
        $header  = array_map('trim', $header);

        $required = ['student_number','student_id','prefix','first_name','last_name','class'];
        $missing  = array_diff($required, $header);
        if ($missing) {
            echo json_encode(['success'=>false,'message'=>'CSV ขาดคอลัมน์: ' . implode(', ', $missing)]);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO students
            (student_number, student_id, prefix, first_name, last_name, class, parent_phone, address, latitude, longitude)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                student_number=VALUES(student_number), prefix=VALUES(prefix),
                first_name=VALUES(first_name), last_name=VALUES(last_name),
                class=VALUES(class), parent_phone=VALUES(parent_phone),
                address=VALUES(address)');

        $imported = 0; $errors = 0;
        foreach ($lines as $line) {
            if (!trim($line)) continue;
            $row = array_combine($header, str_getcsv($line));
            if (!$row) { $errors++; continue; }

            $lat = isset($row['latitude'])  && $row['latitude']  !== '' ? (float)$row['latitude']  : null;
            $lng = isset($row['longitude']) && $row['longitude'] !== '' ? (float)$row['longitude'] : null;

            try {
                $stmt->execute([
                    (int)($row['student_number'] ?? 0),
                    trim($row['student_id']   ?? ''),
                    trim($row['prefix']        ?? 'เด็กชาย'),
                    trim($row['first_name']    ?? ''),
                    trim($row['last_name']     ?? ''),
                    trim($row['class']         ?? ''),
                    trim($row['parent_phone']  ?? ''),
                    trim($row['address']       ?? ''),
                    $lat, $lng,
                ]);
                $imported++;
            } catch (PDOException $e) {
                $errors++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "นำเข้าสำเร็จ $imported แถว" . ($errors ? " (ข้ามไป $errors แถว)" : ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── CLASS LIST ───────────────────────────────────────────── */
    if ($action === 'classes') {
        $rows = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   Page render
══════════════════════════════════════════════════════════════════ */
$classList = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle  = 'จัดการนักเรียน';
$activePage = 'students';
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
          <h1 style="font-size:1.1rem;font-weight:700;">จัดการนักเรียน</h1>
          <p style="font-size:.78rem;color:var(--text-muted);">เพิ่ม แก้ไข ลบ และนำเข้าข้อมูลนักเรียน</p>
        </div>
      </div>
      <div class="flex items-center gap-2" style="flex-wrap:wrap;">
        <button class="btn btn-primary btn-sm" onclick="openStudentModal(0)">➕ เพิ่มนักเรียน</button>
        <button class="btn btn-secondary btn-sm" onclick="openModal('importModal')">📂 นำเข้า CSV</button>
        <button class="btn btn-warning btn-sm" onclick="promoteAll()">⬆️ เลื่อนชั้น</button>
        <?php if ($canDelete): ?>
        <button class="btn btn-danger btn-sm" onclick="deleteAll()">🗑️ ลบทั้งหมด</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="page-content">

      <!-- Filters bar -->
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
            <button class="btn btn-light btn-sm" onclick="loadStudents(1)">🔄 รีเฟรช</button>
          </div>
        </div>
      </div>

      <!-- Student Table -->
      <div class="card">
        <div class="card-header">
          <span style="font-weight:700;">รายชื่อนักเรียน</span>
          <span id="totalBadge" class="badge badge-primary">0 คน</span>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>เลขที่</th>
                <th>เลขประจำตัว</th>
                <th>ชื่อ–นามสกุล</th>
                <th>ชั้น</th>
                <th>เบอร์โทร</th>
                <th>พิกัด</th>
                <th style="text-align:center;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="studentTableBody">
              <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border);">
          <div id="pagination" class="pagination"></div>
        </div>
      </div>

    </div><!--/ page-content -->
  </div><!--/ main-content -->
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL: Add / Edit Student
═══════════════════════════════════════════════════════ -->
<div id="studentModal" class="modal-overlay">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <span class="modal-title" id="studentModalTitle">➕ เพิ่มนักเรียน</span>
      <button class="modal-close" onclick="closeModal('studentModal')">✕</button>
    </div>
    <form id="studentForm" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="id" id="s_id">

        <!-- Profile image -->
        <div class="form-group" style="text-align:center;">
          <div class="img-upload-area" id="uploadArea" onclick="document.getElementById('profile_image').click()">
            <img id="imgPreview" class="img-preview" src="" alt="">
            <div id="uploadPlaceholder">
              <div style="font-size:2.5rem;margin-bottom:.5rem;">📸</div>
              <div style="font-weight:600;font-size:.9rem;">คลิกเพื่อเลือกรูปภาพ</div>
              <div style="color:var(--text-muted);font-size:.78rem;">PNG, JPG, WEBP ไม่เกิน 5MB</div>
            </div>
          </div>
          <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display:none;">
        </div>

        <div class="grid-cols-2">
          <!-- student_number -->
          <div class="form-group">
            <label class="form-label">เลขที่ (ในห้อง)</label>
            <input type="number" name="student_number" id="s_student_number" class="form-control" min="0" value="0">
          </div>
          <!-- student_id -->
          <div class="form-group">
            <label class="form-label">เลขประจำตัว</label>
            <input type="text" name="student_id" id="s_student_id" class="form-control" placeholder="เช่น 67001">
          </div>
          <!-- prefix -->
          <div class="form-group">
            <label class="form-label">คำนำหน้า</label>
            <select name="prefix" id="s_prefix" class="form-control form-select">
              <option value="เด็กชาย">เด็กชาย</option>
              <option value="เด็กหญิง">เด็กหญิง</option>
              <option value="ด.ช.">ด.ช.</option>
              <option value="ด.ญ.">ด.ญ.</option>
              <option value="นาย">นาย</option>
              <option value="นางสาว">นางสาว</option>
              <option value="นาง">นาง</option>
            </select>
          </div>
          <!-- class -->
          <div class="form-group">
            <label class="form-label">ห้องเรียน</label>
            <input type="text" name="class" id="s_class" class="form-control" placeholder="เช่น ป.1/1">
          </div>
          <!-- first_name -->
          <div class="form-group">
            <label class="form-label">ชื่อ <span style="color:red">*</span></label>
            <input type="text" name="first_name" id="s_first_name" class="form-control" placeholder="ชื่อ" required>
          </div>
          <!-- last_name -->
          <div class="form-group">
            <label class="form-label">นามสกุล <span style="color:red">*</span></label>
            <input type="text" name="last_name" id="s_last_name" class="form-control" placeholder="นามสกุล" required>
          </div>
          <!-- parent_phone -->
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label">เบอร์โทรผู้ปกครอง</label>
            <input type="text" name="parent_phone" id="s_parent_phone" class="form-control" placeholder="0812345678">
          </div>
        </div>

        <!-- address -->
        <div class="form-group">
          <label class="form-label">ที่อยู่</label>
          <textarea name="address" id="s_address" class="form-control" rows="2" placeholder="ที่อยู่บ้าน (ไม่บังคับ)"></textarea>
        </div>

        <!-- lat / lng -->
        <div class="grid-cols-2">
          <div class="form-group">
            <label class="form-label">Latitude</label>
            <input type="text" name="latitude" id="s_latitude" class="form-control" placeholder="เช่น 13.7563">
          </div>
          <div class="form-group">
            <label class="form-label">Longitude</label>
            <input type="text" name="longitude" id="s_longitude" class="form-control" placeholder="เช่น 100.5018">
          </div>
        </div>

      </div><!--/ modal-body -->
      <div class="modal-footer">
        <button type="button" class="btn btn-light" onclick="closeModal('studentModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" id="saveStudentBtn">💾 บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL: Import CSV
═══════════════════════════════════════════════════════ -->
<div id="importModal" class="modal-overlay">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <span class="modal-title">📂 นำเข้าข้อมูล CSV</span>
      <button class="modal-close" onclick="closeModal('importModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info">
        ℹ️ ไฟล์ CSV ต้องมีหัวคอลัมน์: <code>student_number, student_id, prefix, first_name, last_name, class, parent_phone, address, latitude, longitude</code>
        <br><small>รองรับ UTF-8 และ TIS-620 (ภาษาไทย)</small>
      </div>
      <div style="margin-bottom:.75rem;">
        <a href="<?= BASE_URL ?>/assets/sample.csv" download class="btn btn-light btn-sm">⬇️ ดาวน์โหลดตัวอย่าง CSV</a>
      </div>
      <form id="importForm" enctype="multipart/form-data">
        <div class="form-group">
          <label class="form-label">เลือกไฟล์ CSV</label>
          <input type="file" name="csv" id="csvFile" class="form-control" accept=".csv,text/csv">
        </div>
      </form>
      <div id="importResult"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('importModal')">ปิด</button>
      <button class="btn btn-secondary" onclick="doImport()">📂 นำเข้า</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL: Set Location (Map)
═══════════════════════════════════════════════════════ -->
<div id="locationModal" class="modal-overlay">
  <div class="modal" style="max-width:720px;">
    <div class="modal-header">
      <span class="modal-title">📍 ตั้งพิกัดบ้านนักเรียน</span>
      <button class="modal-close" onclick="closeModal('locationModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="locStudentInfo" style="padding:.75rem;background:#f8fafc;border-radius:.75rem;margin-bottom:1rem;font-size:.88rem;"></div>

      <div class="flex items-center gap-2 mb-3" style="flex-wrap:wrap;">
        <button class="btn btn-secondary btn-sm" onclick="getCurrentLocation()">📡 ใช้ตำแหน่งปัจจุบัน</button>
        <button class="btn btn-light btn-sm" onclick="clearMarker()">🗑️ ล้างพิกัด</button>
      </div>

      <div id="locationMap" style="height:360px;border-radius:1rem;overflow:hidden;margin-bottom:1rem;"></div>

      <div class="grid-cols-2">
        <div class="form-group">
          <label class="form-label">Latitude</label>
          <input type="text" id="loc_lat" class="form-control" placeholder="คลิกบนแผนที่">
        </div>
        <div class="form-group">
          <label class="form-label">Longitude</label>
          <input type="text" id="loc_lng" class="form-control" placeholder="คลิกบนแผนที่">
        </div>
      </div>
      <small class="text-muted">💡 คลิกบนแผนที่เพื่อวางหมุด หรือลากหมุดเพื่อปรับตำแหน่ง หรือพิมพ์พิกัดโดยตรง</small>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('locationModal')">ยกเลิก</button>
      <button class="btn btn-primary" onclick="saveLocation()">💾 บันทึกพิกัด</button>
    </div>
  </div>
</div>

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= BASE_URL ?>/assets/js/map-markers.js"></script>

<script>
'use strict';

const API        = '<?= BASE_URL ?>/admin/students?api=';
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
let currentPage  = 1;


/* ── Load students ─────────────────────────────────────────── */
async function loadStudents(page = 1) {
  currentPage = page;
  const cls    = document.getElementById('filterClass').value;
  const search = document.getElementById('searchInput').value.trim();
  const url    = `${API}list&page=${page}&class=${encodeURIComponent(cls)}&search=${encodeURIComponent(search)}`;

  const tbody = document.getElementById('studentTableBody');
  tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">กำลังโหลด...</td></tr>';

  try {
    const res = await fetch(url);
    const json = await res.json();
    if (!json.success) { showToast('โหลดข้อมูลไม่สำเร็จ','error'); return; }

    document.getElementById('totalBadge').textContent = `${json.total} คน`;
    renderTable(json.data);
    renderPagination(json.pages, json.page);
  } catch (e) {
    showToast('เกิดข้อผิดพลาด: ' + e.message, 'error');
  }
}

function renderTable(rows) {
  const tbody = document.getElementById('studentTableBody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">ไม่พบข้อมูล</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map((s, i) => {
    const hasCoord = s.latitude && s.longitude;
    const imgHtml = s.profile_image
      ? `<img src="${BASE_URL}/uploads/students/${s.profile_image}" class="avatar avatar-sm" style="margin-right:.5rem;">`
      : `<span style="display:inline-flex;width:32px;height:32px;border-radius:50%;background:#ede9fe;color:#4f46e5;font-weight:700;align-items:center;justify-content:center;font-size:.75rem;margin-right:.5rem;">${(s.first_name||'?')[0]}</span>`;

    const deleteBtn = CAN_DELETE
      ? `<button class="btn btn-danger btn-sm btn-icon" title="ลบ" onclick="deleteStudent(${s.id},'${(s.first_name||'').replace(/'/g,"\\'") } ${(s.last_name||'').replace(/'/g,"\\'")}')">🗑️</button>`
      : `<button class="btn btn-light btn-sm btn-icon" title="ไม่มีสิทธิ์ลบ" disabled style="opacity:.4;">🗑️</button>`;

    return `<tr>
      <td style="color:var(--text-muted);font-size:.8rem;">${(currentPage-1)*20 + i+1}</td>
      <td>${s.student_number || '–'}</td>
      <td><code>${s.student_id || '–'}</code></td>
      <td>
        <div style="display:flex;align-items:center;">
          ${imgHtml}
          <div>
            <div style="font-weight:600;">${s.prefix||''} ${s.first_name||''} ${s.last_name||''}</div>
          </div>
        </div>
      </td>
      <td><span class="badge badge-primary">${s.class||'–'}</span></td>
      <td>${s.parent_phone||'–'}</td>
      <td>
        ${hasCoord
          ? `<span class="badge badge-success">✅ มีพิกัด</span>`
          : `<span class="badge badge-warning">⚠️ ยังไม่มี</span>`}
      </td>
      <td style="text-align:center;white-space:nowrap;">
        <button class="btn btn-warning btn-sm btn-icon" title="ตั้งพิกัด" onclick="openLocationModal(${s.id})">📍</button>
        <button class="btn btn-primary btn-sm btn-icon" title="แก้ไข" onclick="openStudentModal(${s.id})">✏️</button>
        <button class="btn btn-purple btn-sm btn-icon" title="บันทึกการเยี่ยมบ้าน" style="background:#8b5cf6;" onclick="window.location='${BASE_URL}/admin/visit_log?add_visit=${s.id}'">📋</button>
        ${deleteBtn}
      </td>
    </tr>`;
  }).join('');
}

function renderPagination(pages, current) {
  const el = document.getElementById('pagination');
  if (pages <= 1) { el.innerHTML = ''; return; }

  let html = '';
  html += `<div class="page-item ${current===1?'disabled':''}" onclick="loadStudents(${current-1})">‹</div>`;
  for (let i = 1; i <= pages; i++) {
    if (i === 1 || i === pages || Math.abs(i - current) <= 2) {
      html += `<div class="page-item ${i===current?'active':''}" onclick="loadStudents(${i})">${i}</div>`;
    } else if (Math.abs(i - current) === 3) {
      html += `<div class="page-item disabled">…</div>`;
    }
  }
  html += `<div class="page-item ${current===pages?'disabled':''}" onclick="loadStudents(${current+1})">›</div>`;
  el.innerHTML = html;
}

/* ── Student Modal ─────────────────────────────────────────── */
async function openStudentModal(id) {
  // Reset form
  document.getElementById('studentForm').reset();
  document.getElementById('s_id').value = '';
  document.getElementById('imgPreview').classList.remove('show');
  document.getElementById('uploadPlaceholder').style.display = '';

  if (id) {
    document.getElementById('studentModalTitle').textContent = '✏️ แก้ไขข้อมูลนักเรียน';
    const res  = await fetch(`${API}get&id=${id}`);
    const json = await res.json();
    if (!json.success) { showToast('โหลดข้อมูลไม่สำเร็จ','error'); return; }
    const s = json.data;

    document.getElementById('s_id').value             = s.id;
    document.getElementById('s_student_number').value = s.student_number;
    document.getElementById('s_student_id').value     = s.student_id;
    document.getElementById('s_prefix').value         = s.prefix;
    document.getElementById('s_first_name').value     = s.first_name;
    document.getElementById('s_last_name').value      = s.last_name;
    document.getElementById('s_class').value          = s.class;
    document.getElementById('s_parent_phone').value   = s.parent_phone;
    document.getElementById('s_address').value        = s.address || '';
    document.getElementById('s_latitude').value       = s.latitude  || '';
    document.getElementById('s_longitude').value      = s.longitude || '';

    if (s.profile_image) {
      document.getElementById('imgPreview').src = `${BASE_URL}/uploads/students/${s.profile_image}`;
      document.getElementById('imgPreview').classList.add('show');
      document.getElementById('uploadPlaceholder').style.display = 'none';
    }
  } else {
    document.getElementById('studentModalTitle').textContent = '➕ เพิ่มนักเรียน';
  }

  openModal('studentModal');
}

// Image preview
document.getElementById('profile_image').addEventListener('change', function () {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('imgPreview').src = e.target.result;
    document.getElementById('imgPreview').classList.add('show');
    document.getElementById('uploadPlaceholder').style.display = 'none';
  };
  reader.readAsDataURL(file);
});

// Submit student form
document.getElementById('studentForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const btn = document.getElementById('saveStudentBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> กำลังบันทึก...';

  const fd = new FormData(this);
  try {
    const res  = await fetch(`${API}save`, { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      closeModal('studentModal');
      loadStudents(currentPage);
    } else {
      showToast(json.message || 'เกิดข้อผิดพลาด', 'error');
    }
  } catch (err) {
    showToast('เกิดข้อผิดพลาด: ' + err.message, 'error');
  }
  btn.disabled = false;
  btn.innerHTML = '💾 บันทึก';
});

/* ── Delete student ─────────────────────────────────────────── */
function deleteStudent(id, name) {
  confirmDialog(`ต้องการลบ "${name}" ออกจากระบบ?`, async () => {
    const fd = new FormData();
    fd.set('id', id);
    const res  = await fetch(`${API}delete`, { method:'POST', body:fd });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadStudents(currentPage);
  });
}

/* ── Delete ALL ─────────────────────────────────────────────── */
function deleteAll() {
  confirmDialog('⚠️ ต้องการลบนักเรียนทั้งหมดออกจากระบบ? การกระทำนี้ไม่สามารถย้อนกลับได้!', async () => {
    const res  = await fetch(`${API}delete_all`, { method:'POST' });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadStudents(1);
  });
}

/* ── Promote class ──────────────────────────────────────────── */
function promoteAll() {
  confirmDialog('เลื่อนชั้นนักเรียนทั้งหมด? (เช่น ป.1 → ป.2)', async () => {
    const res  = await fetch(`${API}promote`, { method:'POST' });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadStudents(1);
  });
}

/* ── CSV Import ─────────────────────────────────────────────── */
async function doImport() {
  const file = document.getElementById('csvFile').files[0];
  if (!file) { showToast('กรุณาเลือกไฟล์ CSV', 'warning'); return; }

  const fd = new FormData();
  fd.set('csv', file);

  const resultEl = document.getElementById('importResult');
  resultEl.innerHTML = '<div class="alert alert-info">⏳ กำลังนำเข้า...</div>';

  const res  = await fetch(`${API}import_csv`, { method:'POST', body:fd });
  const json = await res.json();

  resultEl.innerHTML = json.success
    ? `<div class="alert alert-success">✅ ${json.message}</div>`
    : `<div class="alert alert-danger">❌ ${json.message}</div>`;

  if (json.success) {
    setTimeout(() => { closeModal('importModal'); loadStudents(1); }, 1500);
  }
}

/* ══════════════════════════════════════════════════════════════
   Location Modal
══════════════════════════════════════════════════════════════ */
let locMap = null, locMarker = null, locStudentId = null;

async function openLocationModal(studentId) {
  locStudentId = studentId;

  // Get student info
  const res  = await fetch(`${API}get&id=${studentId}`);
  const json = await res.json();
  if (!json.success) { showToast('โหลดข้อมูลไม่สำเร็จ','error'); return; }
  const s = json.data;

  document.getElementById('locStudentInfo').innerHTML =
    `<strong>${s.prefix} ${s.first_name} ${s.last_name}</strong> · ชั้น ${s.class} · เลขที่ ${s.student_number}`;

  const lat = parseFloat(s.latitude) || 13.7563;
  const lng = parseFloat(s.longitude)|| 100.5018;
  document.getElementById('loc_lat').value = s.latitude  || '';
  document.getElementById('loc_lng').value = s.longitude || '';

  openModal('locationModal');

  // Init map (wait for modal to be visible)
  setTimeout(() => {
    if (!locMap) {
      locMap = L.map('locationMap').setView([lat, lng], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        { attribution:'© OpenStreetMap contributors', maxZoom:19 }).addTo(locMap);

      locMap.on('click', function (e) { placeMarker(e.latlng.lat, e.latlng.lng); });
    } else {
      locMap.setView([lat, lng], 14);
      locMap.invalidateSize();
    }

    // Place existing marker
    if (s.latitude && s.longitude) {
      placeMarker(lat, lng);
    }
  }, 300);
}

function placeMarker(lat, lng) {
  if (locMarker) locMap.removeLayer(locMarker);
  locMarker = L.marker([lat, lng], { draggable: true }).addTo(locMap);
  locMarker.on('dragend', function () {
    const p = locMarker.getLatLng();
    setLocInputs(p.lat, p.lng);
  });
  setLocInputs(lat, lng);
  locMap.setView([lat, lng], 15);
}

function setLocInputs(lat, lng) {
  document.getElementById('loc_lat').value = lat.toFixed(7);
  document.getElementById('loc_lng').value = lng.toFixed(7);
}

function clearMarker() {
  if (locMarker) { locMap.removeLayer(locMarker); locMarker = null; }
  document.getElementById('loc_lat').value = '';
  document.getElementById('loc_lng').value = '';
}

function getCurrentLocation() {
  if (!navigator.geolocation) { showToast('อุปกรณ์ไม่รองรับ Geolocation','warning'); return; }
  navigator.geolocation.getCurrentPosition(
    pos => { placeMarker(pos.coords.latitude, pos.coords.longitude); },
    err => showToast('ไม่สามารถเข้าถึงตำแหน่งได้: ' + err.message, 'error'),
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

// Allow manual input update
['loc_lat','loc_lng'].forEach(id => {
  document.getElementById(id).addEventListener('change', function () {
    const lat = parseFloat(document.getElementById('loc_lat').value);
    const lng = parseFloat(document.getElementById('loc_lng').value);
    if (!isNaN(lat) && !isNaN(lng)) placeMarker(lat, lng);
  });
});

async function saveLocation() {
  const lat = document.getElementById('loc_lat').value.trim();
  const lng = document.getElementById('loc_lng').value.trim();

  const fd = new FormData();
  fd.set('id',        locStudentId);
  fd.set('latitude',  lat);
  fd.set('longitude', lng);

  const res  = await fetch(`${API}set_location`, { method:'POST', body:fd });
  const json = await res.json();
  showToast(json.message, json.success ? 'success' : 'error');
  if (json.success) {
    closeModal('locationModal');
    loadStudents(currentPage);
  }
}

/* ── Filters & search ──────────────────────────────────────── */
document.getElementById('filterClass').addEventListener('change', () => loadStudents(1));
document.getElementById('searchInput').addEventListener('input',  () => loadStudents(1));

/* ── Init ──────────────────────────────────────────────────── */
loadStudents(1);

if (window.innerWidth <= 768) {
  document.getElementById('sidebar-toggle').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
