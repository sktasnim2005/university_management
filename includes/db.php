<?php
// ============================================================
//  includes/db.php
//  Provides $conn (mysqli) to every module that includes this.
//  config.php is loaded first so all helpers are available too.
// ============================================================

require_once __DIR__ . '/config.php';   // constants + session + helpers

// Create the shared connection used throughout all modules
$conn = getDBConnection();