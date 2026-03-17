<?php
// setup_db.php - Run once to initialize Full Modern Database Architecture
$host = 'localhost';
$username = 'root'; 
$password = ''; 

try {
    // 1. Connect without specific db to create it
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS satitschool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE satitschool");

// ==========================================
// 3. TABLE CREATIONS
// ==========================================

    // Users Table (Authentication)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `users` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Departments Table (For Teachers)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `departments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Classes Table (E.g., M.1, M.2)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `classes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `level_name` VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Classrooms Table (E.g., M.4/1)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `classrooms` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `class_id` INT NOT NULL,
        `room_name` VARCHAR(50) NOT NULL,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Teachers Profile Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `teachers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_code` VARCHAR(50) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `department_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Students Profile Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `students` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_code` VARCHAR(50) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `classroom_id` INT NULL,
        `parent_name` VARCHAR(100) NULL,
        `parent_phone` VARCHAR(20) NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Subjects Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `subjects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `subject_code` VARCHAR(20) NOT NULL UNIQUE,
        `name` VARCHAR(150) NOT NULL,
        `credit` DECIMAL(3,1) NOT NULL DEFAULT 1.0,
        `type` ENUM('core', 'elective') DEFAULT 'core'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Teaching Schedule Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `teaching_schedule` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `classroom_id` INT NOT NULL,
        `academic_year` VARCHAR(10) NOT NULL,
        `semester` INT NOT NULL,
        `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Grades Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `grades` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `academic_year` VARCHAR(10) NOT NULL,
        `semester` INT NOT NULL,
        `raw_score` INT DEFAULT NULL,
        `grade_level` DECIMAL(3,1) DEFAULT NULL,
        UNIQUE KEY `unique_student_subject_term` (`student_id`, `subject_id`, `academic_year`, `semester`),
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Attendance Log Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `attendance_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `schedule_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `date` DATE NOT NULL,
        `status` ENUM('present', 'late', 'leave', 'absent') NOT NULL,
        UNIQUE KEY `unique_attendance` (`schedule_id`, `student_id`, `date`),
        FOREIGN KEY (`schedule_id`) REFERENCES `teaching_schedule`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

// ==========================================
// 4. SEEDING INITIAL DATA
// ==========================================

    $securePassword = password_hash('password123', PASSWORD_BCRYPT);

    // Seed Master Admins & Example Users
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

    // Seed Teachers (Link User ID -> Teacher Code)
    $pdo->exec("INSERT IGNORE INTO teachers (id, teacher_code, user_id, department_id, phone) VALUES 
        (1, 'T1001', 2, 1, '081-111-1111'),
        (2, 'T1002', 4, 2, '082-222-2222')
    ");

    // Seed Classes & Classrooms
    $pdo->exec("INSERT IGNORE INTO classes (id, level_name) VALUES (1, 'ม.4'), (2, 'ม.5'), (3, 'ม.6')");
    $pdo->exec("INSERT IGNORE INTO classrooms (id, class_id, room_name) VALUES (1, 1, 'ม.4/1'), (2, 1, 'ม.4/2')");

    // Seed Students (Link User ID -> Student Code)
    $pdo->exec("INSERT IGNORE INTO students (id, student_code, user_id, classroom_id) VALUES 
        (1, 'S6701001', 3, 1),
        (2, 'S6701002', 5, 1)
    ");

    // Seed Subjects
    $pdo->exec("INSERT IGNORE INTO subjects (id, subject_code, name, credit, type) VALUES 
        (1, 'ว31101', 'ฟิสิกส์ 1', 1.5, 'core'),
        (2, 'ค31101', 'คณิตศาสตร์เพิ่มเติม 1', 2.0, 'elective'),
        (3, 'อ31101', 'ภาษาอังกฤษพื้นฐาน 1', 1.0, 'core')
    ");

    // Seed Teaching Schedule
    // T1001 teaches Physics (1) to M.4/1 (1) on Monday 08:30-10:10
    // T1002 teaches Math (2) to M.4/1 (1) on Monday 10:10-12:00
    $pdo->exec("INSERT IGNORE INTO teaching_schedule (id, teacher_id, subject_id, classroom_id, academic_year, semester, day_of_week, start_time, end_time) VALUES 
        (1, 1, 1, 1, '2567', 1, 'Monday', '08:30:00', '10:10:00'),
        (2, 2, 2, 1, '2567', 1, 'Monday', '10:10:00', '12:00:00')
    ");

    // Seed some initial grades for S6701001 and S6701002
    $pdo->exec("INSERT IGNORE INTO grades (student_id, subject_id, academic_year, semester, raw_score, grade_level) VALUES 
        (1, 1, '2567', 1, 85, 4.0),
        (1, 2, '2567', 1, 78, 3.5),
        (2, 1, '2567', 1, 75, 3.0),
        (2, 2, '2567', 1, 92, 4.0)
    ");

    // Seed some attendance
    $dateToday = date('Y-m-d');
    $pdo->exec("INSERT IGNORE INTO attendance_log (schedule_id, student_id, date, status) VALUES 
        (1, 1, '$dateToday', 'present'),
        (1, 2, '$dateToday', 'late')
    ");

    echo "<h2 style='color: green;'>Full Database Architecture created and seeded successfully!</h2>";
    echo "<p>Test Accounts created (Password for all is: <b>password123</b>):</p>";
    echo "<ul>
            <li>Admin: <b>admin</b></li>
            <li>Teacher 1 (Physics): <b>T1001</b></li>
            <li>Teacher 2 (Math): <b>T1002</b></li>
            <li>Student 1: <b>S6701001</b></li>
            <li>Student 2: <b>S6701002</b></li>
          </ul>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Setup Failed: " . $e->getMessage() . "</h3>");
}
?>
