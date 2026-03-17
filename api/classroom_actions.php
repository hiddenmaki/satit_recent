<?php
// api/classroom_actions.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $class_id = $_POST['class_id'];
    $room_name = trim($_POST['room_name']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO classrooms (class_id, room_name) VALUES (?, ?)");
        $stmt->execute([$class_id, $room_name]);
        header("Location: ../master-classroom.php?status=success&msg=เพิ่มห้องเรียนเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../master-classroom.php?status=error&msg=ระบบไม่สามารถเพิ่มข้อมูลได้");
    }
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../master-classroom.php?status=success&msg=ลบข้อมูลห้องเรียนเรียบร้อยแล้ว");
    } catch (PDOException $e) {
        header("Location: ../master-classroom.php?status=error&msg=ไม่สามารถลบข้อมูลได้ อาจมีนักเรียนอยู่ในห้องนี้");
    }
    exit();
}
?>
