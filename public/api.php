<?php
/**
 * public/api.php – Public read-only API (no login required)
 * Returns student data for public map page
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo    = getDB();
$action = $_GET['action'] ?? '';

// ─── For POST requests: read JSON body once and resolve action ───────────────
$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) $body = json_decode($raw, true) ?: [];
    // If action not in query string, take it from body
    if (!$action && !empty($body['action'])) {
        $action = $body['action'];
    }
}

if ($action === 'students') {
    $class  = $_GET['class']  ?? '';
    $search = $_GET['search'] ?? '';

    $where  = [];
    $params = [];

    // Only return students with coordinates for public map
    // (comment this line to show all students even without coords)
    // $where[] = 'latitude IS NOT NULL AND longitude IS NOT NULL';

    if ($class)  { $where[] = 'class = ?';  $params[] = $class; }
    if ($search) {
        $where[] = '(student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like);
    }

    $sql = 'SELECT id, student_number, student_id, prefix, first_name, last_name,
                   class, parent_phone, address, profile_image, latitude, longitude
            FROM students';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY class, student_number';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'total'   => count($rows),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'classes') {
    $rows = $pdo->query("SELECT DISTINCT class FROM students WHERE class != '' ORDER BY class")
                ->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Find student by student_id (for parent page) ───────────────────────────
if ($action === 'find_student') {
    $sid = trim($_GET['student_id'] ?? '');
    if ($sid === '') {
        echo json_encode(['success'=>false,'message'=>'กรุณาระบุเลขประจำตัวนักเรียน'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT id, student_number, student_id, prefix, first_name, last_name,
                class, parent_phone, address, profile_image, latitude, longitude
         FROM students WHERE student_id = ? LIMIT 1'
    );
    $stmt->execute([$sid]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['success'=>false,'message'=>'ไม่พบนักเรียนที่มีเลขประจำตัว "'.htmlspecialchars($sid, ENT_QUOTES).'"'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Save location by parent (POST) ─────────────────────────────────────────
if ($action === 'save_location') {
    $sid = trim($body['student_id'] ?? '');
    $lat = $body['latitude']  ?? null;
    $lng = $body['longitude'] ?? null;

    if (!$sid || $lat === null || $lng === null) {
        echo json_encode(['success'=>false,'message'=>'ข้อมูลไม่ครบถ้วน'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lat = (float)$lat;
    $lng = (float)$lng;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(['success'=>false,'message'=>'พิกัดไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE students SET latitude=?, longitude=? WHERE student_id=?');
    $ok   = $stmt->execute([$lat, $lng, $sid]);

    if ($ok && $stmt->rowCount() > 0) {
        echo json_encode(['success'=>true,'message'=>'บันทึกพิกัดเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
    } elseif ($ok) {
        echo json_encode(['success'=>false,'message'=>'ไม่พบนักเรียนที่มีเลขประจำตัวนี้'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success'=>false,'message'=>'เกิดข้อผิดพลาดในการบันทึก'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ─── Update contact info by parent (POST) ────────────────────────────────────
if ($action === 'update_contact') {
    $sid   = trim($body['student_id'] ?? '');
    $field = trim($body['field']      ?? '');
    $value = trim($body['value']      ?? '');

    // Whitelist allowed fields only
    $allowed = ['parent_phone', 'address'];
    if (!$sid || !in_array($field, $allowed, true)) {
        echo json_encode(['success'=>false,'message'=>'ข้อมูลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Basic length limits
    $limits = ['parent_phone' => 20, 'address' => 1000];
    if (mb_strlen($value) > $limits[$field]) {
        echo json_encode(['success'=>false,'message'=>'ข้อมูลยาวเกินกำหนด'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE students SET `$field`=? WHERE student_id=?");
    $ok   = $stmt->execute([$value, $sid]);

    if ($ok && $stmt->rowCount() > 0) {
        echo json_encode(['success'=>true,'message'=>'บันทึกข้อมูลเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
    } elseif ($ok) {
        echo json_encode(['success'=>false,'message'=>'ไม่พบนักเรียนที่มีเลขประจำตัวนี้'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success'=>false,'message'=>'เกิดข้อผิดพลาดในการบันทึก'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action'], JSON_UNESCAPED_UNICODE);
