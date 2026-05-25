<?php

require_once 'includes/db.php';

echo "<h2>Connected Database:</h2>";

$result = $conn->query("SELECT DATABASE() AS db");

$row = $result->fetch_assoc();

echo $row['db'];

echo "<hr>";

echo "<h2>Students Table:</h2>";

$res = $conn->query("SELECT * FROM students");

while($r = $res->fetch_assoc()) {
    echo $r['student_id'] . " - " . $r['first_name'] . "<br>";
}
