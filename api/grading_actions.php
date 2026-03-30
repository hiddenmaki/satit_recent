<?php
// api/grading_actions.php - Save grades with auto-calculation
require_once '../includes/auth.php';
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher') {
    die("Unauthorized");
}
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

// Grade calculation: 80-100=4, 70-79=3, 60-69=2, 50-59=1, 0-49=0
function calculateGrade($score) {
    if ($score >= 80) return 4;
    if ($score >= 70) return 3;
    if ($score >= 60) return 2;
    if ($score >= 50) return 1;
    return 0;
}

if ($action === 'save_grades') {
    $subject_id = $_POST['subject_id'];
    $class_id = $_POST['class_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $student_ids = $_POST['student_ids'] ?? [];
    $raw_scores = $_POST['raw_scores'] ?? [];

    // Build redirect URL preserving filters
    $redirectBase = "../grading.php?subject_id={$subject_id}&class_id={$class_id}&academic_year={$academic_year}&semester={$semester}";

    try {
        $pdo->beginTransaction();

        foreach ($student_ids as $sid) {
            $score = $raw_scores[$sid] ?? '';
            if ($score === '' || $score === null) continue; // Skip empty inputs
            
            $scoreInt = intval($score);
            // Clamp to 0-100
            if ($scoreInt < 0) $scoreInt = 0;
            if ($scoreInt > 100) $scoreInt = 100;
            
            // Auto-calculate grade
            $grade = calculateGrade($scoreInt);

            // Check if grade already exists for this student/subject/year/semester
            $stmtCheck = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND academic_year = ? AND semester = ?");
            $stmtCheck->execute([$sid, $subject_id, $academic_year, $semester]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // Update existing grade
                $stmtUp = $pdo->prepare("UPDATE grades SET raw_score = ?, grade_level = ? WHERE id = ?");
                $stmtUp->execute([$scoreInt, $grade, $existing['id']]);
            } else {
                // Insert new grade
                $stmtIn = $pdo->prepare("INSERT INTO grades (student_id, subject_id, academic_year, semester, raw_score, grade_level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtIn->execute([$sid, $subject_id, $academic_year, $semester, $scoreInt, $grade]);
            }
        }

        $pdo->commit();
        header("Location: {$redirectBase}&status=success&msg=" . urlencode("บันทึกคะแนนเรียบร้อยแล้ว"));
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: {$redirectBase}&status=error&msg=" . urlencode("ระบบขัดข้อง ไม่สามารถบันทึกคะแนนได้"));
    }
    exit();
}
?>
