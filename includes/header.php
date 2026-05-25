<?php
// ============================================================
//  includes/header.php
//  Starts session, enforces login, renders navbar + page shell.
//  All role helpers come from config.php (via db.php or direct).
// ============================================================

require_once __DIR__ . '/config.php';   // session + helpers + constants

// Every page that includes header.php must have a logged-in user
checkLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <i class="fas fa-university"></i> UniManage
    </div>
    <ul class="nav-links">
        <li><a href="/dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="/students"><i class="fas fa-user-graduate"></i> Students</a></li>

        <?php if (isAdmin() || isFaculty()): ?>
        <li><a href="/faculty"><i class="fas fa-chalkboard-teacher"></i> Faculty</a></li>
        <?php endif; ?>

        <li><a href="/courses"><i class="fas fa-book"></i> Courses</a></li>
        <li><a href="/departments"><i class="fas fa-building"></i> Departments</a></li>

        <?php if (isAdmin()): ?>
        <li><a href="/enrollments"><i class="fas fa-clipboard-list"></i> Enrollments</a></li>
        <?php endif; ?>

        <li><a href="/grades"><i class="fas fa-graduation-cap"></i> Grades</a></li>

        <?php if (!isFaculty()): ?>
        <li><a href="/fees"><i class="fas fa-money-bill-wave"></i> Fees</a></li>
        <?php endif; ?>

        <li><a href="/attendance"><i class="fas fa-clipboard-check"></i> Attendance</a></li>
        <li><a href="/logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
    <div class="nav-user">
        <i class="fas fa-user-circle"></i>
        <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
        <span class="role-badge role-<?= $_SESSION['role'] ?? '' ?>">
            <?= strtoupper($_SESSION['role'] ?? '') ?>
        </span>
    </div>
</nav>

<div class="main-content">

<?php
// ── Show flash message if one was set ────────────────────────
$flash = getFlash();
if ($flash):
    $icon = match($flash['type']) {
        'success' => 'check-circle',
        'danger'  => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        default   => 'info-circle'
    };
?>
<div class="alert alert-<?= $flash['type'] ?>" id="flashMsg">
    <i class="fas fa-<?= $icon ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>