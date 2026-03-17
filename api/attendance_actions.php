<?php
// api/attendance_actions.php
require_once '../includes/auth.php';
// Both admin and teacher can mark attendance
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher') {
    die("Unauthorized");
}
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'save_attendance') {
    $schedule_id = $_POST['schedule_id'];
    $date = $_POST['date'];
    $statuses = $_POST['status'] ?? []; // Array of [student_id => status]

    try {
        $pdo->beginTransaction();

        foreach ($statuses as $sid => $status) {
            // Check if attendance already exists
            $stmtCheck = $pdo->prepare("SELECT id FROM attendance_log WHERE student_id = ? AND schedule_id = ? AND date = ?");
            $stmtCheck->execute([$sid, $schedule_id, $date]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // Update
                $stmtUp = $pdo->prepare("UPDATE attendance_log SET status = ? WHERE id = ?");
                $stmtUp->execute([$status, $existing['id']]);
            } else {
                // Insert
                $stmtIn = $pdo->prepare("INSERT INTO attendance_log (schedule_id, student_id, date, status) VALUES (?, ?, ?, ?)");
                $stmtIn->execute([$schedule_id, $sid, $date, $status]);
            }
        }

        $pdo->commit();
        header("Location: ../attendance.php?schedule_id={$schedule_id}&date={$date}&status=success&msg=บันทึกข้อมูลการเข้าเรียนเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../attendance.php?schedule_id={$schedule_id}&date={$date}&status=error&msg=ระบบขัดข้อง ไม่สามารถบันทึกข้อมูลได้");
    }
    exit();
}
?>
