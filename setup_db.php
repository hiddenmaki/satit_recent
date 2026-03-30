<?php
// setup_db.php - วนที่ตรวจทานแล้ว (v2.1)
// ไฟล์นี้ใช้สำหรับสร้างฐานข้อมูลและตารางทั้งหมด พร้อมลงข้อมูลตัวอย่าง (Seeding)
$host = 'localhost';
$username = 'root'; 
$password = ''; 

try {
    // 1. เชื่อมต่อฐานข้อมูล
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. สร้าง Database (รองรับภาษาไทย)
    $pdo->exec("CREATE DATABASE IF NOT EXISTS satitschool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE satitschool");

// ==========================================
// 3. การสร้างตาราง (TABLE CREATIONS)
// ==========================================

    // ตารางผู้ใช้งาน (Users)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'teacher', 'student') NOT NULL,
        `prefix` VARCHAR(50) DEFAULT '',
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `last_login` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ตารางหมวดวิชา (Departments)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางระดับชั้น (Classes - เช่น ม.4/1, ม.4/2)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `classes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `class_code` VARCHAR(50) NULL,
        `level_name` VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางสถานที่เรียน/ห้องเรียน (Classrooms - เช่น ห้อง 101, ห้องปฏิบัติการ 1)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `classrooms` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `room_code` VARCHAR(50) NULL,
        `room_number` VARCHAR(50) NULL,
        `class_id` INT NULL,
        `room_name` VARCHAR(50) NOT NULL,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางข้อมูลครู (Teachers)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `teachers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_code` VARCHAR(20) NOT NULL UNIQUE,
        `user_id` INT NULL,
        `prefix` VARCHAR(20) NOT NULL,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `department_id` INT NOT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `line_id` VARCHAR(50) DEFAULT NULL,
        `profile_picture` VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางข้อมูลนักเรียน (Students)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_code` VARCHAR(50) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `classroom_id` INT NULL,
        `class_id` INT NULL,
        `dob` DATE DEFAULT NULL,
        `profile_picture` VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางข้อมูลรายวิชา (Subjects)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `subject_code` VARCHAR(20) NOT NULL UNIQUE,
        `name` VARCHAR(150) NOT NULL,
        `credit` DECIMAL(3,1) NOT NULL DEFAULT 1.0,
        `type` ENUM('core', 'elective') DEFAULT 'core'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางจัดการตารางเรียน (Teaching Schedule)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `teaching_schedule` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `classroom_id` INT NOT NULL,
        `class_id` INT NULL,
        `academic_year` VARCHAR(10) NOT NULL,
        `semester` INT NOT NULL,
        `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางบันทึกคะแนนและเกรด (Grades)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `grades` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `academic_year` VARCHAR(10) NOT NULL,
        `semester` INT NOT NULL,
        `raw_score` INT DEFAULT NULL,
        `grade_level` INT DEFAULT NULL, -- เก็บเกรด 0, 1, 2, 3, 4
        UNIQUE KEY `unique_student_subject` (`student_id`, `subject_id`, `academic_year`, `semester`),
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ตารางบันทึกเวลาเรียน (Attendance Log)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `schedule_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `date` DATE NOT NULL,
        `status` ENUM('present', 'late', 'leave', 'absent') NOT NULL,
        UNIQUE KEY `unique_attendance` (`schedule_id`, `student_id`, `date`),
        FOREIGN KEY (`schedule_id`) REFERENCES `teaching_schedule`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ==========================================
// 4. การลงข้อมูลตัวอย่าง (SEEDING DATA)
// ==========================================

    $securePassword = password_hash('password123', PASSWORD_BCRYPT);

    // Seed Users
    $pdo->exec("INSERT IGNORE INTO users (id, username, password, role, prefix, first_name, last_name) VALUES 
        (1, 'admin', '$securePassword', 'admin', 'ดร.', 'ผู้ดูแล', 'ระบบสูงสุด'),
        (2, 'T1001', '$securePassword', 'teacher', 'นาย', 'สมภพ', 'เรียนดี'),
        (3, 'S6701001', '$securePassword', 'student', 'นาย', 'เรียนเก่ง', 'ขยันอ่าน'),
        (4, 'T1002', '$securePassword', 'teacher', 'นางสาว', 'ใจดี', 'มีสุข'),
        (5, 'S6701002', '$securePassword', 'student', 'นางสาว', 'ขยัน', 'มุ่งมั่น')
    ");

    // Seed Departments
    $pdo->exec("INSERT IGNORE INTO departments (id, name) VALUES 
        (1, 'หมวดวิชาวิทยาศาสตร์'), (2, 'หมวดวิชาคณิตศาสตร์'), (3, 'หมวดวิชาภาษาต่างประเทศ')
    ");

    // Seed Teachers
    $pdo->exec("INSERT IGNORE INTO teachers (id, teacher_code, user_id, prefix, first_name, last_name, department_id, phone) VALUES 
        (1, 'T1001', 2, 'นาย', 'สมภพ', 'เรียนดี', 1, '081-111-1111'),
        (2, 'T1002', 4, 'นางสาว', 'ใจดี', 'มีสุข', 2, '082-222-2222')
    ");

    // Seed Classes (ม.4/1, ม.4/2)
    $pdo->exec("INSERT IGNORE INTO classes (id, class_code, level_name) VALUES 
        (1, 'SEC101', 'ม.4/1'), 
        (2, 'SEC102', 'ม.4/2')
    ");

    // Seed Classrooms (ห้องเรียนจริง)
    $pdo->exec("INSERT IGNORE INTO classrooms (id, room_code, room_number, class_id, room_name) VALUES 
        (1, 'R101', '101', 1, 'ห้องเรียน 101'), 
        (2, 'R102', '102', 2, 'ห้องเรียน 102')
    ");

    // Seed Students
    $pdo->exec("INSERT IGNORE INTO students (id, student_code, user_id, class_id, classroom_id) VALUES 
        (1, 'S6701001', 3, 1, 1),
        (2, 'S6701002', 5, 1, 1)
    ");

    // Seed Subjects
    $pdo->exec("INSERT IGNORE INTO subjects (id, subject_code, name, credit, type) VALUES 
        (1, 'ว31101', 'ฟิสิกส์ 1', 1.5, 'core'),
        (2, 'ค31101', 'คณิตศาสตร์เพิ่มเติม 1', 2.0, 'elective')
    ");

    // Seed Teaching Schedule
    $pdo->exec("INSERT IGNORE INTO teaching_schedule (id, teacher_id, subject_id, classroom_id, class_id, academic_year, semester, day_of_week, start_time, end_time) VALUES 
        (1, 1, 1, 1, 1, '2567', 1, 'Monday', '08:30:00', '10:10:00'),
        (2, 2, 2, 1, 1, '2567', 1, 'Monday', '10:10:00', '12:00:00')
    ");

    // Seed Grades (เกณฑ์: 78 คือเกรด 3)
    $pdo->exec("INSERT IGNORE INTO grades (student_id, subject_id, academic_year, semester, raw_score, grade_level) VALUES 
        (1, 1, '2567', 1, 85, 4),
        (1, 2, '2567', 1, 78, 3),
        (2, 1, '2567', 1, 75, 3),
        (2, 2, '2567', 1, 92, 4)
    ");

    echo "<h2 style='color: green;'>Full Database Architecture created and seeded successfully (v2.1)!</h2>";
    echo "<p>Test Accounts created (Password for all is: <b>password123</b>)</p>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Setup Failed: " . $e->getMessage() . "</h3>");
}
?>
