<?php
// ============================================================
//  includes/config.php
//  Central configuration — DB constants, session, auth helpers
// ============================================================

// ── Database credentials ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'admin123');      // ← change to your MySQL password
define('DB_NAME', 'manage_uni');

// ── Site settings ───────────────────────────────────────────
define('SITE_NAME', 'University Management System');
// define('SITE_URL',  'http://localhost:8080/university_management');
// define('SITE_URL', 'http://localhost:3000/university_management');
define('SITE_URL', 'http://localhost:4000/');

// ── Start session (safe, won't double-start) ─────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── mysqli connection (used by all modules via db.php) ───────
//    This function is kept for legacy callers; new code should
//    just include db.php and use $conn directly.
function getDBConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("
        <div style='font-family:sans-serif;padding:20px;background:#fee;
                    color:#c00;border:1px solid #c00;margin:20px;border-radius:8px;'>
            <h3>❌ Database Connection Failed</h3>
            <p>" . htmlspecialchars($conn->connect_error) . "</p>
            <p>Check your credentials in <code>includes/config.php</code></p>
        </div>");
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ── Auth check ───────────────────────────────────────────────
//    FIX: was checking 'admin_id' — now correctly checks 'user_id'
//    which is what login.php sets in $_SESSION.
function checkLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login');
        exit();
    }
}

// ── Role helpers ─────────────────────────────────────────────
function isAdmin():   bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function isFaculty(): bool { return ($_SESSION['role'] ?? '') === 'faculty'; }
function isStudent(): bool { return ($_SESSION['role'] ?? '') === 'student'; }
function canEdit():   bool { return isAdmin(); }

// ── Access guard — call blockIf(isStudent(), 'message') ──────
function blockIf(bool $condition, string $message = 'Access Denied'): void {
    if ($condition) {
        die("<div class='alert alert-danger' style='margin:20px'>
                <i class='fas fa-ban'></i> ⛔ " . htmlspecialchars($message) . "
             </div>
             <p style='margin:10px 20px'><a href='/dashboard'>← Back to Dashboard</a></p>");
    }
}

// ── Input helpers ────────────────────────────────────────────
function sanitize(mysqli $conn, string $data): string {
    return $conn->real_escape_string(htmlspecialchars(trim($data)));
}

// ── Flash messages ───────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}