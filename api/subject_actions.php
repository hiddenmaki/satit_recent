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
    
    try {
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, name, credit, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name, $credit, $type]);
        header("Location: ../master-subject.php?status=success&msg=เพิ่มรายวิชาเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../master-subject.php?status=error&msg=ระบบไม่สามารถเพิ่มข้อมูลได้ รหัสวิชาอาจซ้ำกัน");
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../master-subject.php?status=success&msg=ลบข้อมูลรายวิชาเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../master-subject.php?status=error&msg=ไม่สามารถลบข้อมูลได้ อาจมีการใช้งานวิชานี้อยู่");
    }
    exit();
}
?>
