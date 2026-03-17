<?php
// api/get_booked_slots.php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('admin');

$teacher_id = $_GET['teacher_id'] ?? 0;
$classroom_id = $_GET['classroom_id'] ?? 0;
$day = $_GET['day'] ?? '';
$academic_year = $_GET['academic_year'] ?? '';
$semester = $_GET['semester'] ?? '';

if (!$day || !$academic_year || !$semester) {
    echo json_encode([]);
    exit;
}

// Fetch all booked slots for the selected teacher OR classroom on the selected day/term
$stmt = $pdo->prepare("
    SELECT start_time, end_time 
    FROM teaching_schedule 
    WHERE day_of_week = ? 
    AND academic_year = ?
    AND semester = ?
    AND (teacher_id = ? OR classroom_id = ?)
");
$stmt->execute([$day, $academic_year, $semester, $teacher_id, $classroom_id]);

$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($slots);
