<?php
// api/teacher_actions.php
require_once '../includes/auth.php'; // ensure logged in
require_once '../includes/db.php';

// Only Admin can manage teachers (Role Based Access Control)
requireRole('admin');

// Helper for Profile Picture
function uploadProfilePicture($file, $oldFile = null) {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $newName = uniqid('prof_') . '.' . $ext;
            $destination = '../uploads/profiles/' . $newName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                if ($oldFile && file_exists('../uploads/profiles/' . $oldFile)) {
                    unlink('../uploads/profiles/' . $oldFile);
                }
                return $newName;
            }
        }
    }
    return $oldFile;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security error: Invalid CSRF Token");
    }

    $action = $_POST['action'] ?? '';

    // === CREATE TEACHER ===
    if ($action === 'create') {
        $t_code = trim($_POST['teacher_code']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $prefix = trim($_POST['prefix']);
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $dept_id = $_POST['department_id'];
        $phone = trim($_POST['phone']);
        $line_id = trim($_POST['line_id'] ?? '');

        try {
            $pdo->beginTransaction();

            // 1. Insert User (Username = Teacher Code)
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role, prefix, first_name, last_name) VALUES (?, ?, 'teacher', ?, ?, ?)");
            $stmtUser->execute([$t_code, $password, $prefix, $fname, $lname]);
            $user_id = $pdo->lastInsertId();

            // 2. Handle File Upload
            $profilePic = uploadProfilePicture($_FILES['profile_picture'] ?? null);

            // 3. Insert Teacher
            $stmtTeacher = $pdo->prepare("INSERT INTO teachers (teacher_code, user_id, department_id, phone, line_id, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtTeacher->execute([$t_code, $user_id, $dept_id, $phone, $line_id, $profilePic]);

            $pdo->commit();
            header("Location: ../master-teacher.php?status=success_add");
            exit;
        } catch(PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                header("Location: ../master-teacher.php?status=err_duplicate");
            } else {
                header("Location: ../master-teacher.php?status=err_db");
            }
            exit;
        }
    }

    // === UPDATE TEACHER ===
    if ($action === 'update') {
        $teacher_id = $_POST['teacher_id'];
        $user_id = $_POST['user_id'];
        
        $prefix = trim($_POST['prefix']);
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $dept_id = $_POST['department_id'];
        $phone = trim($_POST['phone']);
        $line_id = trim($_POST['line_id'] ?? '');

        try {
            $pdo->beginTransaction();

            // Handle password update if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmtPwd = $pdo->prepare("UPDATE users SET password = ?, prefix = ?, first_name = ?, last_name = ? WHERE id = ?");
                $stmtPwd->execute([$password, $prefix, $fname, $lname, $user_id]);
            } else {
                $stmtUser = $pdo->prepare("UPDATE users SET prefix = ?, first_name = ?, last_name = ? WHERE id = ?");
                $stmtUser->execute([$prefix, $fname, $lname, $user_id]);
            }

            // Handle profile picture
            $stmtGetPic = $pdo->prepare("SELECT profile_picture FROM teachers WHERE id = ?");
            $stmtGetPic->execute([$teacher_id]);
            $oldPic = $stmtGetPic->fetchColumn();

            $profilePic = uploadProfilePicture($_FILES['profile_picture'] ?? null, $oldPic);

            // Update Teacher Details
            $stmtTeacher = $pdo->prepare("UPDATE teachers SET department_id = ?, phone = ?, line_id = ?, profile_picture = ? WHERE id = ?");
            $stmtTeacher->execute([$dept_id, $phone, $line_id, $profilePic, $teacher_id]);

            $pdo->commit();
            header("Location: ../master-teacher.php?status=success_edit");
            exit;
        } catch(PDOException $e) {
            $pdo->rollBack();
            header("Location: ../master-teacher.php?status=err_db");
            exit;
        }
    }

    // === DELETE TEACHER ===
    if ($action === 'delete') {
        $user_id = $_POST['user_id']; // Deleting the user automatically deletes the teacher due to CASCADE
        try {
             // Let's get the profile picture first to unlink it
             $stmtGetPic = $pdo->prepare("SELECT profile_picture FROM teachers WHERE user_id = ?");
             $stmtGetPic->execute([$user_id]);
             $oldPic = $stmtGetPic->fetchColumn();

             // Delete User (Which cascades)
             $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
             $stmt->execute([$user_id]);

             if ($oldPic && file_exists('../uploads/profiles/' . $oldPic)) {
                 unlink('../uploads/profiles/' . $oldPic);
             }

             header("Location: ../master-teacher.php?status=success_delete");
             exit;
        } catch (PDOException $e) {
             header("Location: ../master-teacher.php?status=err_delete_fk");
             exit;
        }
    }

} else {
    // If accessed directly without POST
    header("Location: ../master-teacher.php");
    exit;
}
?>
