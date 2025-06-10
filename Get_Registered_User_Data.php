<?php
// Returns: { "student": number, "staff": number }
$conn = new mysqli("localhost", "root", "", "mypetakom");
header('Content-Type: application/json');
$student = 0; $staff = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM student");
if ($row = $res->fetch_assoc()) $student = (int)$row['cnt'];
$res = $conn->query("SELECT COUNT(*) AS cnt FROM staff");
if ($row = $res->fetch_assoc()) $staff = (int)$row['cnt'];
echo json_encode(['student' => $student, 'staff' => $staff]);