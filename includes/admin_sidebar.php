<?php
// includes/admin_sidebar.php - Admin Navigation Sidebar
$adminCurrent = $adminCurrent ?? 'dashboard';
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-logo">
            <span>&#128081; MIG ADMIN</span>
        </a>
        <div style="font-size: 0.78rem; color: var(--admin-text-muted); margin-top: 4px;">
            <?= e(admin_user()['username'] ?? 'Administrator') ?>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-link <?= $adminCurrent === 'dashboard' ? 'active' : '' ?>">
                <span>&#128202;</span> <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="sidebar-link <?= $adminCurrent === 'bookings' ? 'active' : '' ?>">
                <span>&#128179;</span> <span>Bookings & UPI</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/scanner.php" class="sidebar-link <?= $adminCurrent === 'scanner' ? 'active' : '' ?>">
                <span>&#128247;</span> <span>QR Gate Scanner</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/events.php" class="sidebar-link <?= $adminCurrent === 'events' ? 'active' : '' ?>">
                <span>&#127881;</span> <span>Manage Events</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/tickets.php" class="sidebar-link <?= $adminCurrent === 'tickets' ? 'active' : '' ?>">
                <span>&#127915;</span> <span>Ticket Categories</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link <?= $adminCurrent === 'settings' ? 'active' : '' ?>">
                <span>&#9881;</span> <span>Settings & UPI</span>
            </a>
        </li>
        <li style="margin-top: 20px; border-top: 1px solid var(--admin-card-border); padding-top: 15px;">
            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="sidebar-link">
                <span>&#127760;</span> <span>View Live Site</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/logout.php" class="sidebar-link" style="color: #f87171;">
                <span>&#128275;</span> <span>Logout</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div>Millennium India Group</div>
        <div style="font-size:0.75rem; color:#777;">v2.0 &bull; Jashn-E-Garba 2026</div>
    </div>
</aside>
