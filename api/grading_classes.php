<?php
// api/grading_classes.php - Get classes that have a specific subject (for teacher or admin)
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$subject_id = $_GET['subject_id'] ?? '';
$role = $_SESSION['role'];

if (empty($subject_id)) {
    echo json_encode(['success' => false, 'classes' => []]);
    exit();
}

if ($role === 'teacher') {
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacher = $stmtT->fetch();
    $teacher_id = $teacher ? $teacher['id'] : 0;

    $stmt = $pdo->prepare("
        SELECT DISTINCT cl.id, cl.level_name 
        FROM classes cl 
        JOIN teaching_schedule ts ON ts.class_id = cl.id 
        WHERE ts.teacher_id = ? AND ts.subject_id = ?
        ORDER BY cl.level_name ASC
    ");
    $stmt->execute([$teacher_id, $subject_id]);
} else {
    // Admin: show classes that have this subject in teaching_schedule
    $stmt = $pdo->prepare("
        SELECT DISTINCT cl.id, cl.level_name 
        FROM classes cl 
        JOIN teaching_schedule ts ON ts.class_id = cl.id 
        WHERE ts.subject_id = ?
        ORDER BY cl.level_name ASC
    ");
    $stmt->execute([$subject_id]);
}

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'classes' => $classes
]);
