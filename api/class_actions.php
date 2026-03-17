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
    $level_name = trim($_POST['level_name']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO classes (level_name) VALUES (?)");
        $stmt->execute([$level_name]);
        header("Location: ../master-class.php?status=success&msg=เพิ่มระดับชั้นเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../master-class.php?status=error&msg=ระบบไม่สามารถเพิ่มข้อมูลได้ ระดับชั้นอาจซ้ำกัน");
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../master-class.php?status=success&msg=ลบข้อมูลระดับชั้นเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../master-class.php?status=error&msg=ไม่สามารถลบข้อมูลได้ อาจมีการใช้งานระดับชั้นนี้อยู่");
    }
    exit();
}
?>
