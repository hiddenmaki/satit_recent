<?php
// api/schedule_actions.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $teacher_id = $_POST['teacher_id'];
    $subject_id = $_POST['subject_id'];
    $classroom_id = $_POST['classroom_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO teaching_schedule (teacher_id, subject_id, classroom_id, academic_year, semester, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$teacher_id, $subject_id, $classroom_id, $academic_year, $semester, $day_of_week, $start_time, $end_time]);
        header("Location: ../schedule.php?classroom_id={$classroom_id}&status=success&msg=บันทึกตารางเรียนเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../schedule.php?classroom_id={$classroom_id}&status=error&msg=ระบบไม่สามารถเพิ่มข้อมูลได้ เวลาอาจทับซ้อนหรือมีข้อผิดพลาด");
    }
    exit();
}
?>
