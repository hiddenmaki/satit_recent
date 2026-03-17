<?php
// includes/auth.php - Authentication & RBAC Core
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าเข้าสู่ระบบหรือยัง ถ้ายังให้เตะไปหน้า login.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ฟังก์ชันสำหรับตรวจสอบสิทธิ์การใช้งาน (RBAC)
function requireRole($allowedRoles) {
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
    
    // ถ้าระบุ Role เป็น string ตัวเดียว ให้แปลงเป็นอาร์เรย์
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (!in_array($_SESSION['role'], $allowedRoles)) {
         // ไม่มีสิทธิ์เข้าถึง (Forbidden)
         header("HTTP/1.1 403 Forbidden");
         die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                <h2>403 Forbidden</h2>
                <p>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะ " . implode(', ', $allowedRoles) . " เท่านั้น)</p>
                <a href='index.php'>กลับหน้าหลัก</a>
              </div>");
    }
}
?>
