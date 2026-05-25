<?php
// ============================================================
//  logout.php
//  Destroys session and redirects to login page.
//  FIX: was accidentally mixed into the bottom of login.php
// ============================================================

require_once __DIR__ . '/includes/config.php';

session_destroy();
header('Location: /login');
exit();