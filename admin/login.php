<?php
// admin/login.php - Admin Authentication with Brute-Force Rate Limiting
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

send_no_cache_headers();

// Redirect if already logged in
if (is_admin_logged_in()) {
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

$error = '';
$username = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif (!check_rate_limit('admin_login', 5, 60)) {
        $error = 'Too many failed login attempts. Please wait 60 seconds before trying again.';
    } else {
        $password = $_POST['password'] ?? '';
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = :u OR email = :u2 LIMIT 1");
            $stmt->execute([':u' => $username, ':u2' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                reset_rate_limit('admin_login');

                $_SESSION[ADMIN_SESSION_KEY] = [
                    'id'       => $admin['id'],
                    'username' => $admin['username'],
                    'email'    => $admin['email']
                ];

                header("Location: " . BASE_URL . "/admin/index.php");
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            error_log('[admin/login.php error]: ' . $e->getMessage());
            $error = 'Database connection issue during authentication. Please retry.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Admin Login | Millennium India Group</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
    <style>
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f111a radial-gradient(circle at 50% 20%, #3d0720 0%, #0f111a 70%);
            padding: 20px;
        }
        .login-box {
            background: #1b1e2b;
            border: 1px solid #2e344a;
            border-radius: 16px;
            width: 100%;
            max-width: 420px;
            padding: 35px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo {
            font-size: 1.5rem;
            font-weight: 900;
            color: #f5b722;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
    </style>
</head>
<body style="display:block;">
    <div class="login-wrap">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">&#128081; Millennium Admin</div>
                <div style="color: #9aa1b8; font-size: 0.9rem; margin-top: 6px;">Event Management &amp; Gate Access Portal</div>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #f87171; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/admin/login.php" method="POST">
                <?= csrf_field() ?>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 6px;">Username or Email</label>
                    <input type="text" name="username" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:12px; border-radius:8px;" value="<?= e($username) ?>" required autofocus>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 6px;">Password</label>
                    <input type="password" name="password" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:12px; border-radius:8px;" required>
                </div>

                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #f5b722, #d89614); color: #12141d; font-weight: 800; padding: 13px; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;">
                    Sign In to Dashboard &rarr;
                </button>
            </form>

            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #2e344a; font-size: 0.8rem; color: #64748b; text-align: center;">
                Default Credentials: <code>admin</code> / <code>Admin@MIG2026!</code><br>
                <a href="<?= BASE_URL ?>/index.php" style="color: #f5b722; text-decoration: none; display: inline-block; margin-top: 8px;">&larr; Back to Website</a>
            </div>
        </div>
    </div>
</body>
</html>
