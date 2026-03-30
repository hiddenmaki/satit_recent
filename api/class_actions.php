<?php
// api/class_actions.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $class_code = trim($_POST['class_code']);
    $level_name = trim($_POST['level_name']);
    
    // Check for duplicate class_code or level_name
    $stmtDup = $pdo->prepare("SELECT id FROM classes WHERE class_code = ? OR level_name = ?");
    $stmtDup->execute([$class_code, $level_name]);
    if ($stmtDup->fetch()) {
        header("Location: ../master-class.php?status=err_duplicate");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO classes (class_code, level_name) VALUES (?, ?)");
        $stmt->execute([$class_code, $level_name]);
        header("Location: ../master-class.php?status=success_add");
    } catch (PDOException $e) {
        header("Location: ../master-class.php?status=error");
    }
    exit();
}

if ($action === 'update') {
    $id = $_POST['class_id'];
    $class_code = trim($_POST['class_code']);
    $level_name = trim($_POST['level_name']);
    
    // Check for duplicate class_code or level_name EXCEPT self
    $stmtDup = $pdo->prepare("SELECT id FROM classes WHERE (class_code = ? OR level_name = ?) AND id != ?");
    $stmtDup->execute([$class_code, $level_name, $id]);
    if ($stmtDup->fetch()) {
        header("Location: ../master-class.php?status=err_duplicate");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE classes SET class_code = ?, level_name = ? WHERE id = ?");
        $stmt->execute([$class_code, $level_name, $id]);
        header("Location: ../master-class.php?status=success_edit");
    } catch (PDOException $e) {
        header("Location: ../master-class.php?status=error");
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../master-class.php?status=success_delete");
    } catch (PDOException $e) {
        header("Location: ../master-class.php?status=err_delete_fk");
    }
    exit();
}
?>
