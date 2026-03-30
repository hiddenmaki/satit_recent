<?php
// api/classroom_actions.php - Room Management API
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $room_code = trim($_POST['room_code']);
    $room_number = trim($_POST['room_number']);
    $room_name = trim($_POST['room_name']);
    
    // Check duplicate room_code
    $stmtDup = $pdo->prepare("SELECT id FROM classrooms WHERE room_code = ?");
    $stmtDup->execute([$room_code]);
    if ($stmtDup->fetch()) {
        header("Location: ../master-classroom.php?status=err_duplicate");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO classrooms (room_code, room_number, room_name) VALUES (?, ?, ?)");
        $stmt->execute([$room_code, $room_number, $room_name]);
        header("Location: ../master-classroom.php?status=success_add");
    } catch (PDOException $e) {
        header("Location: ../master-classroom.php?status=error");
    }
    exit();
}

if ($action === 'update') {
    $id = $_POST['classroom_id'];
    $room_code = trim($_POST['room_code']);
    $room_number = trim($_POST['room_number']);
    $room_name = trim($_POST['room_name']);
    
    // Check duplicate room_code EXCEPT self
    $stmtDup = $pdo->prepare("SELECT id FROM classrooms WHERE room_code = ? AND id != ?");
    $stmtDup->execute([$room_code, $id]);
    if ($stmtDup->fetch()) {
        header("Location: ../master-classroom.php?status=err_duplicate");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE classrooms SET room_code = ?, room_number = ?, room_name = ? WHERE id = ?");
        $stmt->execute([$room_code, $room_number, $room_name, $id]);
        header("Location: ../master-classroom.php?status=success_edit");
    } catch (PDOException $e) {
        header("Location: ../master-classroom.php?status=error");
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../master-classroom.php?status=success_delete");
    } catch (PDOException $e) {
        header("Location: ../master-classroom.php?status=err_delete_fk");
    }
    exit();
}
?>
