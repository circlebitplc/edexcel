<?php
// login.php
require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    }

    if ($error) {
        // Skip credential processing for an invalid CSRF token.
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Fetch user including teacher_id
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND deleted_at IS NULL");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session to prevent fixation
            regenerate_session();
            
            // Store user data in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['teacher_id'] = $user['teacher_id'] ?? null; // <-- FIX: store teacher_id
            $_SESSION['username'] = $user['username'];

            // Redirect based on role (optional)
            if ($user['role'] === 'admin') {
                header('Location: dashboard.php');
            } else {
                header('Location: dashboard.php'); // or a teacher-specific dashboard
            }
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in both fields.';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Login - Edexcel College</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/system.css?v=<?= filemtime(__DIR__ . '/assets/css/system.css') ?>">
    
    <style>
        .auth-page { min-height: 100vh; display:grid; place-items:center; padding:24px; }
        .auth-shell { width:min(100%, 440px); }
        .auth-card { background:var(--surface); border:1px solid var(--panel-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-md); padding:34px; }
        .auth-brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; }
        .auth-brand-icon { width:48px; height:48px; display:grid; place-items:center; border-radius:14px; color:#fff; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); box-shadow:0 10px 24px rgba(81,97,206,.22); font-size:1.35rem; }
        .auth-title { margin:0; font-size:1.7rem; font-weight:800; color:var(--text); }
        .auth-subtitle { margin:4px 0 0; color:var(--muted); font-size:.9rem; }
        .auth-form .form-control { min-height:48px; border-radius:12px; }
        .auth-submit { min-height:48px; border-radius:12px; font-weight:700; }
        .auth-footer { margin-top:20px; text-align:center; color:var(--muted); font-size:.8rem; }
        @media (max-width:576px){ .auth-page{padding:16px;} .auth-card{padding:24px 20px;} .auth-title{font-size:1.45rem;} }
    </style>
</head>
<body>
<main class="auth-page">
    <div class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand">
                <div class="auth-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div><h1 class="auth-title">Edexcel College</h1><p class="auth-subtitle">Timetable management portal</p></div>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <form method="POST" action="" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">Username</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input type="text" class="form-control" id="username" name="username" autocomplete="username" required autofocus></div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required></div>
                </div>
                <button type="submit" class="btn btn-primary w-100 auth-submit"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
            </form>
            <div class="auth-footer">Edexcel College Kandy · Secure staff portal</div>
        </section>
    </div>
</main>
</body>
</html>
