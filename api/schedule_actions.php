<?php
// api/schedule_actions.php - Full CRUD
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

// Helper: build redirect URL from filter context
function buildRedirect($classId = '', $classroomId = '', $status = 'success', $msg = '')
{
    $url = "../schedule.php?status={$status}";
    if (!empty($classId))
        $url .= "&class_id={$classId}";
    if (!empty($classroomId))
        $url .= "&classroom_id={$classroomId}";
    if (!empty($msg))
        $url .= "&msg=" . urlencode($msg);
    return $url;
}

if ($action === 'create') {
    $teacher_id = $_POST['teacher_id'];
    $subject_id = $_POST['subject_id'];
    $classroom_id = $_POST['classroom_id'];
    $class_id = $_POST['class_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    try {
        // Check time overlaps for teacher or classroom
        $stmtCheck = $pdo->prepare("
            SELECT id FROM teaching_schedule 
            WHERE day_of_week = ? 
            AND academic_year = ? 
            AND semester = ? 
            AND ((teacher_id = ?) OR (classroom_id = ?))
            AND (
                (start_time < ? AND end_time > ?) OR
                (start_time >= ? AND start_time < ?)
            )
        ");
        $stmtCheck->execute([$day_of_week, $academic_year, $semester, $teacher_id, $classroom_id, $end_time, $start_time, $start_time, $end_time]);

        if ($stmtCheck->rowCount() > 0) {
            header("Location: " . buildRedirect($class_id, $classroom_id, 'error', 'ไม่สามารถเพิ่มได้: เวลาทับซ้อนกับตารางสอนของครูหรือห้องเรียนนี้'));
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO teaching_schedule (teacher_id, subject_id, classroom_id, class_id, academic_year, semester, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$teacher_id, $subject_id, $classroom_id, $class_id, $academic_year, $semester, $day_of_week, $start_time, $end_time]);
        header("Location: " . buildRedirect($class_id, $classroom_id, 'success', 'บันทึกตารางเรียนเรียบร้อยแล้ว'));
    } catch (PDOException $e) {
        header("Location: " . buildRedirect($class_id, $classroom_id, 'error', 'เกิดข้อผิดพลาดในฐานข้อมูล'));
    }
    exit();
}

if ($action === 'update') {
    $id = $_POST['schedule_id'];
    $teacher_id = $_POST['teacher_id'];
    $subject_id = $_POST['subject_id'];
    $classroom_id = $_POST['classroom_id'];
    $class_id = $_POST['class_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $redirect_class_id = $_POST['redirect_class_id'] ?? '';
    $redirect_classroom_id = $_POST['redirect_classroom_id'] ?? '';

    try {
        // Check overlaps EXCEPT self
        $stmtCheck = $pdo->prepare("
            SELECT id FROM teaching_schedule 
            WHERE day_of_week = ? 
            AND academic_year = ? 
            AND semester = ? 
            AND ((teacher_id = ?) OR (classroom_id = ?))
            AND id != ?
            AND (
                (start_time < ? AND end_time > ?) OR
                (start_time >= ? AND start_time < ?)
            )
        ");
        $stmtCheck->execute([$day_of_week, $academic_year, $semester, $teacher_id, $classroom_id, $id, $end_time, $start_time, $start_time, $end_time]);

        if ($stmtCheck->rowCount() > 0) {
            header("Location: " . buildRedirect($redirect_class_id, $redirect_classroom_id, 'error', 'ไม่สามารถแก้ไขได้: เวลาทับซ้อนกับตารางสอนอื่น'));
            exit();
        }

        $stmt = $pdo->prepare("UPDATE teaching_schedule SET teacher_id=?, subject_id=?, classroom_id=?, class_id=?, academic_year=?, semester=?, day_of_week=?, start_time=?, end_time=? WHERE id=?");
        $stmt->execute([$teacher_id, $subject_id, $classroom_id, $class_id, $academic_year, $semester, $day_of_week, $start_time, $end_time, $id]);
        header("Location: " . buildRedirect($redirect_class_id, $redirect_classroom_id, 'success_edit'));
    } catch (PDOException $e) {
        header("Location: " . buildRedirect($redirect_class_id, $redirect_classroom_id, 'error', 'เกิดข้อผิดพลาดในฐานข้อมูล'));
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    $redirect_class_id = $_POST['redirect_class_id'] ?? '';
    $redirect_classroom_id = $_POST['redirect_classroom_id'] ?? '';

    try {
        $stmt = $pdo->prepare("DELETE FROM teaching_schedule WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . buildRedirect($redirect_class_id, $redirect_classroom_id, 'success_delete'));
    } catch (PDOException $e) {
        header("Location: " . buildRedirect($redirect_class_id, $redirect_classroom_id, 'error', 'ไม่สามารถลบได้: ข้อมูลถูกอ้างอิงอยู่'));
    }
    exit();
}
?>
