<?php
// api/grading_actions.php
require_once '../includes/auth.php';
// Both admin and teacher can grade
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher') {
    die("Unauthorized");
}
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'save_grades') {
    $schedule_id = $_POST['schedule_id'];
    $subject_id = $_POST['subject_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $student_ids = $_POST['student_ids'] ?? [];
    $raw_scores = $_POST['raw_scores'] ?? [];

    try {
        $pdo->beginTransaction();

        foreach ($student_ids as $sid) {
            $score = $raw_scores[$sid];
            if ($score === '' || $score === null) continue; // Skip empty inputs
            
            $scoreStr = floatval($score);
            
            // Calculate grade
            $grade = '0';
            if ($scoreStr >= 80) $grade = '4.0';
            else if ($scoreStr >= 75) $grade = '3.5';
            else if ($scoreStr >= 70) $grade = '3.0';
            else if ($scoreStr >= 65) $grade = '2.5';
            else if ($scoreStr >= 60) $grade = '2.0';
            else if ($scoreStr >= 55) $grade = '1.5';
            else if ($scoreStr >= 50) $grade = '1.0';

            // Check if grade already exists
            $stmtCheck = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND academic_year = ? AND semester = ?");
            $stmtCheck->execute([$sid, $subject_id, $academic_year, $semester]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // Update
                $stmtUp = $pdo->prepare("UPDATE grades SET raw_score = ?, grade_level = ? WHERE id = ?");
                $stmtUp->execute([$scoreStr, $grade, $existing['id']]);
            } else {
                // Insert
                $stmtIn = $pdo->prepare("INSERT INTO grades (student_id, subject_id, academic_year, semester, raw_score, grade_level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtIn->execute([$sid, $subject_id, $academic_year, $semester, $scoreStr, $grade]);
            }
        }

        $pdo->commit();
        header("Location: ../grading.php?schedule_id={$schedule_id}&status=success&msg=บันทึกคะแนนเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../grading.php?schedule_id={$schedule_id}&status=error&msg=ระบบขัดข้อง ไม่สามารถบันทึกคะแนนได้");
    }
    exit();
}
?>
