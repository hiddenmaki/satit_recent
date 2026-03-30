<?php
// api/subject_actions.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $code = trim($_POST['subject_code']);
    $name = trim($_POST['name']);
    $credit = $_POST['credit'];
    $type = $_POST['type'];
    
    // Check Duplicate code
    $stmtDup = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ?");
    $stmtDup->execute([$code]);
    if ($stmtDup->fetch()) {
        header("Location: ../master-subject.php?status=err_duplicate");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, name, credit, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name, $credit, $type]);
        header("Location: ../master-subject.php?status=success_add");
    } catch (PDOException $e) {
        header("Location: ../master-subject.php?status=error");
    }
    exit();
}

if ($action === 'update') {
    $id = $_POST['subject_id'];
    $code = trim($_POST['subject_code']);
    $name = trim($_POST['name']);
    $credit = $_POST['credit'];
    $type = $_POST['type'];
    
    // Check Duplicate code EXCEPT self
    $stmtDup = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ? AND id != ?");
    $stmtDup->execute([$code, $id]);
    if ($stmtDup->fetch()) {
        header("Location: ../master-subject.php?status=err_duplicate");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE subjects SET subject_code=?, name=?, credit=?, type=? WHERE id=?");
        $stmt->execute([$code, $name, $credit, $type, $id]);
        header("Location: ../master-subject.php?status=success_edit");
    } catch (PDOException $e) {
        header("Location: ../master-subject.php?status=error");
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../master-subject.php?status=success_delete");
    } catch (PDOException $e) {
        header("Location: ../master-subject.php?status=err_delete_fk");
    }
    exit();
}
?>
