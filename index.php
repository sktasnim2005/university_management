<?php
// ============================================================
//  index.php — Front controller / URL router
//  Run with: php -S localhost:3000 index.php
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';

// ── Route map ─────────────────────────────────────────────────
$routes = [
    '/'            => 'modules/dashboard.php',
    '/login'       => 'login.php',
    '/logout'      => 'logout.php',
    '/dashboard'   => 'modules/dashboard.php',
    '/students'    => 'modules/students.php',
    '/faculty'     => 'modules/faculty.php',
    '/courses'     => 'modules/courses.php',
    '/departments' => 'modules/departments.php',
    '/enrollments' => 'modules/enrollments.php',
    '/grades'      => 'modules/grades.php',
    '/fees'        => 'modules/fees.php',
    '/attendance'  => 'modules/attendance.php',
];

// ── Serve static files (css, js, images) ─────────────────────
$static = __DIR__ . $uri;
if ($uri !== '/' && file_exists($static) && is_file($static)) {
    return false;
}

// ── Match route ───────────────────────────────────────────────
if (isset($routes[$uri])) {
    $file = __DIR__ . '/' . $routes[$uri];
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(500);
        echo "<div style='font-family:sans-serif;padding:40px;text-align:center'>
                <h2>⚠️ Route file not found</h2>
                <p><code>" . htmlspecialchars($routes[$uri]) . "</code></p>
              </div>";
    }
} else {
    http_response_code(404);
    echo "<div style='font-family:sans-serif;padding:40px;text-align:center'>
            <h2>404 — Page Not Found</h2>
            <p><a href='/dashboard'>← Back to Dashboard</a></p>
          </div>";
}