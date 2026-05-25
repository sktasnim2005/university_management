<?php
// ============================================================
//  login.php  — public page, no header.php needed
// ============================================================

require_once __DIR__ . '/includes/config.php';  // session + constants

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit();
}

require_once __DIR__ . '/includes/db.php';  // gives us $conn

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = hash('sha256', trim($_POST['password'] ?? ''));

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        // ref_id stores student_id for students, faculty_id for faculty, 0 for admin
        $_SESSION['ref_id']   = $user['ref_id'];

        // Update last login timestamp
        $uid = (int)$user['user_id'];
        $conn->query("UPDATE users SET last_login = NOW() WHERE user_id = $uid");

        header('Location: /dashboard');
        exit();
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-logo">
        <i class="fas fa-university"></i>
        <h2>UniManage</h2>
        <p>University Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" placeholder="Enter username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn btn-primary login-btn">
            <i class="fas fa-sign-in-alt"></i> Login
        </button>
    </form>
</div>

</body>
</html>