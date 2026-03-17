<?php
// api/student_actions.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

// Validate CSRF token (If you add an AJAX token check)
// if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
//     header("Location: ../master-student.php?status=error&msg=Invalid CSRF Token");
//     exit();
// }

$action = $_POST['action'] ?? '';

// CREATE Student
if ($action === 'create') {
    $studentCode = trim($_POST['student_code']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $prefix = trim($_POST['prefix']);
    $classroom_id = trim($_POST['classroom_id']);
    
    // We also need to create a User account for them automatically
    // The username will be their student_code, password defaults to password123
    
    try {
        $pdo->beginTransaction();
        
        $securePassword = password_hash('password123', PASSWORD_BCRYPT);
        
        // 1. Insert into Users
        $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role, prefix, first_name, last_name) VALUES (?, ?, 'student', ?, ?, ?)");
        $stmtUser->execute([$studentCode, $securePassword, $prefix, $first_name, $last_name]);
        $userId = $pdo->lastInsertId();
        
        // 2. Insert into Students
        $stmtStudent = $pdo->prepare("INSERT INTO students (student_code, user_id, classroom_id) VALUES (?, ?, ?)");
        $stmtStudent->execute([$studentCode, $userId, $classroom_id]);
        
        $pdo->commit();
        header("Location: ../master-student.php?status=success&msg=เพิ่มนักเรียนเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../master-student.php?status=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}

// DELETE Student
if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    
    try {
        // Need to find user_id to delete the user account too
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        
        if($student) {
             // Deleting the user will cascade delete the student record due to our ON DELETE CASCADE setup
             $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
             $stmtDel->execute([$student['user_id']]);
             header("Location: ../master-student.php?status=success&msg=ลบข้อมูลนักเรียนเรียบร้อยแล้ว");
        } else {
             header("Location: ../master-student.php?status=error&msg=ไม่พบข้อมูล");
        }
    } catch (PDOException $e) {
        header("Location: ../master-student.php?status=error&msg=ไม่สามารถลบข้อมูลได้");
    }
    exit();
}
?>
