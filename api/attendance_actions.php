<?php
// api/attendance_actions.php - AJAX Toggle + Bulk Save
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

// Status labels (Thai) for AJAX response
$statusLabels = [
    'present' => 'มาเรียน',
    'late' => 'สาย',
    'leave' => 'ลา',
    'absent' => 'ขาดเรียน'
];

// ========== AJAX: Toggle single student status ==========
if ($action === 'toggle_status') {
    header('Content-Type: application/json; charset=utf-8');
    
    $schedule_id = $_POST['schedule_id'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $status = $_POST['status'] ?? '';
    
    // Validate status value
    $validStatuses = ['present', 'late', 'leave', 'absent'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    if (empty($schedule_id) || empty($student_id) || empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    try {
        // Check if record already exists
        $stmtCheck = $pdo->prepare("SELECT id FROM attendance_log WHERE student_id = ? AND schedule_id = ? AND date = ?");
        $stmtCheck->execute([$student_id, $schedule_id, $date]);
        $existing = $stmtCheck->fetch();
        
        if ($existing) {
            // Update existing record
            $stmtUp = $pdo->prepare("UPDATE attendance_log SET status = ? WHERE id = ?");
            $stmtUp->execute([$status, $existing['id']]);
        } else {
            // Insert new record
            $stmtIn = $pdo->prepare("INSERT INTO attendance_log (schedule_id, student_id, date, status) VALUES (?, ?, ?, ?)");
            $stmtIn->execute([$schedule_id, $student_id, $date, $status]);
        }
        
        // Get student name for the response toast
        $stmtName = $pdo->prepare("
            SELECT u.first_name, u.last_name 
            FROM students s JOIN users u ON s.user_id = u.id 
            WHERE s.id = ?
        ");
        $stmtName->execute([$student_id]);
        $studentData = $stmtName->fetch();
        $studentName = $studentData ? $studentData['first_name'] . ' ' . $studentData['last_name'] : '';
        
        echo json_encode([
            'success' => true,
            'student_id' => $student_id,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? $status,
            'student_name' => $studentName
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// ========== Form Submit: Bulk save (legacy fallback) ==========
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
        header("Location: ../attendance.php?schedule_id={$schedule_id}&date={$date}&status=success&msg=" . urlencode("บันทึกข้อมูลการเข้าเรียนเรียบร้อยแล้ว"));
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../attendance.php?schedule_id={$schedule_id}&date={$date}&status=error&msg=" . urlencode("ระบบขัดข้อง ไม่สามารถบันทึกข้อมูลได้"));
    }
    exit();
}
?>