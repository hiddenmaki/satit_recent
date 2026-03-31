<?php
// api/user_actions.php - Admin User Account Management Actions
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$action = $_POST['action'] ?? '';
$currentUserId = $_SESSION['user_id'];

// === CREATE NEW ADMIN ===
if ($action === 'create_admin') {
    $username = trim($_POST['username']);
    $prefix = trim($_POST['prefix']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    $rawPassword = $_POST['password'];
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])[a-zA-Z0-9]+$/', $rawPassword)) {
        header("Location: ../manage-users.php?status=err_password");
        exit();
    }
    $password = password_hash($rawPassword, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, prefix, first_name, last_name) VALUES (?, ?, 'admin', ?, ?, ?)");
        $stmt->execute([$username, $password, $prefix, $first_name, $last_name]);
        header("Location: ../manage-users.php?status=success_add");
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header("Location: ../manage-users.php?status=err_duplicate");
        } else {
            header("Location: ../manage-users.php?status=err_db");
        }
    }
    exit();
}

// === TOGGLE ACCOUNT STATUS (Active/Inactive) ===
if ($action === 'toggle_status') {
    $user_id = (int) $_POST['user_id'];

    // ป้องกันไม่ให้ระงับบัญชีตัวเอง
    if ($user_id == $currentUserId) {
        header("Location: ../manage-users.php?status=err_self");
        exit();
    }

    try {
        // อ่านสถานะปัจจุบัน แล้วสลับ (toggle)
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
            $stmtUpdate = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmtUpdate->execute([$newStatus, $user_id]);
            header("Location: ../manage-users.php?status=success_toggle");
        } else {
            header("Location: ../manage-users.php?status=err_db");
        }
    } catch (PDOException $e) {
        header("Location: ../manage-users.php?status=err_db");
    }
    exit();
}

// === RESET PASSWORD ===
if ($action === 'reset_password') {
    $user_id = (int) $_POST['user_id'];

    try {
        // รีเซ็ตรหัสผ่านเป็น "Password123" (ตรง Pattern: ตัวพิมพ์ใหญ่ + ตัวพิมพ์เล็ก + ตัวเลข)
        $newPassword = password_hash('Password123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newPassword, $user_id]);
        header("Location: ../manage-users.php?status=success_reset");
    } catch (PDOException $e) {
        header("Location: ../manage-users.php?status=err_db");
    }
    exit();
}

// === CHANGE ROLE ===
if ($action === 'change_role') {
    $user_id = (int) $_POST['user_id'];
    $new_role = $_POST['new_role'];

    // ป้องกันไม่ให้เปลี่ยนสิทธิ์ตัวเอง
    if ($user_id == $currentUserId) {
        header("Location: ../manage-users.php?status=err_self");
        exit();
    }

    // ตรวจสอบว่า Role ที่ส่งมาถูกต้อง
    $validRoles = ['admin', 'teacher', 'student'];
    if (!in_array($new_role, $validRoles)) {
        header("Location: ../manage-users.php?status=err_db");
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        header("Location: ../manage-users.php?status=success_role");
    } catch (PDOException $e) {
        header("Location: ../manage-users.php?status=err_db");
    }
    exit();
}

// === DELETE USER ===
if ($action === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    
    // ป้องกันไม่ให้ลบบัญชีตัวเอง
    if ($user_id == $currentUserId) {
        header("Location: ../manage-users.php?status=err_self");
        exit();
    }
    
    try {
        // ดึงรูุปโปรไฟล์ของนักเรียน/ครู ก่อนทำการลบ เพื่อนำไปไล่ลบไฟล์รูป
        $oldPic = null;
        
        $stmtT = $pdo->prepare("SELECT profile_picture FROM teachers WHERE user_id = ?");
        $stmtT->execute([$user_id]);
        $t = $stmtT->fetch();
        if ($t && !empty($t['profile_picture'])) $oldPic = $t['profile_picture'];
        
        $stmtS = $pdo->prepare("SELECT profile_picture FROM students WHERE user_id = ?");
        $stmtS->execute([$user_id]);
        $s = $stmtS->fetch();
        if ($s && !empty($s['profile_picture'])) $oldPic = $s['profile_picture'];

        // เนื่องจากตาราง teachers และ students มี FOREIGN KEY อ้างอิง users (ON DELETE CASCADE)
        // การลบ record จาก users จะลบข้อมูลจากตารางที่เกี่ยวโยงทั้งหมดอัตโนมัติ
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDel->execute([$user_id]);
        
        // ลบไฟล์รูป (ถ้ามี)
        if ($oldPic && file_exists('../uploads/profiles/' . $oldPic)) {
            unlink('../uploads/profiles/' . $oldPic);
        }
        
        header("Location: ../manage-users.php?status=success_delete");
    } catch (PDOException $e) {
        header("Location: ../manage-users.php?status=err_delete");
    }
    exit();
}
?>