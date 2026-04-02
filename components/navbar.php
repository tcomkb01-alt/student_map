<?php
/**
 * navbar.php – Admin sidebar navigation
 * Inject: $activePage (string) = 'dashboard' | 'students' | 'users'
 */
if (!isset($activePage)) $activePage = '';

require_once __DIR__ . '/../includes/auth.php';

$currentRole = $_SESSION['admin_role'] ?? 'admin';
$isAdminFull = ($currentRole === 'admin');

// Handle flash messages
$flashError   = $_SESSION['flash_error']   ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!-- Mobile overlay -->
<div class="sidebar-overlay"></div>

<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">🏫</div>
        <div class="logo-text">
            <?= APP_NAME ?>
            <small>ระบบผู้ดูแล</small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">การจัดการ</div>

        <a href="<?= BASE_URL ?>/admin/dashboard" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            แดชบอร์ด
        </a>

        <a href="<?= BASE_URL ?>/admin/students" class="nav-item <?= $activePage === 'students' ? 'active' : '' ?>">
            <span class="nav-icon">👨‍🎓</span>
            จัดการนักเรียน
        </a>

        <?php if ($isAdminFull): ?>
        <a href="<?= BASE_URL ?>/admin/users" class="nav-item <?= $activePage === 'users' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            จัดการผู้ใช้งาน
        </a>
        <?php endif; ?>

        <div class="nav-section-label mt-3">ระบบเยี่ยมบ้าน</div>
        <a href="<?= BASE_URL ?>/admin/apps" class="nav-item <?= $activePage === 'apps' ? 'active' : '' ?>">
            <span class="nav-icon">🧩</span>
            App Center
        </a>

        <div class="nav-section-label mt-3">สาธารณะ</div>

        <a href="<?= BASE_URL ?>/public/" target="_blank" class="nav-item">
            <span class="nav-icon">🌐</span>
            หน้าเว็บสาธารณะ
        </a>

        <a href="<?= BASE_URL ?>/public/map" target="_blank" class="nav-item">
            <span class="nav-icon">🗺️</span>
            แผนที่สาธารณะ
        </a>
    </nav>

    <!-- Admin info + Logout -->
    <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);">
        <div class="flex items-center gap-3 mb-2">
            <div
                style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#06b6d4);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;">
                <?= strtoupper(mb_substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:.85rem;">
                    <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                <?php if ($isAdminFull): ?>
                <div
                    style="font-size:.68rem;font-weight:700;color:#4f46e5;background:#ede9fe;border-radius:99px;padding:.1rem .5rem;display:inline-block;margin-top:.15rem;">
                    👑 Admin</div>
                <?php else: ?>
                <div
                    style="font-size:.68rem;font-weight:700;color:#0891b2;background:#cffafe;border-radius:99px;padding:.1rem .5rem;display:inline-block;margin-top:.15rem;">
                    ✏️ Editor</div>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-light w-full" style="justify-content:center;">
            🚪 ออกจากระบบ
        </a>
    </div>
</aside>

<?php if ($flashError): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof showToast === 'function') showToast(<?= json_encode($flashError) ?>, 'error');
});
</script>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof showToast === 'function') showToast(<?= json_encode($flashSuccess) ?>, 'success');
});
</script>
<?php endif; ?>