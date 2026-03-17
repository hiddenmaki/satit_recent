<?php
require_once 'includes/db.php';

try {
    // 1. Create Departments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed Departments
    $departments = ['วิทยาศาสตร์', 'คณิตศาสตร์', 'ภาษาต่างประเทศ', 'ภาษาไทย', 'สังคมศึกษา'];
    $stmtDept = $pdo->prepare("INSERT IGNORE INTO departments (name) VALUES (?)");
    foreach($departments as $dept) {
        $stmtDept->execute([$dept]);
    }

    // 2. Create Teachers Details Table
    // We link back to users.username (or we could link to users.id)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `teachers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_code` VARCHAR(20) NOT NULL UNIQUE,
        `user_id` INT NULL, -- Link to login account if they have one
        `prefix` VARCHAR(20) NOT NULL,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `department_id` INT NOT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed Teachers
    // First, verify if teacher user exists to link
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = 'T1001'");
    $stmtUser->execute();
    $user = $stmtUser->fetch();
    $userId = $user ? $user['id'] : null;

    $stmtTeacher = $pdo->prepare("INSERT IGNORE INTO teachers 
        (teacher_code, user_id, prefix, first_name, last_name, department_id, phone) 
        VALUES 
        ('T1001', ?, 'นาย', 'สมภพ', 'เรียนดี', 1, '081-234-5678'),
        ('T1002', NULL, 'นางสาว', 'ใจดี', 'มีสุข', 2, '089-876-5432'),
        ('T1003', NULL, 'Mr.', 'John', 'Smith', 3, '081-111-2222')
    ");
    $stmtTeacher->execute([$userId]);

    echo "<h2 style='color: green;'>Personnel Tables created and seeded successfully!</h2>";

} catch(PDOException $e) {
    die("<h3 style='color:red;'>Setup Failed: " . $e->getMessage() . "</h3>");
}
?>
