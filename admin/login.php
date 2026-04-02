<?php
/**
 * login.php – Admin login page
 * Student Home Visit Map System
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in → redirect
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        if (loginAdmin($username, $password)) {
            header('Location: ' . BASE_URL . '/admin/dashboard');
            exit;
        }
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เข้าสู่ระบบ · <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 50%, #10b981 100%);
      padding: 1rem;
    }
    .login-card {
      width: 100%; max-width: 420px;
      background: #fff;
      border-radius: 2rem;
      box-shadow: 0 25px 80px rgba(0,0,0,.25);
      overflow: hidden;
      animation: fadeUp .5s ease;
    }
    @keyframes fadeUp {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:none; }
    }
    .login-banner {
      background: linear-gradient(135deg, #4f46e5, #06b6d4);
      padding: 2.5rem;
      text-align: center;
      color: #fff;
    }
    .login-banner .icon-wrap {
      width: 80px; height: 80px;
      background: rgba(255,255,255,.2);
      border-radius: 50%;
      margin: 0 auto 1rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem;
      backdrop-filter: blur(4px);
    }
    .login-form { padding: 2rem; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="login-banner">
    <div class="icon-wrap">🏫</div>
    <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:.35rem;"><?= APP_NAME ?></h1>
    <p style="font-size:.9rem;opacity:.85;">เข้าสู่ระบบผู้ดูแล</p>
  </div>

  <div class="login-form">
    <?php if ($error): ?>
      <div class="alert alert-danger mb-4">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="username">ชื่อผู้ใช้</label>
        <div style="position:relative;">
          <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:1rem;">👤</span>
          <input id="username" name="username" type="text" class="form-control"
                 style="padding-left:2.75rem;"
                 placeholder="กรอกชื่อผู้ใช้"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">รหัสผ่าน</label>
        <div style="position:relative;">
          <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:1rem;">🔑</span>
          <input id="password" name="password" type="password" class="form-control"
                 style="padding-left:2.75rem;"
                 placeholder="กรอกรหัสผ่าน"
                 required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-full" style="margin-top:.5rem;height:48px;font-size:1rem;">
        เข้าสู่ระบบ
      </button>
    </form>

    <div style="text-align:center;margin-top:1.5rem;font-size:.8rem;color:var(--text-muted);">
      ระบบแผนที่เยี่ยมบ้านนักเรียน v<?= APP_VERSION ?>
    </div>
  </div>
</div>
</body>
</html>
